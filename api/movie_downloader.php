<?php

/**
 * Movie Downloader (api.erocloud.net)
 */

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR . "/common.inc.php");

$api = array();

if (!isset($_GET['user_streaming_key']) || !is_string($_GET['user_streaming_key'])) {
    $api['error'] = 'key not exists';
    print_xml($api);
}

if (!isset($_GET['movie_id']) || !is_string($_GET['movie_id'])) {
    $api['error'] = 'movie_id not exists';
    print_xml($api);
}

if (!isset($_GET['access_token']) || !is_string($_GET['access_token'])) {
    $api['error'] = 'access denid';
    print_xml($api);
}

$user_streaming_key = trim((string)$_GET['user_streaming_key']);
$movie_id_raw       = trim((string)$_GET['movie_id']);
$access_token_raw   = trim((string)$_GET['access_token']);
if (strpos($access_token_raw, '&') !== false) {
    $access_token_raw = explode('&', $access_token_raw, 2)[0];
}
if (strpos($access_token_raw, '?') !== false) {
    $access_token_raw = explode('?', $access_token_raw, 2)[0];
}

// Prüfen, ob der user_streaming_key existiert
$rs_user_streaming_key = p4c_query("SELECT * FROM `movies_access` WHERE `user_streaming_key` = '" . p4c_escape_string($user_streaming_key) . "' LIMIT 1;", __FILE__, __LINE__);
if (p4c_num_rows($rs_user_streaming_key) == 0) {
    $api['error'] = 'access denid';
    print_xml($api);
}

$access_obj = p4c_fetch_object($rs_user_streaming_key);
if (!$access_obj) {
    $api['error'] = 'access denid';
    print_xml($api);
}

// Wenn das Accesstoken nicht korrekt oder abgelaufen ist
if ((string)$access_obj->access_token !== $access_token_raw || (string)($access_obj->access_token_datetime ?? '') < date("Y-m-d H:i:s")) {
    $api['error'] = 'access denid';
    print_xml($api);
}

$movie_id = p4c_escape_string($movie_id_raw);

$rs_movie = p4c_query("SELECT * FROM `movies` WHERE `file_id`='" . $movie_id . "' LIMIT 1;", __FILE__, __LINE__);
if (p4c_num_rows($rs_movie) == 0) {
    $api['error'] = 'movie_id not exists';
    print_xml($api);
}

$movie_ary = p4c_fetch_object($rs_movie);
if (!$movie_ary) {
    $api['error'] = 'movie_id not exists';
    print_xml($api);
}

$file = MOVIES_PATH . '/' . $movie_ary->storage_location . '/' . $movie_ary->merchant_id . '/' . $movie_ary->id . '/' . $movie_ary->filename;

if (!is_file($file) || !is_readable($file)) {
    $class_errorlog->log("Ein Film konnte nicht heruntergeladen werden!\n" . $file, __FILE__, __LINE__);
    p4c_close(DB_HOST);
    p4c_errorlog(error_get_last());
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo 'Es ist ein Fehler aufgetreten. Bitte versuche es sp&auml;ter noch einmal.';
    exit;
}

clearstatcache(true, $file);
$rawSize = @filesize($file);
$size    = ($rawSize !== false && $rawSize >= 0) ? (int)$rawSize : 0;

