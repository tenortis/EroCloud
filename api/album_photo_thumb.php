<?php

define('SAFE_INC', 1);

if (ob_get_level() === 0) {
    ob_start();
}
@ini_set('memory_limit', '512M');

include_once("../config.inc.php");
include_once(API_DIR . "/common.inc.php");
@session_write_close();

if (!defined('EC_THUMB_EXIF_FIX_TS')) {
    define('EC_THUMB_EXIF_FIX_TS', 1795520600);
}

function no_image()
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $candidates = array(
        MCP_DIR . '/mcp_neu/web/assets/img/movie_poster_nopic.jpg',
        MCP_DIR . '/images/movie_poster_nopic.jpg',
        SOURCEDIR . '/acp/images/movie_poster_nopic.jpg',
    );
    foreach ($candidates as $filename) {
        if (is_file($filename)) {
            header('Content-type: image/jpeg');
            header('Content-transfer-encoding: binary');
            header('Content-length: ' . filesize($filename));
            readfile($filename);
            break;
        }
    }

    p4c_close(DB_HOST);
    p4c_errorlog(error_get_last());
}

/**
 * Prüft per schnellem 7x7-Stichproben-Raster, ob ein bestehendes kleines Vorschaubild
 * beschädigt oder komplett schwarz ist (z. B. aus fehlgeschlagenen Alt-Konvertierungen).
 */
function ec_thumb_is_solid_black($thumb_path)
{
    $raw = @file_get_contents($thumb_path);
    if ($raw === false || $raw === '') {
        return true;
    }
    $im = @imagecreatefromstring($raw);
    unset($raw);
    if (!$im) {
        return true;
    }

    $w = imagesx($im);
    $h = imagesy($im);
    if ($w <= 0 || $h <= 0) {
        unset($im);
        return true;
    }

    for ($gy = 1; $gy <= 7; $gy++) {
        $py = min($h - 1, max(0, (int)round(($h - 1) * ($gy / 8.0))));
        for ($gx = 1; $gx <= 7; $gx++) {
            $px  = min($w - 1, max(0, (int)round(($w - 1) * ($gx / 8.0))));
            $rgb = imagecolorsforindex($im, imagecolorat($im, $px, $py));
            if (($rgb['red'] ?? 0) >= 6 || ($rgb['green'] ?? 0) >= 6 || ($rgb['blue'] ?? 0) >= 6) {
                unset($im);
                return false;
            }
        }
    }

    unset($im);
    return true;
}

/**
 * Prüft, ob das verpixelte Vorschaubild (thumb_{filename}) neu generiert werden muss:
 *  1. Datei fehlt oder ist zu klein/beschädigt (<= 3100 Bytes)
 *  2. Das Originalbild wurde nach der Erstellung des Thumbnails geändert/gedreht (filemtime)
 *  3. Altbestand-Selbstheilung vor EC_THUMB_EXIF_FIX_TS:
 *     - JPEGs mit EXIF Orientation > 1 (2..8 inkl. 180°, 1:1-Quadrat & Frontkamera-Spiegelung)
 *     - Komplett schwarze Alt-Thumbnails (< 20 KB, z. B. 9:16-Hochformat > 3100 Bytes bei Orientation = 1)
 *  4. Dauerhafte 90°/270°-Seitenverhältnis-Prüfung (Orientation 5/6/7/8)
 */
