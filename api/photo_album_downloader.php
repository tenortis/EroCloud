<?php

/**
 * Fotoalbum Downloader (api.erocloud.net)
 */

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR . "/common.inc.php");

$api = array();

if (!isset($_GET['user_key']) || !is_string($_GET['user_key'])) {
    $api['error'] = 'user_key not exists';
    print_xml($api);
}

if (!isset($_GET['album_id']) || !is_string($_GET['album_id'])) {
    $api['error'] = 'album_id not exists';
    print_xml($api);
}

if (!isset($_GET['access_token']) || !is_string($_GET['access_token'])) {
    $api['error'] = 'access denid 1';
    print_xml($api);
}

$user_key         = trim((string)$_GET['user_key']);
$album_id_raw     = trim((string)$_GET['album_id']);
$access_token_raw = trim((string)$_GET['access_token']);
if (strpos($access_token_raw, '&') !== false) {
    $access_token_raw = explode('&', $access_token_raw, 2)[0];
}
if (strpos($access_token_raw, '?') !== false) {
    $access_token_raw = explode('?', $access_token_raw, 2)[0];
}

// Prüfen, ob der user_key existiert
$rs_user_key = p4c_query("SELECT * FROM `photo_albums_access` WHERE `user_key` = '" . p4c_escape_string($user_key) . "' LIMIT 1;", __FILE__, __LINE__);
if (p4c_num_rows($rs_user_key) == 0) {
    $api['error'] = 'access denid 2';
    print_xml($api);
}

$access_obj = p4c_fetch_object($rs_user_key);
if (!$access_obj) {
    $api['error'] = 'access denid 2';
    print_xml($api);
}

// Wenn das Accesstoken nicht korrekt oder abgelaufen ist
if ((string)$access_obj->access_token !== $access_token_raw || (string)($access_obj->access_token_datetime ?? '') < date("Y-m-d H:i:s")) {
    $api['error'] = 'access denid 3';
    print_xml($api);
}

$rs_album = p4c_query("SELECT * FROM `photo_albums` WHERE `album_id`='" . p4c_escape_string($album_id_raw) . "' LIMIT 1;", __FILE__, __LINE__);
if (p4c_num_rows($rs_album) == 0) {
    $api['error'] = 'album_id not exists';
    print_xml($api);
}

$album_ary = p4c_fetch_object($rs_album);
if (!$album_ary) {
    $api['error'] = 'album_id not exists';
    print_xml($api);
}
$albumTitleRaw = (string)($album_ary->title ?? ($album_ary->name ?? 'Fotoalbum'));

$album_dir = PROFILE_IMAGE_PATH . '/' . $album_ary->storage_location . '/' . $album_ary->merchant_id . '/' . $album_ary->id;
if (!class_exists('ZipArchive') || !is_dir($album_dir)) {
    $api['error'] = 'cannot open zipfile';
    print_xml($api);
}

