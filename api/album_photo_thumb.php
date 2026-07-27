<?php

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

function no_image() {
    $filename = MCP_DIR.'/images/movie_poster_nopic.jpg';
    if (is_file($filename)) {
        header('Content-type: image/jpeg');
        header('Content-transfer-encoding: binary');
        header('Content-length: '.filesize($filename));
        readfile($filename);
    }
    
    // Garbage Collection
    p4c_close(DB_HOST);
    
    // PHP Fehlermeldung loggen
    p4c_errorlog(error_get_last());
}

function pixelate($image, $thumb_path, $size = 200, $pixelate_x = 3, $pixelate_y = 3, $context_info = array()) {
    $ext = strtolower(pathinfo($image, PATHINFO_EXTENSION));

    if ($ext === "jpg" || $ext === "jpeg" || $ext === "png") {
        // Wenn noch kein Thumbnail erstellt wurde oder es zu klein/beschädigt ist
        if (!is_file($thumb_path) || filesize($thumb_path) <= 3100) {
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
            if ($ext === "jpg" || $ext === "jpeg") {
                // Check for Exif data and rotate if needed
                $exif = @exif_read_data($image);
                if (!empty($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3:
                            $im = imagerotate($im, 180, 0);
                            break;
                        case 6:
                            $im = imagerotate($im, -90, 0);
                            break;
                        case 8:
                            $im = imagerotate($im, 90, 0);
                            break;
                    }
                }
                $altesBild = $im;
                $width = imagesx($altesBild);
                $height = imagesy($altesBild);
            } elseif ($ext === "png") {
                $width = imagesx($im);
                $height = imagesy($im);
                $altesBild = imagecreatetruecolor($width, $height);
                imagecopy($altesBild, $im, 0, 0, 0, 0, $width, $height);
                imagedestroy($im);
            }

            if (!$altesBild || $width <= 0 || $height <= 0) {
                $album_info = !empty($context_info) ? "Album-ID: {$context_info['album_id']}, Photo-ID: {$context_info['photo_id']}, Pfad: {$image}" : "Pfad: {$image}";
                error_log("[EroCloud Photo Error] Ungültige Bildabmessungen! {$album_info}");
                return false;
            }

            $prop = $height / $width;
            $neueBreite = (int)$size;
            $neueHoehe = (int)round($size * $prop);

            $neuesBild = imagecreatetruecolor($neueBreite, $neueHoehe);

            imagecopyresampled($neuesBild, $altesBild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $width, $height);

            $imgW = imagesx($neuesBild);
            $imgH = imagesy($neuesBild);

            // Pixelate-Loop mit Abmessungs-Prüfung zur Vermeidung von imagecolorat Out-of-Bounds
            for ($y = 0; $y < $imgH; $y += $pixelate_y + 1) {
                for ($x = 0; $x < $imgW; $x += $pixelate_x + 1) {
                    if ($x < $imgW && $y < $imgH) {
                        $rgb = imagecolorsforindex($neuesBild, imagecolorat($neuesBild, $x, $y));
                        $color = imagecolorclosest($neuesBild, $rgb['red'], $rgb['green'], $rgb['blue']);
                        imagefilledrectangle($neuesBild, $x, $y, min($x + $pixelate_x, $imgW - 1), min($y + $pixelate_y, $imgH - 1), $color);
                    }
                }
            }

            if ($ext === "jpg" || $ext === "jpeg") {
                imagejpeg($neuesBild, $thumb_path, 100);
            } elseif ($ext === "png") {
                imagepng($neuesBild, $thumb_path);
            }

            imagedestroy($neuesBild);
            imagedestroy($altesBild);
        }

        if (is_file($thumb_path)) {
            $mime_content_type = mime_content_type($thumb_path);
            header('Content-type: ' . $mime_content_type);
            header("Content-Length: " . filesize($thumb_path));

            if ($ext === "jpg" || $ext === "jpeg") {
                readfile($thumb_path);
            } elseif ($ext === "png") {
                $fp = fopen($thumb_path, 'rb');
                if ($fp) {
                    header("Content-Type: image/png");
                    header("Content-Length: " . filesize($thumb_path));
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
    error_log("[EroCloud Photo Error] Aufruf ohne photo_id Parameter.");
    no_image();
    exit;
}

$photo_id = $_GET['photo_id'];

$rs_photo = p4c_query("SELECT `photo_albums`.`id` AS `album_id`, `photo_albums`.`storage_location`, `photo_albums_photos`.`merchant_id`, `photo_albums_photos`.`filename`
    FROM `photo_albums_photos` INNER JOIN `photo_albums` ON `photo_albums_photos`.`album_id`=`photo_albums`.`album_id` WHERE
        `file_id`='".p4c_escape_string($photo_id)."'
    LIMIT 1;",__FILE__,__LINE__);

if (p4c_num_rows($rs_photo) == 0) {
    error_log("[EroCloud Photo Error] Foto-Eintrag nicht in Datenbank gefunden! File-ID: ".$photo_id);
    no_image();
    exit;
}

$photo_obj = p4c_fetch_object($rs_photo);
$album_id   = $photo_obj->album_id;
$merchant_id= $photo_obj->merchant_id;
$filename   = $photo_obj->filename;

$file_dir   = PHOTO_ALBUMS_PATH.'/'.$photo_obj->storage_location.'/'.$merchant_id.'/'.$album_id.'/images/';
$file_path  = $file_dir.$filename;
$thumb_path = $file_dir.'thumb_'.$filename;

$context_info = array(
    'album_id' => $album_id,
    'photo_id' => $photo_id,
    'merchant_id' => $merchant_id,
    'filename' => $filename
);

function getRequestHeaders() {
    if (function_exists("apache_request_headers")) {
        if($headers = apache_request_headers()) {
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

// Wenn Datei auf der Festplatte fehlt
if (!is_file($file_path)) {
    error_log("[EroCloud Photo Error] Foto-Datei existiert nicht auf dem Server! Album-ID: {$album_id}, Photo-File-ID: {$photo_id}, Merchant-ID: {$merchant_id}, Dateiname: {$filename}, Pfad: {$file_path}");
    no_image();
    exit;

// Wenn Datei leer ist (0 Bytes)
} elseif (filesize($file_path) == 0) {
    error_log("[EroCloud Photo Error] Foto-Datei ist leer (0 Bytes)! Album-ID: {$album_id}, Photo-File-ID: {$photo_id}, Merchant-ID: {$merchant_id}, Dateiname: {$filename}, Pfad: {$file_path}");
    no_image();
    exit;
} else {
    $mime_content_type = mime_content_type($file_path);
    
    header("Pragma: cache");
    header('Cache-control: max-age='.(60*60*24*360).', public');
    header('Expires: '.gmdate(DATE_RFC1123,time()+60*60*24*365));
    header('Content-type: '.$mime_content_type);
   
    $width = 200;
    if (isset($_GET['w']) AND !empty($_GET['w'])) {
        $width = abs($_GET['w']);
    }

    if ($width > 200) {$width = 200;}
    if ($width < 50) {$width = 50;}
    
    if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($file_path))) {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file_path)).' GMT', true, 304);
    } else {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file_path)).' GMT', true, 200);
        header('Content-transfer-encoding: binary');
        $success = pixelate($file_path, $thumb_path, $width, 3, 3, $context_info);
        if (!$success) {
            no_image();
        }
    }
}

// Garbage Collection
p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>