<?php

/**
 * Movie Downloader (api.erocloud.net)
 */

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR . "/common.inc.php");

if (!isset($_GET['user_streaming_key'])) {
    $api['error'] = 'key not exists';
    print_xml($api);
}

if (!isset($_GET['movie_id'])) {
    $api['error'] = 'movie_id not exists';
    print_xml($api);
}

if (!isset($_GET['access_token'])) {
    $api['error'] = 'access denid';
    print_xml($api);
}

$user_streaming_key = (string)$_GET['user_streaming_key'];

// Prüfen, ob der user_streaming_key existiert
$rs_user_streaming_key = p4c_query("SELECT * FROM `movies_access` WHERE `user_streaming_key` = '" . p4c_escape_string($user_streaming_key) . "' LIMIT 1;", __FILE__, __LINE__);
if (p4c_num_rows($rs_user_streaming_key) == 0) {
    $api['error'] = 'access denid';
    print_xml($api);
}

$access_obj = p4c_fetch_object($rs_user_streaming_key);

// Wenn das Accesstoken nicht korrekt oder abgelaufen ist
if ($access_obj->access_token != $_GET['access_token'] || $access_obj->access_token_datetime < date("Y-m-d H:i:s")) {
    $api['error'] = 'access denid';
    print_xml($api);
}

$movie_id = p4c_escape_string((string)$_GET['movie_id']);

$rs_movie = p4c_query("SELECT * FROM `movies` WHERE `file_id`='" . $movie_id . "' LIMIT 1;", __FILE__, __LINE__);
if (p4c_num_rows($rs_movie) == 0) {
    $api['error'] = 'movie_id not exists';
    print_xml($api);
}

$movie_ary = p4c_fetch_object($rs_movie);

$file = MOVIES_PATH . '/' . $movie_ary->storage_location . '/' . $movie_ary->merchant_id . '/' . $movie_ary->id . '/' . $movie_ary->filename;

if (!file_exists($file)) {
    $class_errorlog->log("Ein Film konnte nicht heruntergeladen werden!\n" . $file, __FILE__, __LINE__);
    echo 'Es ist ein Fehler aufgetreten. Bitte versuche es sp&auml;ter noch einmal.';
} else {
    $size = (int)filesize($file);

    if (isset($_GET['start']) && $_GET['start'] == 'download') {
        session_write_close();

        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Cache-Control: private', false);
        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream');
        header('Content-Type: application/download');
        header('Content-Disposition: attachment; filename="' . seo_url((string)$movie_ary->title) . '.mp4"');
        header('Content-Description: File Transfer');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . $size);

        $bis = 1024 * 1024;
        $count = (int)ceil($size / $bis);
        $handle = fopen($file, "rb");
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
        $safeAccessToken = rawurlencode((string)$_GET['access_token']);
        $safeStreamingKey = rawurlencode($user_streaming_key);
        $safeMovieId = rawurlencode((string)$_GET['movie_id']);
        $safeTitle = htmlspecialchars((string)($movie_ary->title ?? ''), ENT_QUOTES, 'UTF-8');

        header('Content-Type: text/html; charset=utf-8');
        header('refresh:5; ' . API_URL . '/Downloader/' . $safeStreamingKey . '/' . $safeMovieId . '/' . $safeAccessToken . '&start=download');

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
            if (el) { el.textContent = "Der Film wird heruntergeladen..."; }
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
}

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());
