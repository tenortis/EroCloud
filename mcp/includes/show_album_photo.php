<?php

define('SAFE_INC', 1);

session_cache_limiter('none');

include_once("../../config.inc.php");
include_once(ACP_DIR."/common.inc.php");

if (is_logged_in('mcp') === false) {
    exit;
}

if (!isset($_GET['photo_id']) || empty($_GET['photo_id'])) {
    no_image();
    exit;
}

$photo_id = $_GET['photo_id'];

$rs_photo = p4c_query("SELECT `photo_albums`.`id` AS `album_id`, `photo_albums`.`storage_location`, `photo_albums_photos`.`merchant_id`, `photo_albums_photos`.`filename`
    FROM `photo_albums_photos` INNER JOIN `photo_albums` ON `photo_albums_photos`.`album_id`=`photo_albums`.`album_id` WHERE
        `file_id`='".p4c_escape_string($photo_id)."' AND
        `photo_albums_photos`.`merchant_id`='".abs($_SESSION['merchant_id'])."'
    LIMIT 1;",__FILE__,__LINE__);

if (p4c_num_rows($rs_photo) == 0) {
    no_image();
    exit;
}

$photo_obj = p4c_fetch_object($rs_photo);

$filename = PHOTO_ALBUMS_PATH.'/'.$photo_obj->storage_location.'/'.$photo_obj->merchant_id.'/'.$photo_obj->album_id.'/images/'.$photo_obj->filename;

function no_image() {
    $filename = MCP_DIR.'/images/movie_poster_nopic.jpg';
    header("Pragma: cache");
    header('Cache-control: max-age=31536000, public');
    header('Expires: '.gmdate(DATE_RFC1123, time() + 31536000));
    header('Content-type: image/jpeg');
    if (is_file($filename)) {
        header('Content-length: '.filesize($filename));
        readfile($filename);
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
        no_image();
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
        no_image();
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

if (!is_file($filename) || filesize($filename) == 0) {
    no_image();
    exit;
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

p4c_close(DB_HOST);

p4c_errorlog(error_get_last());

?>