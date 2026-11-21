<?php

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR . "/common.inc.php");
@session_write_close();

function no_image()
{
    $filename = MCP_DIR . '/images/movie_poster_nopic.jpg';
    if (is_file($filename)) {
        header('Content-type: image/jpeg');
        header('Content-transfer-encoding: binary');
        header('Content-length: ' . filesize($filename));
        readfile($filename);
    }

    p4c_close(DB_HOST);
    p4c_errorlog(error_get_last());
}

/**
 * Prüft, ob das verpixelte Vorschaubild (thumb_{filename}) neu generiert werden muss:
 *  1. Datei fehlt oder ist zu klein/beschädigt (<= 3100 Bytes)
 *  2. Das Originalbild wurde nach der Erstellung des Thumbnails geändert/gedreht (filemtime)
 *  3. Altbestand-Selbstheilung: Das Original-JPEG besitzt ein EXIF-Orientation-Tag (6/8 = 90° Hochformat),
 *     aber ein altes Thumbnail auf Disk hat noch das ungedrehte Sensor-Seitenverhältnis (oder Orientation=3 vor 2024).
 */
function ec_album_thumb_needs_regeneration($image, $thumb_path, $ext)
{
    if (!is_file($thumb_path) || (int)@filesize($thumb_path) <= 3100) {
        return true;
    }

    $origMtime  = (int)@filemtime($image);
    $thumbMtime = (int)@filemtime($thumb_path);
    if ($origMtime > 0 && $thumbMtime < $origMtime) {
        return true;
    }

    if (($ext === 'jpg' || $ext === 'jpeg') && function_exists('exif_read_data')) {
        $exif = @exif_read_data($image);
        if (is_array($exif) && !empty($exif['Orientation'])) {
            $orient = (int)$exif['Orientation'];
            if ($orient === 6 || $orient === 8) {
                $origSize  = @getimagesize($image);
                $thumbSize = @getimagesize($thumb_path);
                if (is_array($origSize) && is_array($thumbSize) && $origSize[0] > 0 && $origSize[1] > 0 && $thumbSize[0] > 0 && $thumbSize[1] > 0) {
                    // Nach 90°-EXIF-Drehung muss Hoch-/Querformat gegenüber den rohen Sensor-Pixeln getauscht sein
                    $origIsLandscape  = ($origSize[0] > $origSize[1]);
                    $origIsPortrait   = ($origSize[1] > $origSize[0]);
                    $thumbIsLandscape = ($thumbSize[0] > $thumbSize[1]);
                    $thumbIsPortrait  = ($thumbSize[1] > $thumbSize[0]);
                    if (($origIsLandscape && $thumbIsLandscape) || ($origIsPortrait && $thumbIsPortrait)) {
                        return true;
                    }
                }
            } elseif ($orient === 3 && $thumbMtime > 0 && $thumbMtime < 1704067200) {
                return true;
            }
        }
    }

    return false;
}