if (isset($_GET['start']) && (string)$_GET['start'] === 'download') {
    $dlSlug = seo_url((string)($movie_ary->title ?? 'video'));
    if ($dlSlug === '') {
        $dlSlug = 'video_' . abs((int)($movie_ary->id ?? 0));
    }
    $downloadName = $dlSlug . '.mp4';
    $rawMtime     = @filemtime($file);
    $mtime        = ($rawMtime !== false && (int)$rawMtime > 0) ? (int)$rawMtime : time();

    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }
    p4c_close(DB_HOST);
    p4c_errorlog(error_get_last());

    $handle = @fopen($file, 'rb');
    if ($handle === false) {
        http_response_code(500);
        exit('Datei konnte nicht geöffnet werden.');
    }

    $chunk = 524288; // 512 KB
    if (function_exists('stream_set_read_buffer')) {
        @stream_set_read_buffer($handle, 0);
    }
    if (function_exists('stream_set_chunk_size')) {
        @stream_set_chunk_size($handle, $chunk);
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

    $lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
    $etag         = '"' . sprintf('%x-%x', $mtime, $size) . '"';

    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, must-revalidate, max-age=0');
    header('Last-Modified: ' . $lastModified);
    header('ETag: ' . $etag);
    header('Accept-Ranges: bytes');
    header('X-Accel-Buffering: no');
    header('Content-Type: video/mp4');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Transfer-Encoding: binary');

    $start = 0;
    $end   = ($size > 0) ? ($size - 1) : 0;

    $httpRange = isset($_SERVER['HTTP_RANGE']) ? trim((string)$_SERVER['HTTP_RANGE']) : '';
    if ($httpRange !== '' && isset($_SERVER['HTTP_IF_RANGE'])) {
        $ifRange = trim((string)$_SERVER['HTTP_IF_RANGE']);
        $etagHex = trim($etag, '"');
        if ($ifRange !== '' && $ifRange !== $etag && $ifRange !== $lastModified && strpos($ifRange, $etagHex) === false) {
            $httpRange = '';
        }
    }

    if ($httpRange !== '' && stripos($httpRange, 'bytes=') === 0) {
        $rangeSpec = substr($httpRange, 6);
        if (strpos($rangeSpec, ',') !== false) {
            $rangeSpec = trim(explode(',', $rangeSpec, 2)[0]);
        }
        $ranges   = explode('-', $rangeSpec, 2);
        $startRaw = isset($ranges[0]) ? trim($ranges[0]) : '';
        $endRaw   = isset($ranges[1]) ? trim($ranges[1]) : '';

        if ($startRaw === '' && $endRaw !== '' && ctype_digit($endRaw)) {
            $suffixLength = (int)$endRaw;
            if ($suffixLength <= 0 || $size === 0) {
                @fclose($handle);
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                exit;
            }
            $start = max(0, $size - $suffixLength);
            $end   = $size - 1;
        } else {
            $start = ($startRaw !== '' && ctype_digit($startRaw)) ? (int)$startRaw : 0;
            $end   = ($endRaw !== '' && ctype_digit($endRaw)) ? min((int)$endRaw, $size - 1) : ($size - 1);
        }

        if ($size === 0 || $start < 0 || $start >= $size || $start > $end) {
            @fclose($handle);
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }

        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        header('Content-Length: ' . (($end - $start) + 1));
    } else {
        header('Content-Length: ' . $size);
    }

    $reqMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : 'GET';
    if ($reqMethod === 'HEAD' || $size <= 0) {
        @fclose($handle);
        exit;
    }

    @set_time_limit(0);
    if ($start > 0) {
        @fseek($handle, $start, SEEK_SET);
    }

    $bytesRemaining = ($end - $start) + 1;
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
    exit;
}

$safeAccessToken  = rawurlencode($access_token_raw);
$safeStreamingKey = rawurlencode($user_streaming_key);
$safeMovieId      = rawurlencode($movie_id_raw);
$safeTitle        = htmlspecialchars((string)($movie_ary->title ?? ''), ENT_QUOTES, 'UTF-8');
$downloadUrl      = API_URL . '/Downloader/' . $safeStreamingKey . '/' . $safeMovieId . '/' . $safeAccessToken . '?start=download';
$safeDownloadUrl  = htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8');

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
        .dl-meta { color: #64748b; font-size: 0.85rem; margin-top: 6px; }
        .dl-btn { display: inline-block; margin-top: 14px; padding: 8px 16px; background: #0284c7; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.88rem; }
        .dl-btn:hover { background: #0369a1; }
        .dl-logo { position: fixed; bottom: 8px; right: 10px; opacity: 0.9; }
    </style>
    <script>
        setTimeout(function() {
            var el = document.getElementById("dl_info");
            if (el) { el.textContent = "Der Film wird heruntergeladen..."; }
        }, 1800);
    </script>
</head>
<body>
    <div id="head_info">' . $safeTitle . '</div>
    <div class="dl-body">
        <div id="dl_info">Der Download startet in wenigen Sekunden...</div>
        <div class="dl-meta">Dateigr&ouml;&szlig;e: ' . htmlspecialchars((string)convertBytes($size), ENT_QUOTES, 'UTF-8') . '</div>
        <a class="dl-btn" href="' . $safeDownloadUrl . '">Jetzt direkt herunterladen</a>
    </div>
    <div class="dl-logo">
        <a href="' . MCP_URL . '" target="_blank" rel="noopener"><img src="' . MCP_URL . '/erocloud_logo.png" alt="EroCloud" style="width:80px; height:auto;" /></a>
    </div>
</body>
</html>';

echo $site;

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());
