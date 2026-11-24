<?php

define('SAFE_INC', 1);

include_once("../config.inc.php");

if (!isset($_GET['file_id']) || !is_scalar($_GET['file_id']) || empty($_GET['file_id'])) {
    header('HTTP/1.1 404 Not Found');
    echo 'movie id not exists';
    exit;
}

$file_id = trim((string)$_GET['file_id']);
$fsk = '16';
if (isset($_GET['fsk']) && is_scalar($_GET['fsk'])) {
    $fsk_val = abs((int)$_GET['fsk']);
    if ($fsk_val == 16 || $fsk_val == 18) {
        $fsk = (string)$fsk_val;
    }
}
$req_width = 0;
if (isset($_GET['w']) && is_scalar($_GET['w']) && !empty($_GET['w'])) {
    $req_width = abs((int)$_GET['w']);
}
if ($req_width > 900) { $req_width = 900; }
if ($req_width > 0 && $req_width < 50) { $req_width = 50; }
$req_cs = (isset($_GET['cs']) && is_scalar($_GET['cs'])) ? trim((string)$_GET['cs']) : '';

// Zero-DB / Zero-Session Fast-Path: Wenn das skalierte Poster bereits im Disk-Cache liegt,
// direkt ohne Session-Start und ohne MySQL-Verbindung in ~1ms ausliefern!
$fastCacheDir = ((defined('TEMP_DIR') && is_dir(TEMP_DIR)) ? rtrim((string)TEMP_DIR, '/\\') : sys_get_temp_dir()) . '/ec_poster_cache';
$fastCacheFile = ($req_cs !== '')
    ? ($fastCacheDir . '/fast_mp_' . md5($file_id . '|' . $fsk . '|' . $req_width . '|' . $req_cs) . '.jpg')
    : '';

if ($fastCacheFile !== '' && is_file($fastCacheFile) && filesize($fastCacheFile) > 0) {
    $mtime = (int)@filemtime($fastCacheFile);
    $etag  = '"' . md5($fastCacheFile . '|' . $mtime) . '"';
    header("Pragma: cache");
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Expires: ' . gmdate(DATE_RFC1123, time() + 31536000));
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('ETag: ' . $etag);
    if (
        (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim((string)$_SERVER['HTTP_IF_NONE_MATCH']) === $etag)
        || (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime((string)$_SERVER['HTTP_IF_MODIFIED_SINCE']) === $mtime)
    ) {
        http_response_code(304);
        exit;
    }
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($fastCacheFile));
    readfile($fastCacheFile);
    exit;
}

include_once(API_DIR."/common.inc.php");
@session_write_close();

function send_fallback_and_exit() {
    $fallback = MCP_DIR.'/images/movie_poster_nopic.jpg';
    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('Content-type: image/jpeg');
    if (is_file($fallback)) {
        header('Content-length: '.filesize($fallback));
        readfile($fallback);
    }
    exit;
}

function output_raw_file_and_exit($bild, $mime_type) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('Content-type: '.$mime_type);
    header('Content-length: '.filesize($bild));
    readfile($bild);
    exit;
}

if (!isset($_GET['file_id']) || !is_scalar($_GET['file_id']) || empty($_GET['file_id'])) {
    header('HTTP/1.1 404 Not Found');
    echo 'movie id not exists';
    exit;
}

$file_id = trim((string)$_GET['file_id']);

$fsk = '16';
if (isset($_GET['fsk'])) {
    $fsk_val = abs((int)$_GET['fsk']);
    if ($fsk_val == 16 || $fsk_val == 18) {
        $fsk = (string)$fsk_val;
    }
}

$fsk_field = 'preview_image_fsk'.$fsk;

$rs_movie = p4c_query("SELECT `id`, `merchant_id`, `storage_location`, `file_id`, `preview_image_fsk16`, `preview_image_fsk18` FROM `movies` WHERE `file_id`='".p4c_escape_string($file_id)."' LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_movie) == 0) {
    @p4c_close(DB_HOST);
    header('HTTP/1.1 404 Not Found');
    echo 'movie id not exists';
    exit;   
}

$movie_ary = p4c_fetch_object($rs_movie);
@p4c_close(DB_HOST);
if (!$movie_ary) {
    header('HTTP/1.1 404 Not Found');
    echo 'movie id not exists';
    exit;
}

$selected_thumb = !empty($movie_ary->$fsk_field) ? $movie_ary->$fsk_field : '1';
$base_path = MOVIES_PATH.'/'.$movie_ary->storage_location.'/'.$movie_ary->merchant_id.'/'.$movie_ary->id.'/thumb_'.$movie_ary->id.'_'.$movie_ary->file_id.'_';

$filename = $base_path . $selected_thumb;
$found_file = false;

foreach (array('.jpg', '.jpeg', '.png', '.gif') as $ext) {
    if (file_exists($filename . $ext)) {
        $filename = $filename . $ext;
        $found_file = true;
        break;
    }
}

if (!$found_file) {
    foreach (array('.jpg', '.jpeg', '.png', '.gif') as $ext) {
        if (file_exists($base_path . '1' . $ext)) {
            $filename = $base_path . '1' . $ext;
            $found_file = true;
            break;
        }
    }
}

if (!function_exists('getRequestHeaders')) {
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
}

