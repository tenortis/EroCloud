<?php

define('SAFE_INC', 1);

$session_write_close = true;

include_once("../config.inc.php");
include_once(API_DIR . "/common.inc.php");

$no_pic = is_file(MCP_DIR . '/mcp_neu/web/assets/img/movie_poster_nopic.jpg')
    ? MCP_DIR . '/mcp_neu/web/assets/img/movie_poster_nopic.jpg'
    : MCP_DIR . '/images/movie_poster_nopic.jpg';

// Bild aus EroCMS gesendet
if (isset($_GET['type']) && $_GET['type'] === 'erocms') {
    $rawUrl = isset($_GET['url']) ? (string)$_GET['url'] : '';
    $rawActorId = isset($_GET['actor_id']) ? (string)$_GET['actor_id'] : '';
    $rawFilename = isset($_GET['filename']) ? (string)$_GET['filename'] : '';
    $img_url = 'https://' . $rawUrl . '/' . $rawActorId . '/' . $rawFilename;

    if (!filter_var($img_url, FILTER_VALIDATE_URL)) {
        $img_url = $no_pic;
    }

    $file_path = @file_get_contents($img_url);
    if ($file_path === false) {
        $file_path = (string)@file_get_contents($no_pic);
        $img_url = $no_pic;
        $class_errorlog->log("Foto existiert nicht mehr:\nURL: " . $img_url . "\n" . print_r($_GET, true), __FILE__, __LINE__);
    }

    $width = 300;
    $height = 300;
    $imgInfo = @getimagesize($img_url);
    if (is_array($imgInfo) && (int)$imgInfo[0] > 0 && (int)$imgInfo[1] > 0) {
        $width = (int)round(((int)$imgInfo[0] / 100) * ($height / ((int)$imgInfo[1] / 100)));
    }

    $img_src = 'data:image/jpeg;base64,' . base64_encode((string)$file_path);
    $load = '&type=erocms&url=' . $rawUrl . '/' . $rawActorId . '/' . $rawFilename;

    if (isset($_GET['load'])) {
        $img_url = str_replace('thumb_', '', $img_url);

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($img_url));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen((string)$file_path));
        if (ob_get_level() > 0) {
            @ob_clean();
        }
        flush();
        readfile($img_url);
        exit;
    }
} else {
    // Bild aus EroCloud gesendet
    $actor_id = isset($_GET['actor_id']) ? abs((int)$_GET['actor_id']) : 0;
    $merchant_id = isset($_GET['merchant_id']) ? abs((int)$_GET['merchant_id']) : 0;
    $file_name = isset($_GET['filename']) ? (string)preg_replace('/[^a-z0-9._-]/i', '', (string)$_GET['filename']) : '';

    $file_path = PROFILE_IMAGE_PATH . '/' . MERCHANT_DEFAULT_DIR . '/' . $merchant_id . '/messenger/' . $actor_id . '/' . $file_name;
    if (!is_file($file_path)) {
        $file_path = $no_pic;
    }

    if (isset($_GET['load'])) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($file_path));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . (int)@filesize($file_path));
        if (ob_get_level() > 0) {
            @ob_clean();
        }
        flush();
        readfile($file_path);
        exit;
    }

    $width = 300;
    $height = 300;
    $imgInfo = @getimagesize($file_path);
    if (is_array($imgInfo) && (int)$imgInfo[0] > 0 && (int)$imgInfo[1] > 0) {
        $width = (int)round(((int)$imgInfo[0] / 100) * ($height / ((int)$imgInfo[1] / 100)));
    }

    $image_data = is_file($file_path) ? (string)@file_get_contents($file_path) : '';
    $img_src = 'data:image/jpeg;base64,' . base64_encode($image_data);
    $load = $merchant_id . '/' . $actor_id . '/' . $file_name;
}

header('Content-Type: text/html; charset=utf-8');

$site = '<!DOCTYPE html>
<html lang="de">
<head>
    <title>' . htmlspecialchars((string)PROJECTNAME, ENT_QUOTES, 'UTF-8') . '</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="' . MCP_URL . '/favicon.ico">
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f8fafc; color: #0f172a; max-width: 600px; }
        #head_info { background: #0284c7; color: #ffffff; font-weight: 600; padding: 10px 14px; font-size: 0.95rem; }
        .dl-link { color: #0284c7; font-weight: 600; text-decoration: none; }
        .dl-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div style="position:fixed; bottom:8px; right:10px; opacity:0.9;">
        <a href="' . MCP_URL . '" target="_blank" rel="noopener"><img src="' . MCP_URL . '/erocloud_logo.png" alt="EroCloud" style="width:80px; height:auto;" /></a>
    </div>
    <div id="head_info">Bild herunterladen</div>
    <div style="padding:14px;">
        <div style="width:' . abs((int)$width) . 'px; max-width:330px; height:' . abs((int)$height) . 'px; max-height:300px; overflow:hidden; margin:0 auto; border-radius:8px;">
            <img src="' . htmlspecialchars((string)$img_src, ENT_QUOTES, 'UTF-8') . '" style="width:100%; height:100%; object-fit: cover;" alt="" />
        </div>
        <div style="text-align:center; margin-top:15px;">
            <a class="dl-link" href="' . API_URL . '/MessengerImageInfo/' . htmlspecialchars((string)$load, ENT_QUOTES, 'UTF-8') . '&amp;load">Bild in Originalgr&ouml;&szlig;e herunterladen</a>
        </div>
    </div>
</body>
</html>
';

echo $site;

// Garbage Collection
p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());
