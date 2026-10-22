<?php

define('SAFE_INC', 1);

session_cache_limiter('none');

include_once("../../config.inc.php");
include_once(ACP_DIR."/common.inc.php");

if (is_logged_in('mcp') === false) {
    @session_write_close();
    exit;
}

$session_merchant_id = abs((int)($_SESSION['merchant_id'] ?? 0));
// Session-Lock sofort freigeben, damit 50+ parallele Profilbild-Abrufe niemals andere Requests blockieren
@session_write_close();

$file_id = (string)($_GET['id'] ?? '');

if (strpos($file_id, '_16.') !== false) {
    $fsk = 16;
} else {
    $fsk = 18;
}

$no_pic = MCP_DIR.'/images/movie_poster_nopic.jpg';
$filename = $no_pic;

$rs_picture = p4c_query("SELECT `id`, `merchant_id` FROM `actors` WHERE `profile_image_fsk".abs((int)$fsk)."`='".p4c_escape_string($file_id)."' AND `merchant_id`='".$session_merchant_id."' LIMIT 1;", __FILE__, __LINE__);
if ($rs_picture && p4c_num_rows($rs_picture) == 1) {
    $actor_obj = p4c_fetch_object($rs_picture);
    $candidate = PROFILE_IMAGE_PATH.'/'.MERCHANT_DEFAULT_DIR.'/'.$actor_obj->merchant_id.'/'.$actor_obj->id.'/'.$file_id;
    if (is_file($candidate)) {
        $filename = $candidate;
    }
}

function getRequestHeaders() {
    if (function_exists("apache_request_headers")) {
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

function to_thumb($bild, $size = 0) {
    $file = @getimagesize($bild);
    if (!$file) {
        return;
    }
    $mime_type = $file['mime'];
    $breite = (int)$file[0];
    $hoehe = (int)$file[1];
    if ($breite <= 0 || $hoehe <= 0) {
        return;
    }

    if (($breite > 800 && $size > 800) || $size == 0) {
        $size = 800;
    }

    $prop = $hoehe / $breite;
    $neueBreite = max(1, (int)round($size));
    $neueHoehe  = max(1, (int)round($size * $prop));

    $cacheBase = (defined('TEMP_DIR') && is_dir(TEMP_DIR)) ? rtrim((string)TEMP_DIR, '/\\') : sys_get_temp_dir();
    $cacheDir  = $cacheBase . '/ec_poster_cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    $cacheFile = $cacheDir . '/mcppp_' . md5($bild . '|' . (int)@filemtime($bild) . '|' . $neueBreite) . '.bin';
    if (is_file($cacheFile) && filesize($cacheFile) > 0) {
        header("Content-Length: " . filesize($cacheFile));
        readfile($cacheFile);
        return;
    }

    if ($mime_type == 'image/jpeg') {
        $altesBild = @ImageCreateFromJPEG($bild);
        if (!$altesBild) {
            return;
        }
        $neuesBild = imagecreatetruecolor($neueBreite, $neueHoehe);
        imagecopyresampled($neuesBild, $altesBild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $breite, $hoehe);

        ob_start();
        ImageJPEG($neuesBild, null, 85);
        $binaryData = ob_get_clean();
        if ($binaryData !== false && $binaryData !== '') {
            @file_put_contents($cacheFile, $binaryData);
        }
        header("Content-Length: " . strlen((string)$binaryData));
        echo $binaryData;
        if (PHP_VERSION_ID < 80000) {
            @imagedestroy($altesBild);
            @imagedestroy($neuesBild);
        }
    } elseif ($mime_type == 'image/png') {
        $altesBild = @ImageCreateFromPNG($bild);
        if (!$altesBild) {
            return;
        }
        $neuesBild = imagecreatetruecolor($neueBreite, $neueHoehe);
        imagealphablending($neuesBild, false);
        imagesavealpha($neuesBild, true);
        imagecopyresampled($neuesBild, $altesBild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $breite, $hoehe);

        ob_start();
        ImagePNG($neuesBild, null);
        $binaryData = ob_get_clean();
        if ($binaryData !== false && $binaryData !== '') {
            @file_put_contents($cacheFile, $binaryData);
        }
        header("Content-Length: " . strlen((string)$binaryData));
        echo $binaryData;
        if (PHP_VERSION_ID < 80000) {
            @imagedestroy($altesBild);
            @imagedestroy($neuesBild);
        }
    }
}

$headers = getRequestHeaders();
$mtime   = @filemtime($filename) ?: time();

header('Cache-Control: private, max-age=86400');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

if (isset($headers['If-Modified-Since']) && strtotime((string)$headers['If-Modified-Since']) === $mtime) {
    http_response_code(304);
    p4c_close(DB_HOST);
    exit;
}

if (@filesize($filename) == 0) {
    @unlink($filename);
    $filename = $no_pic;
    header('Content-type: image/jpeg');
    header('Content-length: ' . @filesize($filename));
    readfile($filename);
} else {
    $mime_content_type = @mime_content_type($filename) ?: 'image/jpeg';
    header('Content-type: ' . $mime_content_type);

    $width = 300;
    if (isset($_GET['w']) && !empty($_GET['w'])) {
        $width = abs((int)$_GET['w']);
    }

    if ($width > 900) { $width = 900; }
    if ($width < 40)  { $width = 40; }

    header('Content-transfer-encoding: binary');
    to_thumb($filename, $width);
}

p4c_close(DB_HOST);
p4c_errorlog(error_get_last());
?>