function to_thumb($bild, $size = 0) {
    if (!is_file($bild) || filesize($bild) == 0) {
        send_fallback_and_exit();
    }

    $image_info = @getimagesize($bild);
    if (!$image_info) {
        $mime_type = @mime_content_type($bild);
        if (!$mime_type) { $mime_type = 'image/jpeg'; }
        output_raw_file_and_exit($bild, $mime_type);
    }

    $mime_type = $image_info['mime'];
    $breite = $image_info[0];
    $hoehe = $image_info[1];

    if ($breite <= 0 || $hoehe <= 0) {
        send_fallback_and_exit();
    }

    if ($size == 0 || $size > 900) {
        $size = 800;
    }

    if ($size >= $breite) {
        output_raw_file_and_exit($bild, $mime_type);
    }

    $prop = $hoehe / $breite;
    $neueBreite = max(1, (int)$size);
    $neueHoehe = max(1, (int)round($neueBreite * $prop));

    $cacheBase = (defined('TEMP_DIR') && is_dir(TEMP_DIR)) ? rtrim((string)TEMP_DIR, '/\\') : sys_get_temp_dir();
    $cacheDir  = $cacheBase . '/ec_poster_cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    $cacheFile = $cacheDir . '/mp_' . md5($bild . '|' . (int)@filemtime($bild) . '|' . $neueBreite) . '.bin';
    if (is_file($cacheFile) && filesize($cacheFile) > 0) {
        if (!empty($GLOBALS['fastCacheFile']) && !is_file($GLOBALS['fastCacheFile'])) {
            @copy($cacheFile, $GLOBALS['fastCacheFile']);
        }
        output_raw_file_and_exit($cacheFile, $mime_type);
    }

    $altesBild = null;
    if ($mime_type == 'image/jpeg' || $mime_type == 'image/jpg') {
        $image_data = @file_get_contents($bild);
        if ($image_data) {
            $altesBild = @imagecreatefromstring($image_data);
            if ($altesBild) {
                $exif = @exif_read_data($bild);
                if (!empty($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3:
                            $rot = @imagerotate($altesBild, 180, 0);
                            if ($rot) { $altesBild = $rot; }
                            break;
                        case 6:
                            $rot = @imagerotate($altesBild, -90, 0);
                            if ($rot) { $altesBild = $rot; }
                            break;
                        case 8:
                            $rot = @imagerotate($altesBild, 90, 0);
                            if ($rot) { $altesBild = $rot; }
                            break;
                    }
                    $breite     = imagesx($altesBild);
                    $hoehe      = imagesy($altesBild);
                    $neueBreite = max(1, min((int)$size, $breite));
                    $neueHoehe  = max(1, (int)round($neueBreite * ($hoehe / max(1, $breite))));
                }
            }
        }
    } elseif ($mime_type == 'image/png') {
        $altesBild = @imagecreatefrompng($bild);
    } elseif ($mime_type == 'image/gif') {
        $altesBild = @imagecreatefromgif($bild);
    }

    if (!$altesBild) {
        output_raw_file_and_exit($bild, $mime_type);
    }

    $neuesBild = imagecreatetruecolor($neueBreite, $neueHoehe);
    if (!$neuesBild) {
        unset($altesBild);
        output_raw_file_and_exit($bild, $mime_type);
    }
    if ($mime_type == 'image/png' || $mime_type == 'image/gif') {
        imagealphablending($neuesBild, false);
        imagesavealpha($neuesBild, true);
    }

    imagecopyresampled($neuesBild, $altesBild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $breite, $hoehe);

    while (ob_get_level() > 0) { @ob_end_clean(); }

    header('Content-type: '.$mime_type);
    ob_start();
    if ($mime_type == 'image/png') {
        imagepng($neuesBild, null, 9);
    } elseif ($mime_type == 'image/gif') {
        imagegif($neuesBild, null);
    } else {
        imagejpeg($neuesBild, null, 90);
    }
    $binaryData = ob_get_clean();
    if ($binaryData !== false && $binaryData !== '') {
        @file_put_contents($cacheFile, $binaryData);
        if (!empty($GLOBALS['fastCacheFile'])) {
            @file_put_contents($GLOBALS['fastCacheFile'], $binaryData);
        }
    }
    header('Content-length: '.strlen((string)$binaryData));
    echo $binaryData;

    unset($neuesBild, $altesBild);
    exit;
}

$headers = getRequestHeaders();

if (!$found_file || !is_file($filename) || filesize($filename) == 0) {
    send_fallback_and_exit();
} else {
    $mime_content_type = @mime_content_type($filename) ?: 'image/jpeg';
    
    header("Pragma: cache");
    header('Cache-control: max-age=31536000, public');
    header('Expires: '.gmdate(DATE_RFC1123, time() + 31536000));
    header('Content-type: '.$mime_content_type);
   
    $width = 0;
    if (isset($_GET['w']) AND !empty($_GET['w'])) {
        $width = abs((int)$_GET['w']);
    }
    
    if ($width > 900) {$width = 900;}
    if ($width > 0 && $width < 50) {$width = 50;}

    $mtime = (int)@filemtime($filename);
    if ($mtime <= 0) { $mtime = time(); }
    $imsTs = isset($headers['If-Modified-Since']) ? strtotime((string)$headers['If-Modified-Since']) : false;
    if ($imsTs !== false && $imsTs === $mtime) {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT', true, 304);
        exit;
    } else {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT', true, 200);
        header('Content-transfer-encoding: binary');
        to_thumb($filename, $width);
    }
}

?>