function ec_album_thumb_needs_regeneration($image, $thumb_path, $ext)
{
    if (!is_file($thumb_path)) {
        return true;
    }

    $thumbBytes = (int)@filesize($thumb_path);
    if ($thumbBytes <= 3100) {
        return true;
    }

    $origMtime  = (int)@filemtime($image);
    $thumbMtime = (int)@filemtime($thumb_path);
    if ($origMtime > 0 && $thumbMtime < $origMtime) {
        return true;
    }

    if ($thumbMtime < EC_THUMB_EXIF_FIX_TS && $thumbBytes < 20480) {
        if (ec_thumb_is_solid_black($thumb_path)) {
            return true;
        }
    }

    $orient = function_exists('ec_read_exif_orientation') ? ec_read_exif_orientation($image) : 1;
    if ($orient >= 2 && $orient <= 8) {
        if ($thumbMtime < EC_THUMB_EXIF_FIX_TS) {
            return true;
        }
        if (in_array($orient, array(5, 6, 7, 8), true)) {
            $origSize  = @getimagesize($image);
            $thumbSize = @getimagesize($thumb_path);
            if (is_array($origSize) && is_array($thumbSize) && $origSize[0] > 0 && $origSize[1] > 0 && $thumbSize[0] > 0 && $thumbSize[1] > 0) {
                $origIsLandscape  = ($origSize[0] > $origSize[1]);
                $origIsPortrait   = ($origSize[1] > $origSize[0]);
                $thumbIsLandscape = ($thumbSize[0] > $thumbSize[1]);
                $thumbIsPortrait  = ($thumbSize[1] > $thumbSize[0]);
                if (($origIsLandscape && $thumbIsLandscape) || ($origIsPortrait && $thumbIsPortrait)) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * Lädt das Quellbild speicherschonend, skaliert es zuerst auf Zielgröße (~200px) herunter
 * und wendet erst danach die EXIF-Drehung an (halbiert den RAM-Bedarf bei 48-50-MP-Fotos).
 * Unterstützt zusätzlich WebP/Transparenz sowie Imagick-Fallback (z. B. für HEIC/AVIF/CMYK).
 */
function ec_load_scaled_source_for_thumb($image, $size, $context_info = array())
{
    $targetSize = max(50, (int)$size);
    $orient     = function_exists('ec_read_exif_orientation') ? ec_read_exif_orientation($image) : 1;

    $image_data = @file_get_contents($image);
    if ($image_data !== false && $image_data !== '') {
        $im = @imagecreatefromstring($image_data);
        unset($image_data);

        if ($im) {
            $rawW = imagesx($im);
            $rawH = imagesy($im);
            if ($rawW > 0 && $rawH > 0) {
                // Bei 90°/270°-EXIF-Drehung (5/6/7/8) tauschen Breite und Höhe nach dem Drehen
                if (in_array($orient, array(5, 6, 7, 8), true)) {
                    $preH = $targetSize;
                    $preW = max(1, (int)round($targetSize * ($rawW / max(1, $rawH))));
                } else {
                    $preW = $targetSize;
                    $preH = max(1, (int)round($targetSize * ($rawH / max(1, $rawW))));
                }

                $scaled = imagecreatetruecolor($preW, $preH);
                if ($scaled) {
                    // Heller Hintergrund verhindert schwarze Flächen bei PNG/WebP mit Alpha-Transparenz
                    $white = imagecolorallocate($scaled, 255, 255, 255);
                    imagefilledrectangle($scaled, 0, 0, $preW - 1, $preH - 1, $white);
                    imagealphablending($scaled, true);
                    imagecopyresampled($scaled, $im, 0, 0, 0, 0, $preW, $preH, $rawW, $rawH);
                    // Volles Originalbild sofort aus dem RAM freigeben, bevor gedreht wird!
                    unset($im);

                    if ($orient >= 2 && $orient <= 8 && function_exists('ec_apply_exif_orientation')) {
                        $scaled = ec_apply_exif_orientation($scaled, $orient);
                    }
                    return $scaled;
                }
            }
            unset($im);
        }
    }

    // Fallback über Imagick (z. B. falls Smartphone-HEIC/AVIF/CMYK als .jpg hochgeladen wurde)
    if (class_exists('Imagick')) {
        try {
            $imagick = new \Imagick();
            $imagick->readImage($image);
            if ($imagick->getNumberImages() > 1) {
                $imagick->setIteratorIndex(0);
            }
            if (method_exists($imagick, 'autoOrient')) {
                $imagick->autoOrient();
            } elseif (method_exists($imagick, 'autoOrientImage')) {
                $imagick->autoOrientImage();
            }
            $imagick->setImageBackgroundColor('white');
            if (defined('Imagick::ALPHACHANNEL_REMOVE')) {
                $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            }
            $imagick->thumbnailImage($targetSize, 0);
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(95);
            $blob = $imagick->getImageBlob();
            $imagick->clear();
            unset($imagick);

            if (is_string($blob) && $blob !== '') {
                $imFromBlob = @imagecreatefromstring($blob);
                unset($blob);
                if ($imFromBlob) {
                    return $imFromBlob;
                }
            }
        } catch (\Throwable $e) {
            $album_info = !empty($context_info) ? "Album-ID: " . ($context_info['album_id'] ?? '') . ", Photo-ID: " . ($context_info['photo_id'] ?? '') . ", Pfad: {$image}" : "Pfad: {$image}";
            error_log("[EroCloud Photo Error] Imagick-Fallback fehlgeschlagen ({$e->getMessage()})! {$album_info}");
        }
    }

    $album_info = !empty($context_info) ? "Album-ID: " . ($context_info['album_id'] ?? '') . ", Photo-ID: " . ($context_info['photo_id'] ?? '') . ", Pfad: {$image}" : "Pfad: {$image}";
    error_log("[EroCloud Photo Error] Bilddatei konnte weder per GD noch per Imagick dekodiert werden! {$album_info}");
    return null;
}

function pixelate($image, $thumb_path, $size = 200, $pixelate_x = 3, $pixelate_y = 3, $context_info = array())
{
    $ext = strtolower(pathinfo($image, PATHINFO_EXTENSION));

    if (ec_album_thumb_needs_regeneration($image, $thumb_path, $ext)) {
        $neuesBild = ec_load_scaled_source_for_thumb($image, $size, $context_info);
        if (!$neuesBild) {
            return false;
        }

        $imgW = imagesx($neuesBild);
        $imgH = imagesy($neuesBild);
        if ($imgW <= 0 || $imgH <= 0) {
            unset($neuesBild);
            return false;
        }

        // Pixelate-Loop mit Abmessungs-Prüfung zur Vermeidung von imagecolorat Out-of-Bounds
        for ($y = 0; $y < $imgH; $y += $pixelate_y + 1) {
            for ($x = 0; $x < $imgW; $x += $pixelate_x + 1) {
                if ($x < $imgW && $y < $imgH) {
                    $rgb   = imagecolorsforindex($neuesBild, imagecolorat($neuesBild, $x, $y));
                    $color = imagecolorclosest($neuesBild, $rgb['red'], $rgb['green'], $rgb['blue']);
                    imagefilledrectangle($neuesBild, $x, $y, min($x + $pixelate_x, $imgW - 1), min($y + $pixelate_y, $imgH - 1), $color);
                }
            }
        }

        $tmpThumb = $thumb_path . '.tmp_' . mt_rand(1000, 9999);
        $saved    = ($ext === 'png')
            ? @imagepng($neuesBild, $tmpThumb, 8)
            : @imagejpeg($neuesBild, $tmpThumb, 95);
        unset($neuesBild);

        if ($saved && is_file($tmpThumb) && (int)@filesize($tmpThumb) > 0) {
            if (!@rename($tmpThumb, $thumb_path)) {
                if (@copy($tmpThumb, $thumb_path)) {
                    @unlink($tmpThumb);
                } else {
                    @unlink($tmpThumb);
                    return false;
                }
            }
            @chmod($thumb_path, 0777);
            @touch($thumb_path, max(time(), EC_THUMB_EXIF_FIX_TS + 1));
            clearstatcache(true, $thumb_path);
        } else {
            if (is_file($tmpThumb)) {
                @unlink($tmpThumb);
            }
            return false;
        }
    }

    if (is_file($thumb_path) && (int)@filesize($thumb_path) > 0) {
        $thumbMime = function_exists('mime_content_type') ? (string)@mime_content_type($thumb_path) : '';
        if ($thumbMime === '' || strpos($thumbMime, 'image/') !== 0) {
            $thumbMime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
        }
        $newMtime = max((int)@filemtime($image), (int)@filemtime($thumb_path));

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Pragma: cache');
        header('Cache-control: max-age=' . (60 * 60 * 24 * 360) . ', public');
        header('Expires: ' . gmdate(DATE_RFC1123, time() + 60 * 60 * 24 * 365));
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $newMtime) . ' GMT', true, 200);
        header('Content-type: ' . $thumbMime);
        header('Content-Length: ' . filesize($thumb_path));
        header('Content-transfer-encoding: binary');
        readfile($thumb_path);
        return true;
    }

    return false;
}

if (!isset($_GET['photo_id']) || !is_string($_GET['photo_id']) || empty($_GET['photo_id'])) {
    error_log('[EroCloud Photo Error] Aufruf ohne photo_id Parameter.');
    no_image();
    exit;
}

$raw_photo_id = trim((string)$_GET['photo_id']);
$fsk_mode     = null;
if (preg_match('#^FSK(16|18)/(.+)$#i', $raw_photo_id, $m)) {
    $fsk_mode = (int)$m[1];
    $photo_id = trim($m[2]);
} elseif (strpos($raw_photo_id, '/') !== false) {
    $photo_id = basename($raw_photo_id);
} else {
    $photo_id = $raw_photo_id;
}
// Falls Query-Fragmente am Pfad hingen (z. B. ?v=...)
if (strpos($photo_id, '&') !== false) {
    $photo_id = substr($photo_id, 0, strpos($photo_id, '&'));
}

$rs_photo = p4c_query("SELECT `photo_albums`.`id` AS `album_id`, `photo_albums`.`storage_location`, `photo_albums_photos`.`merchant_id`, `photo_albums_photos`.`filename`
    FROM `photo_albums_photos` INNER JOIN `photo_albums` ON `photo_albums_photos`.`album_id`=`photo_albums`.`album_id` WHERE
        `file_id`='" . p4c_escape_string($photo_id) . "'
    LIMIT 1;", __FILE__, __LINE__);

if (p4c_num_rows($rs_photo) == 0) {
    $rs_album_check = p4c_query("SELECT `id` FROM `photo_albums` WHERE `album_id`='" . p4c_escape_string($photo_id) . "' LIMIT 1;", __FILE__, __LINE__);
    if (p4c_num_rows($rs_album_check) > 0) {
        $_GET['album_id'] = $photo_id;
        if ($fsk_mode !== null && !isset($_GET['fsk'])) {
            $_GET['fsk'] = $fsk_mode;
        }
        include(API_DIR . '/photo_album_poster.php');
        exit;
    }

    error_log('[EroCloud Photo Error] Foto-Eintrag nicht in Datenbank gefunden! File-ID: ' . $raw_photo_id);
    no_image();
    exit;
}

$photo_obj   = p4c_fetch_object($rs_photo);
if (!$photo_obj) {
    no_image();
    exit;
}
$album_id    = $photo_obj->album_id;
$merchant_id = $photo_obj->merchant_id;
$filename    = $photo_obj->filename;

$file_dir   = PHOTO_ALBUMS_PATH . '/' . $photo_obj->storage_location . '/' . $merchant_id . '/' . $album_id . '/images/';
$file_path  = $file_dir . $filename;
$thumb_path = $file_dir . 'thumb_' . $filename;

$context_info = array(
    'album_id'    => $album_id,
    'photo_id'    => $photo_id,
    'merchant_id' => $merchant_id,
    'filename'    => $filename
);

if (!function_exists('getRequestHeaders')) {
    function getRequestHeaders()
    {
        if (function_exists('apache_request_headers')) {
            if ($headers = apache_request_headers()) {
                return $headers;
            }
        }
        $headers = array();
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $headers['If-Modified-Since'] = $_SERVER['HTTP_IF_MODIFIED_SINCE'];
        }
        return $headers;
    }
}

$headers = getRequestHeaders();

if (!is_file($file_path)) {
    error_log("[EroCloud Photo Error] Foto-Datei existiert nicht auf dem Server! Album-ID: {$album_id}, Photo-File-ID: {$photo_id}, Merchant-ID: {$merchant_id}, Dateiname: {$filename}, Pfad: {$file_path}");
    no_image();
    exit;
} elseif (filesize($file_path) == 0) {
    error_log("[EroCloud Photo Error] Foto-Datei ist leer (0 Bytes)! Album-ID: {$album_id}, Photo-File-ID: {$photo_id}, Merchant-ID: {$merchant_id}, Dateiname: {$filename}, Pfad: {$file_path}");
    no_image();
    exit;
} else {
    $extCheck   = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $needsRegen = ec_album_thumb_needs_regeneration($file_path, $thumb_path, $extCheck);

    $width = 200;
    if (isset($_GET['w']) && !empty($_GET['w'])) {
        $width = abs((int)$_GET['w']);
    }
    if ($width > 200) {
        $width = 200;
    }
    if ($width < 50) {
        $width = 50;
    }

    $effectiveMtime = max((int)@filemtime($file_path), is_file($thumb_path) ? (int)@filemtime($thumb_path) : 0);

    if (!$needsRegen && isset($headers['If-Modified-Since']) && (strtotime((string)$headers['If-Modified-Since']) === $effectiveMtime)) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Pragma: cache');
        header('Cache-control: max-age=' . (60 * 60 * 24 * 360) . ', public');
        header('Expires: ' . gmdate(DATE_RFC1123, time() + 60 * 60 * 24 * 365));
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $effectiveMtime) . ' GMT', true, 304);
    } else {
        $success = pixelate($file_path, $thumb_path, $width, 3, 3, $context_info);
        if (!$success) {
            no_image();
        }
    }
}

p4c_close(DB_HOST);
p4c_errorlog(error_get_last());
