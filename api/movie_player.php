<?php

/**
 * Movie stream (High-Performance HTTP Range + Smart-TV / AirPlay / Chromecast Support)
 */

define('SAFE_INC', 1);

// Schnelle Beantwortung von CORS-Preflight-Requests (z. B. Google Cast / Chromecast / Smart-TVs) ohne DB-Overhead
if (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
    header('Access-Control-Allow-Headers: Range, If-Range, Content-Type, Origin, Accept');
    header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges, ETag');
    http_response_code(204);
    header('Content-Length: 0');
    exit;
}

include_once("../config.inc.php");
include_once(API_DIR . "/common.inc.php");
include_once(SOURCEDIR . '/includes/klassen/VideoStream.inc.php');

if (!isset($_GET['user_streaming_key']) || $_GET['user_streaming_key'] === '') {
    $api['error'] = 'key not exists';
    print_xml($api);
}

if (!isset($_GET['movie_id']) || $_GET['movie_id'] === '') {
    $api['error'] = 'movie_id not exists';
    print_xml($api);
}

if (!isset($_GET['access_token']) || $_GET['access_token'] === '') {
    $api['error'] = 'access denid';
    print_xml($api);
}

$user_streaming_key = (string)$_GET['user_streaming_key'];
$movie_id_raw       = (string)$_GET['movie_id'];
$access_token_raw   = (string)$_GET['access_token'];

// =========================================================================
// FAST-PATH: Session-Stream-Cache für extrem schnelles Seeking ohne DB-Last
// =========================================================================
$cacheKey = sha1($user_streaming_key . '|' . $movie_id_raw . '|' . $access_token_raw);
if (
    isset($_SESSION['erocloud_stream_cache'][$cacheKey])
    && is_array($_SESSION['erocloud_stream_cache'][$cacheKey])
) {
    $cachedEntry = $_SESSION['erocloud_stream_cache'][$cacheKey];
    $cachedAt    = isset($cachedEntry['ts']) ? (int)$cachedEntry['ts'] : 0;
    $cachedFile  = isset($cachedEntry['file']) ? (string)$cachedEntry['file'] : '';

    if (($cachedAt + 1800) >= time() && $cachedFile !== '' && is_file($cachedFile)) {
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        p4c_close(DB_HOST);

        $stream = new VideoStream($cachedFile, 'video/mp4');
        $stream->start();
    }
}

// Prüfen ob der user_streaming_key existiert
$rs_user_streaming_key = p4c_query("SELECT * FROM `movies_access` WHERE `user_streaming_key` = '" . p4c_escape_string($user_streaming_key) . "' LIMIT 1;", __FILE__, __LINE__);
if (p4c_num_rows($rs_user_streaming_key) == 0) {
    $api['error'] = 'access denid';
    print_xml($api);
}

$access_obj = p4c_fetch_object($rs_user_streaming_key);

// Wenn das Accesstoken nicht korrekt oder abgelaufen ist
if ($access_obj->access_token != $access_token_raw || $access_obj->access_token_datetime < date("Y-m-d H:i:s")) {
    $api['error'] = 'access denid';
    print_xml($api);
}

// Access-Token-Laufzeit gedrosselt verlängern (maximal 1x pro Stunde statt bei jedem einzelnen Range-Chunk,
// damit parallele Range-Requests beim Spulen auf Browser/Smart-TV keine DB-Row-Locks erzeugen).
if ($access_obj->access_token_datetime < date("Y-m-d H:i:s", strtotime("+5 hours"))) {
    p4c_query("UPDATE `movies_access` SET
        `access_token_datetime`='" . date("Y-m-d H:i:s", strtotime("+6 hours")) . "'
    WHERE `user_streaming_key`='" . p4c_escape_string($user_streaming_key) . "' LIMIT 1;", __FILE__, __LINE__);
}

$movie_id = p4c_escape_string($movie_id_raw);

$rs_movie = p4c_query("SELECT * FROM `movies` WHERE `file_id`='" . $movie_id . "' LIMIT 1;", __FILE__, __LINE__);
if (p4c_num_rows($rs_movie) == 0) {
    $api['error'] = 'movie_id not exists';
    print_xml($api);
}

$movie_ary = p4c_fetch_object($rs_movie);

$file = MOVIES_PATH . '/' . $movie_ary->storage_location . '/' . $movie_ary->merchant_id . '/' . $movie_ary->id . '/' . $movie_ary->filename;

// Im Session-Cache ablegen (Ringpuffer mit max. 20 Einträgen)
if (!isset($_SESSION['erocloud_stream_cache']) || !is_array($_SESSION['erocloud_stream_cache'])) {
    $_SESSION['erocloud_stream_cache'] = array();
}
if (count($_SESSION['erocloud_stream_cache']) >= 20) {
    array_shift($_SESSION['erocloud_stream_cache']);
}
$_SESSION['erocloud_stream_cache'][$cacheKey] = array(
    'file' => $file,
    'ts'   => time(),
);

// Session-Lock und DB-Verbindung VOR dem Streaming schließen!
if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
    @session_write_close();
}
p4c_close(DB_HOST);
p4c_errorlog(error_get_last());

$stream = new VideoStream($file, 'video/mp4');
$stream->start();
?>