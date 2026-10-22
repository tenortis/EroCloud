<?php

define('SAFE_INC', 1);

header('Access-Control-Allow-Origin: *');

session_cache_limiter('none');

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");
@session_write_close();

$file_id = isset($_GET['id']) ? (string)$_GET['id'] : '';

if (strpos($file_id, '_16.') !== false) {
    $fsk = 16;
} else {
    $fsk = 18;
}

$no_pic = MCP_DIR.'/images/movie_poster_nopic.jpg';
$filename = $no_pic;

if ($file_id !== '') {
    $rs_picture = p4c_query("SELECT `id`, `merchant_id` FROM `actors` WHERE `profile_image_fsk".abs((int)$fsk)."`='".p4c_escape_string($file_id)."' LIMIT 1;",__FILE__,__LINE__);
    if ($rs_picture && p4c_num_rows($rs_picture) == 1) {
        $actor_obj = p4c_fetch_object($rs_picture);
        $candidate = PROFILE_IMAGE_PATH.'/'.MERCHANT_DEFAULT_DIR.'/'.$actor_obj->merchant_id.'/'.$actor_obj->id.'/'.$file_id;
        if (is_file($candidate)) {
            $filename = $candidate;
        }
    }
}

// Garbage Collection
@p4c_close(DB_HOST);

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
    $file = @getimagesize($bild);
    if (!$file) {
        header("Content-Length: " . filesize($bild));
        readfile($bild);
        return;
    }
    $mime_type = $file['mime'];
    $breite = $file[0];
    $hoehe = $file[1];

    if (($breite > 800 && $size > 800) || $size == 0) {
        $size = 800;
    }

    if ($size >= $breite) {
        header("Content-Length: " . filesize($bild));
        readfile($bild);
        return;
    }

    $prop = $hoehe / $breite;
    $neueBreite = (int)$size;
    $neueHoehe = (int)round($size * $prop);

    $cacheBase = (defined('TEMP_DIR') && is_dir(TEMP_DIR)) ? rtrim((string)TEMP_DIR, '/\\') : sys_get_temp_dir();
    $cacheDir  = $cacheBase . '/ec_poster_cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    $cacheFile = $cacheDir . '/pp_' . md5($bild . '|' . (int)@filemtime($bild) . '|' . $neueBreite) . '.bin';
    if (is_file($cacheFile) && filesize($cacheFile) > 0) {
        header("Content-Length: " . filesize($cacheFile));
        readfile($cacheFile);
        return;
    }

    if ($mime_type == 'image/jpeg' || $mime_type == 'image/jpg') {
        $altesBild = @ImageCreateFromJPEG($bild);
        if (!$altesBild) {
            header("Content-Length: " . filesize($bild));
            readfile($bild);
            return;
        }
        $neuesBild = imagecreatetruecolor($neueBreite, $neueHoehe);
        imagecopyresampled($neuesBild, $altesBild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $breite, $hoehe);

        ob_start();
        ImageJPEG($neuesBild, null, 88);
        $binaryData = ob_get_clean();
        if ($binaryData !== false && $binaryData !== '') {
            @file_put_contents($cacheFile, $binaryData);
        }
        header("Content-Length: " . strlen((string)$binaryData));
        echo $binaryData;
        imagedestroy($neuesBild);
        imagedestroy($altesBild);
    } elseif ($mime_type == 'image/png') {
        $altesBild = @ImageCreateFromPNG($bild);
        if (!$altesBild) {
            header("Content-Length: " . filesize($bild));
            readfile($bild);
            return;
        }
        $neuesBild = imagecreatetruecolor($neueBreite, $neueHoehe);
        imagealphablending($neuesBild, false);
        imagesavealpha($neuesBild, true);
        imagecopyresampled($neuesBild, $altesBild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $breite, $hoehe);

        ob_start();
        ImagePNG($neuesBild, null, 9);
        $binaryData = ob_get_clean();
        if ($binaryData !== false && $binaryData !== '') {
            @file_put_contents($cacheFile, $binaryData);
        }
        header("Content-Length: " . strlen((string)$binaryData));
        echo $binaryData;
        imagedestroy($neuesBild);
        imagedestroy($altesBild);
    } else {
        header("Content-Length: " . filesize($bild));
        readfile($bild);
    }
}

$headers = getRequestHeaders();

if (!is_file($filename) || filesize($filename) == 0) {
    if ($filename !== $no_pic && is_file($filename)) {
        @unlink($filename);
    }
    $filename = $no_pic;
    header("Pragma: cache");
    header('Cache-control: max-age='.(60*60*24*360).', public');
    header('Expires: '.gmdate(DATE_RFC1123, time()+60*60*24*365));
    header('Content-type: image/jpeg');

    if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($filename))) {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 304);
    } else {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 200);
        header('Content-transfer-encoding: binary');
        header('Content-length: '.filesize($filename));
        readfile($filename);
    }
} else {
    $mime_content_type = mime_content_type($filename);

    header("Pragma: cache");
    header('Cache-control: max-age='.(60*60*24*360).', public');
    header('Expires: '.gmdate(DATE_RFC1123, time()+60*60*24*365));
    header('Content-type: '.$mime_content_type);

    $width = 300;
    if (isset($_GET['w']) && !empty($_GET['w'])) {
        $width = abs((int)$_GET['w']);
    }

    if ($width > 900) { $width = 900; }
    if ($width < 50) { $width = 50; }

    if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($filename))) {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 304);
    } else {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 200);
        header('Content-transfer-encoding: binary');
        to_thumb($filename, $width);
    }
}
?>
