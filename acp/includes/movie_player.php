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

if (is_logged_in('acp') === false) {
    http_response_code(403);
    exit;
}

$movie_id_raw = isset($_GET['id']) ? (string)$_GET['id'] : '';
if ($movie_id_raw === '') {
    http_response_code(404);
    exit;
}

// Fast-Path über Session-Stream-Cache (0 DB-Queries beim Spulen im ACP)
$cacheKey = 'acp_' . $movie_id_raw;
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

$movie_id = p4c_escape_string($movie_id_raw);

$rs_movie = p4c_query("SELECT * FROM `movies` WHERE `file_id`='" . $movie_id . "' LIMIT 1;", __FILE__, __LINE__);
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