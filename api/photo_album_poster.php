<?php

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

if (!isset($_GET['file_id']) || empty($_GET['file_id'])) {
    header('HTTP/1.1 404 Not Found');
    echo 'album id not exists';
    exit;
}

$file_id = $_GET['file_id'];

$rs_album = p4c_query("SELECT * FROM `photo_albums` WHERE `file_id`='".p4c_escape_string($file_id)."' LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_album) == 0) {
    header('HTTP/1.1 404 Not Found');
    echo 'album id not exists';
    exit;   
}

$album_ary = p4c_fetch_object($rs_album);

$base_path = PHOTO_ALBUMS_PATH.'/'.$album_ary->storage_location.'/'.$album_ary->merchant_id.'/'.$album_ary->id.'/images/';
$filename = $base_path . 'poster.jpg';
$found_file = false;

if (file_exists($filename)) {
    $found_file = true;
} else {
    // Search first image if poster.jpg doesn't exist
    $files = glob($base_path . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
    if (!empty($files)) {
        $filename = $files[0];
        $found_file = true;
    }
}

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

function to_thumb($bild, $size = 0) {
    if (!is_file($bild) || filesize($bild) == 0) {
        $fallback = MCP_DIR.'/images/movie_poster_nopic.jpg';
        if (is_file($fallback)) {
            header('Content-type: image/jpeg');
            header('Content-length: '.filesize($fallback));
            readfile($fallback);
        }
        return;
    }

    $image_info = @getimagesize($bild);
    if (!$image_info) {
        $mime_type = @mime_content_type($bild);
        if (!$mime_type) { $mime_type = 'image/jpeg'; }
        header('Content-type: '.$mime_type);
        header('Content-length: '.filesize($bild));
        readfile($bild);
        return;
    }

    $mime_type = $image_info['mime'];
    $breite = $image_info[0];
    $hoehe = $image_info[1];

    if ($breite <= 0 || $hoehe <= 0) {
        $fallback = MCP_DIR.'/images/movie_poster_nopic.jpg';
        if (is_file($fallback)) {
            header('Content-type: image/jpeg');
            readfile($fallback);
        }
        return;
    }

    if ($size == 0 || $size > 900) {
        $size = 800;
    }

    if ($size >= $breite) {
        header('Content-type: '.$mime_type);
        header('Content-length: '.filesize($bild));
        readfile($bild);
        return;
    }

    $prop = $hoehe / $breite;
    $neueBreite = (int)$size;
    $neueHoehe = (int)round($size * $prop);

    $altesBild = null;
    if ($mime_type == 'image/jpeg' || $mime_type == 'image/jpg') {
        $image_data = @file_get_contents($bild);
        if ($image_data) {
            $altesBild = @imagecreatefromstring($image_data);
            if ($altesBild) {
                $exif = @exif_read_data($bild);
                if (!empty($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3:
                            $altesBild = @imagerotate($altesBild, 180, 0);
                            break;
                        case 6:
                            $altesBild = @imagerotate($altesBild, -90, 0);
                            break;
                        case 8:
                            $altesBild = @imagerotate($altesBild, 90, 0);
                            break;
                    }
                }
            }
        }
    } elseif ($mime_type == 'image/png') {
        $altesBild = @imagecreatefrompng($bild);
    } elseif ($mime_type == 'image/gif') {
        $altesBild = @imagecreatefromgif($bild);
    }

    if (!$altesBild) {
        header('Content-type: '.$mime_type);
        header('Content-length: '.filesize($bild));
        readfile($bild);
        return;
    }

    $neuesBild = imagecreatetruecolor($neueBreite, $neueHoehe);
    if ($mime_type == 'image/png' || $mime_type == 'image/gif') {
        imagealphablending($neuesBild, false);
        imagesavealpha($neuesBild, true);
    }

    imagecopyresampled($neuesBild, $altesBild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $breite, $hoehe);

    header('Content-type: '.$mime_type);
    ob_start();
    if ($mime_type == 'image/png') {
        imagepng($neuesBild, null, 9);
    } elseif ($mime_type == 'image/gif') {
        imagegif($neuesBild, null);
    } else {
        imagejpeg($neuesBild, null, 90);
    }
    $length = ob_get_length();
    header('Content-length: '.$length);
    ob_end_flush();

    imagedestroy($neuesBild);
    imagedestroy($altesBild);
}

$headers = getRequestHeaders();

// Wenn Datei nicht existiert oder leer ist
if (!$found_file || !is_file($filename) || filesize($filename) == 0) {
    $fallback = MCP_DIR.'/images/movie_poster_nopic.jpg';
    header('Content-type: image/jpeg');
    if (is_file($fallback)) {
        header('Content-length: '.filesize($fallback));
        readfile($fallback);
    }
} else {
    $mime_content_type = mime_content_type($filename);
    
    header("Pragma: cache");
    header('Cache-control: max-age=31536000, public');
    header('Expires: '.gmdate(DATE_RFC1123, time() + 31536000));
    header('Content-type: '.$mime_content_type);
   
    $width = 0;
    if (isset($_GET['w']) AND !empty($_GET['w'])) {
        $width = abs($_GET['w']);
    }
    
    if ($width > 900) {$width = 900;}
    if ($width < 50) {$width = 50;}
   
    if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($filename))) {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 304);
    } else {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 200);
        header('Content-transfer-encoding: binary');
        to_thumb($filename, $width);
    }
}

// Garbage Collection
p4c_close(DB_HOST);
	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>