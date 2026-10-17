<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
 */

define('SAFE_INC', 1);

include_once("../../config.inc.php");
include_once(ACP_DIR . "/common.inc.php");
include_once(SOURCEDIR . '/includes/klassen/VideoStream.inc.php');

if (is_logged_in('mcp') === false) {
    http_response_code(403);
    exit;
}

$file_id_raw = isset($_GET['id']) ? (string)$_GET['id'] : '';
$merchant_id = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
if ($file_id_raw === '' || $merchant_id <= 0) {
    http_response_code(404);
    exit;
}

// Fast-Path über Session-Stream-Cache (0 DB-Queries beim Spulen im MCP)
$cacheKey = 'mcp_' . $merchant_id . '_' . $file_id_raw;
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

$rs_movie = p4c_query("SELECT * FROM `movies` WHERE `file_id`='" . p4c_escape_string($file_id_raw) . "' AND `merchant_id`='" . $merchant_id . "' LIMIT 1;", __FILE__, __LINE__);
if (p4c_num_rows($rs_movie) == 0) {
    p4c_close(DB_HOST);
    http_response_code(404);
    exit;
}

$movie_ary = p4c_fetch_object($rs_movie);

$file = MOVIES_PATH . '/' . $movie_ary->storage_location . '/' . $movie_ary->merchant_id . '/' . $movie_ary->id . '/' . $movie_ary->filename;

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