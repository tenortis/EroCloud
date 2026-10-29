<?php

/**
 * Fotoalbum Downloader (api.erocloud.net)
 */

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR . "/common.inc.php");

if (!isset($_GET['user_key'])) {
    $api['error'] = 'user_key not exists';
    print_xml($api);
}

if (!isset($_GET['album_id'])) {
    $api['error'] = 'album_id not exists';
    print_xml($api);
}

if (!isset($_GET['access_token'])) {
    $api['error'] = 'access denid 1';
    print_xml($api);
}

$user_key = (string)$_GET['user_key'];

// Prüfen, ob der user_key existiert
$rs_user_key = p4c_query("SELECT * FROM `photo_albums_access` WHERE `user_key` = '" . p4c_escape_string($user_key) . "' LIMIT 1;", __FILE__, __LINE__);
if (p4c_num_rows($rs_user_key) == 0) {
    $api['error'] = 'access denid 2';
    print_xml($api);
}

$access_obj = p4c_fetch_object($rs_user_key);

// Wenn das Accesstoken nicht korrekt oder abgelaufen ist
if ($access_obj->access_token != $_GET['access_token'] || $access_obj->access_token_datetime < date("Y-m-d H:i:s")) {
    $api['error'] = 'access denid 3';
    print_xml($api);
}

$album_id = p4c_escape_string((string)$_GET['album_id']);

$rs_album = p4c_query("SELECT * FROM `photo_albums` WHERE `album_id`='" . $album_id . "' LIMIT 1;", __FILE__, __LINE__);
if (p4c_num_rows($rs_album) == 0) {
    $api['error'] = 'album_id not exists';
    print_xml($api);
}

$album_ary = p4c_fetch_object($rs_album);
$albumTitleRaw = (string)($album_ary->title ?? ($album_ary->name ?? 'Fotoalbum'));

$rs_photos = p4c_query("SELECT * FROM `photo_albums_photos` WHERE `album_id`='" . p4c_escape_string($album_id) . "' AND `rejected`='0';", __FILE__, __LINE__);

$album_dir = PROFILE_IMAGE_PATH . '/' . $album_ary->storage_location . '/' . $album_ary->merchant_id . '/' . $album_ary->id;
$zip_file = $album_dir . '/album.zip';

if (is_file($zip_file)) {
    @unlink($zip_file);
}

$zip = new ZipArchive();

if ($zip->open($zip_file, ZipArchive::CREATE) !== true) {
    $api['error'] = 'cannot open zipfile';
    print_xml($api);
}

$zip->addFromString((string)($album_ary->seo_url ?? 'album') . '.txt', strip_tags((string)($album_ary->description ?? '')));
while ($photo_ary = p4c_fetch_object($rs_photos)) {
    $photoPath = $album_dir . '/images/' . $photo_ary->filename;
    if (is_file($photoPath)) {
        $zip->addFile($photoPath, (string)$photo_ary->filename);
    }
}
$zip->close();

$size = is_file($zip_file) ? (int)filesize($zip_file) : 0;

if (isset($_GET['start']) && $_GET['start'] == 'download') {
    session_write_close();

    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Cache-Control: private', false);
    header('Content-Type: application/zip');
    header('Content-Type: application/force-download');
    header('Content-Type: application/download');
    header('Content-Disposition: attachment; filename="' . seo_url($albumTitleRaw) . '.zip"');
    header('Content-Description: File Transfer');
    header('Content-Length: ' . $size);

    $bis = 1024 * 1024;
    $count = (int)ceil($size / max(1, $bis));
    $handle = fopen($zip_file, "rb");
    if ($handle !== false) {
        for ($i = 0; $i <= $count; $i++) {
            $ftell = ftell($handle) + $bis;
            $contents = fread($handle, $bis);
            if ($contents === false || $contents === '') {
                break;
            }
            fseek($handle, $ftell);
            echo $contents;
        }
        fclose($handle);
    }
} else {
    $safeUserKey = rawurlencode($user_key);
    $safeAlbumId = rawurlencode((string)$_GET['album_id']);
    $safeAccessToken = rawurlencode((string)$_GET['access_token']);
    $safeTitle = htmlspecialchars($albumTitleRaw, ENT_QUOTES, 'UTF-8');

    header('Content-Type: text/html; charset=utf-8');
    header('refresh:5; ' . API_URL . '/PhotoAlbumDownloader/' . $safeUserKey . '/' . $safeAlbumId . '/' . $safeAccessToken . '&start=download');

    $site = '<!DOCTYPE html>
<html lang="de">
<head>
    <title>' . htmlspecialchars((string)PROJECTNAME, ENT_QUOTES, 'UTF-8') . '</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="' . MCP_URL . '/favicon.ico">
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f8fafc; color: #0f172a; }
        #head_info { background: #0284c7; color: #ffffff; font-weight: 600; padding: 10px 14px; font-size: 0.95rem; }
        .dl-body { padding: 16px 14px; font-size: 0.92rem; line-height: 1.5; }
        .dl-meta { color: #64748b; font-size: 0.85rem; margin-top: 6px; }
        .dl-logo { position: fixed; bottom: 8px; right: 10px; opacity: 0.9; }
    </style>
    <script>
        setTimeout(function() {
            var el = document.getElementById("dl_info");
            if (el) { el.textContent = "Das Album wird heruntergeladen..."; }
        }, 4000);
    </script>
</head>
<body>
    <div id="head_info">' . $safeTitle . '</div>
    <div class="dl-body">
        <div id="dl_info">Der Download startet in wenigen Sekunden...</div>
        <div class="dl-meta">Dateigr&ouml;&szlig;e: ' . htmlspecialchars((string)convertBytes($size), ENT_QUOTES, 'UTF-8') . '</div>
    </div>
    <div class="dl-logo">
        <a href="' . MCP_URL . '" target="_blank" rel="noopener"><img src="' . MCP_URL . '/erocloud_logo.png" alt="EroCloud" style="width:80px; height:auto;" /></a>
    </div>
</body>
</html>';

    echo $site;
}

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());