if (!isset($_GET['start']) || (string)$_GET['start'] !== 'download') {
    $safeUserKey     = rawurlencode($user_key);
    $safeAlbumId     = rawurlencode($album_id_raw);
    $safeAccessToken = rawurlencode($access_token_raw);
    $safeTitle       = htmlspecialchars($albumTitleRaw, ENT_QUOTES, 'UTF-8');
    $downloadUrl     = API_URL . '/PhotoAlbumDownloader/' . $safeUserKey . '/' . $safeAlbumId . '/' . $safeAccessToken . '?start=download';
    $safeDownloadUrl = htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8');

    header('Content-Type: text/html; charset=utf-8');
    header('Refresh: 2; url=' . $downloadUrl);

    $site = '<!DOCTYPE html>
<html lang="de">
<head>
    <title>' . htmlspecialchars((string)PROJECTNAME, ENT_QUOTES, 'UTF-8') . '</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="2; url=' . $safeDownloadUrl . '">
    <link rel="icon" type="image/x-icon" href="' . MCP_URL . '/favicon.ico">
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f8fafc; color: #0f172a; }
        #head_info { background: #0284c7; color: #ffffff; font-weight: 600; padding: 10px 14px; font-size: 0.95rem; }
        .dl-body { padding: 16px 14px; font-size: 0.92rem; line-height: 1.5; }
        .dl-btn { display: inline-block; margin-top: 14px; padding: 8px 16px; background: #0284c7; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.88rem; }
        .dl-btn:hover { background: #0369a1; }
        .dl-logo { position: fixed; bottom: 8px; right: 10px; opacity: 0.9; }
    </style>
    <script>
        setTimeout(function() {
            var el = document.getElementById("dl_info");
            if (el) { el.textContent = "Das Album wird heruntergeladen..."; }
        }, 1800);
    </script>
</head>
<body>
    <div id="head_info">' . $safeTitle . '</div>
    <div class="dl-body">
        <div id="dl_info">Dein ZIP-Archiv wird vorbereitet und der Download startet in wenigen Sekunden...</div>
        <a class="dl-btn" href="' . $safeDownloadUrl . '">Jetzt direkt herunterladen</a>
    </div>
    <div class="dl-logo">
        <a href="' . MCP_URL . '" target="_blank" rel="noopener"><img src="' . MCP_URL . '/erocloud_logo.png" alt="EroCloud" style="width:80px; height:auto;" /></a>
    </div>
</body>
</html>';

    echo $site;
    p4c_close(DB_HOST);
    p4c_errorlog(error_get_last());
    exit;
}

$rs_photos = p4c_query("SELECT * FROM `photo_albums_photos` WHERE `album_id`='" . p4c_escape_string($album_id_raw) . "' AND `rejected`='0';", __FILE__, __LINE__);

$tmpBaseDir = (defined('TEMP_DIR') && is_dir(TEMP_DIR) && is_writable(TEMP_DIR)) ? rtrim((string)TEMP_DIR, '/') : $album_dir;
$zip_file   = $tmpBaseDir . '/album_' . abs((int)$album_ary->id) . '_' . randomString(12) . '.zip';

register_shutdown_function(static function () use ($zip_file): void {
    if (is_file($zip_file)) {
        @unlink($zip_file);
    }
});

$zip = new ZipArchive();
if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
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
if ($zip->close() !== true || !is_file($zip_file)) {
    $api['error'] = 'cannot create zipfile';
    print_xml($api);
}

clearstatcache(true, $zip_file);
$rawSize = @filesize($zip_file);
$size    = ($rawSize !== false && $rawSize >= 0) ? (int)$rawSize : 0;

$dlSlug = seo_url($albumTitleRaw);
if ($dlSlug === '') {
    $dlSlug = 'album_' . abs((int)$album_ary->id);
}

if (session_status() === PHP_SESSION_ACTIVE) {
    @session_write_close();
}
p4c_close(DB_HOST);
p4c_errorlog(error_get_last());

$handle = @fopen($zip_file, 'rb');
if ($handle === false) {
    @unlink($zip_file);
    http_response_code(500);
    exit('ZIP-Archiv konnte nicht gelesen werden.');
}

$chunk = 524288; // 512 KB
if (function_exists('stream_set_read_buffer')) {
    @stream_set_read_buffer($handle, 0);
}
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) {
    @ob_end_clean();
}
if (function_exists('header_remove')) {
    @header_remove('Pragma');
    @header_remove('Expires');
    @header_remove('Cache-Control');
}

header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, must-revalidate, max-age=0');
header('X-Accel-Buffering: no');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $dlSlug . '.zip"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . $size);

@set_time_limit(0);
$bytesRemaining = $size;
while ($bytesRemaining > 0 && !feof($handle)) {
    if (connection_aborted() || connection_status() !== CONNECTION_NORMAL) {
        break;
    }
    $bytesToRead = ($bytesRemaining > $chunk) ? $chunk : $bytesRemaining;
    $contents    = @fread($handle, $bytesToRead);
    if ($contents === false || $contents === '') {
        break;
    }
    $bytesRemaining -= strlen($contents);
    echo $contents;
    flush();
}
@fclose($handle);
@unlink($zip_file);
exit;