function pixelate($image, $thumb_path, $size = 200, $pixelate_x = 3, $pixelate_y = 3, $context_info = array())
{
    $ext = strtolower(pathinfo($image, PATHINFO_EXTENSION));

    if ($ext === 'jpg' || $ext === 'jpeg' || $ext === 'png') {
        if (ec_album_thumb_needs_regeneration($image, $thumb_path, $ext)) {
            $image_data = @file_get_contents($image);
            if ($image_data === false || empty($image_data)) {
                $album_info = !empty($context_info) ? "Album-ID: {$context_info['album_id']}, Photo-ID: {$context_info['photo_id']}, Pfad: {$image}" : "Pfad: {$image}";
                error_log("[EroCloud Photo Error] Konnte Bilddaten nicht lesen! {$album_info}");
                return false;
            }

            $im = @imagecreatefromstring($image_data);
            if (!$im) {
                $album_info = !empty($context_info) ? "Album-ID: {$context_info['album_id']}, Photo-ID: {$context_info['photo_id']}, Pfad: {$image}" : "Pfad: {$image}";
                error_log("[EroCloud Photo Error] GD imagecreatefromstring fehlgeschlagen (Bild beschädigt)! {$album_info}");
                return false;
            }

            $altesBild = null;
            $width     = 0;
            $height    = 0;
            if ($ext === 'jpg' || $ext === 'jpeg') {
                if (function_exists('exif_read_data')) {
                    $exif = @exif_read_data($image);
                    if (is_array($exif) && !empty($exif['Orientation'])) {
                        switch ((int)$exif['Orientation']) {
                            case 3:
                                $rot = @imagerotate($im, 180, 0);
                                if ($rot) {
                                    $im = $rot;
                                }
                                break;
                            case 6:
                                $rot = @imagerotate($im, -90, 0);
                                if ($rot) {
                                    $im = $rot;
                                }
                                break;
                            case 8:
                                $rot = @imagerotate($im, 90, 0);
                                if ($rot) {
                                    $im = $rot;
                                }
                                break;
                        }
                    }
                }
                $altesBild = $im;
                $width     = imagesx($altesBild);
                $height    = imagesy($altesBild);
            } elseif ($ext === 'png') {
                $width     = imagesx($im);
                $height    = imagesy($im);
                $altesBild = imagecreatetruecolor($width, $height);
                imagecopy($altesBild, $im, 0, 0, 0, 0, $width, $height);
                unset($im);
            }

            if (!$altesBild || $width <= 0 || $height <= 0) {
                $album_info = !empty($context_info) ? "Album-ID: {$context_info['album_id']}, Photo-ID: {$context_info['photo_id']}, Pfad: {$image}" : "Pfad: {$image}";
                error_log("[EroCloud Photo Error] Ungültige Bildabmessungen! {$album_info}");
                return false;
            }

            $prop        = $height / $width;
            $neueBreite  = (int)$size;
            $neueHoehe   = max(1, (int)round($size * $prop));
            $neuesBild   = imagecreatetruecolor($neueBreite, $neueHoehe);

            imagecopyresampled($neuesBild, $altesBild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $width, $height);

            $imgW = imagesx($neuesBild);
            $imgH = imagesy($neuesBild);

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

            if ($ext === 'jpg' || $ext === 'jpeg') {
                @imagejpeg($neuesBild, $thumb_path, 95);
            } elseif ($ext === 'png') {
                @imagepng($neuesBild, $thumb_path);
            }

            @touch($thumb_path, time());
            clearstatcache(true, $thumb_path);
            unset($neuesBild, $altesBild);
        }

        if (is_file($thumb_path)) {
            $mime_content_type = mime_content_type($thumb_path);
            header('Content-type: ' . $mime_content_type);
            header('Content-Length: ' . filesize($thumb_path));

            if ($ext === 'jpg' || $ext === 'jpeg') {
                readfile($thumb_path);
            } elseif ($ext === 'png') {
                $fp = fopen($thumb_path, 'rb');
                if ($fp) {
                    header('Content-Type: image/png');
                    header('Content-Length: ' . filesize($thumb_path));
                    fpassthru($fp);
                    fclose($fp);
                } else {
                    readfile($thumb_path);
                }
            }
            return true;
        }
    }
    return false;
}

if (!isset($_GET['photo_id']) || empty($_GET['photo_id'])) {
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
    $extCheck  = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $needsRegen = ec_album_thumb_needs_regeneration($file_path, $thumb_path, $extCheck);

    $mime_content_type = mime_content_type($file_path);

    header('Pragma: cache');
    header('Cache-control: max-age=' . (60 * 60 * 24 * 360) . ', public');
    header('Expires: ' . gmdate(DATE_RFC1123, time() + 60 * 60 * 24 * 365));
    header('Content-type: ' . $mime_content_type);

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
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $effectiveMtime) . ' GMT', true, 304);
    } else {
        header('Content-transfer-encoding: binary');
        $success = pixelate($file_path, $thumb_path, $width, 3, 3, $context_info);
        if (!$success) {
            no_image();
        } else {
            $newMtime = max((int)@filemtime($file_path), is_file($thumb_path) ? (int)@filemtime($thumb_path) : time());
            if (!headers_sent()) {
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $newMtime) . ' GMT', true, 200);
            }
        }
    }
}

p4c_close(DB_HOST);
p4c_errorlog(error_get_last());
