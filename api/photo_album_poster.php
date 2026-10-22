<?php

define('SAFE_INC', 1);

include_once("../config.inc.php");

$fast_album_param = '';
if (!empty($_GET['album_id'])) {
    $fast_album_param = trim((string)$_GET['album_id']);
} elseif (!empty($_GET['file_id'])) {
    $fast_album_param = trim((string)$_GET['file_id']);
} elseif (!empty($_GET['id'])) {
    $fast_album_param = trim((string)$_GET['id']);
}
$fast_fsk = (isset($_GET['fsk']) && abs((int)$_GET['fsk']) == 18) ? '18' : '16';
$fast_w   = isset($_GET['w']) && !empty($_GET['w']) ? abs((int)$_GET['w']) : 0;
if ($fast_w > 1280) { $fast_w = 1280; }
if ($fast_w > 0 && $fast_w < 50) { $fast_w = 50; }
$fast_cs  = isset($_GET['cs']) ? trim((string)$_GET['cs']) : '';

$fastCacheDir  = ((defined('TEMP_DIR') && is_dir(TEMP_DIR)) ? rtrim((string)TEMP_DIR, '/\\') : sys_get_temp_dir()) . '/ec_poster_cache';
$fastCacheFile = ($fast_album_param !== '' && $fast_cs !== '')
    ? ($fastCacheDir . '/fast_pa_' . md5($fast_album_param . '|' . $fast_fsk . '|' . $fast_w . '|' . $fast_cs) . '.jpg')
    : '';

if ($fastCacheFile !== '' && is_file($fastCacheFile) && filesize($fastCacheFile) > 0) {
    $mtime = (int)@filemtime($fastCacheFile);
    $etag  = '"' . md5($fastCacheFile . '|' . $mtime) . '"';
    header('Access-Control-Allow-Origin: *');
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

header('Access-Control-Allow-Origin: *');

function send_fallback_and_exit() {
    $fallback = SOURCEDIR . '/mcp/images/movie_poster_nopic.jpg';
    if (!is_file($fallback)) {
        $fallback = SOURCEDIR . '/acp/images/movie_poster_nopic.jpg';
    }
    while (ob_get_level() > 0) { @ob_end_clean(); }
    header("Pragma: cache");
    header('Cache-control: max-age=31536000, public');
    header('Expires: '.gmdate(DATE_RFC1123, time() + 31536000));
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

$album_param = '';
if (isset($_GET['album_id']) && !empty($_GET['album_id'])) {
    $album_param = trim($_GET['album_id']);
} elseif (isset($_GET['file_id']) && !empty($_GET['file_id'])) {
    $album_param = trim($_GET['file_id']);
} elseif (isset($_GET['id']) && !empty($_GET['id'])) {
    $album_param = trim($_GET['id']);
}

if (empty($album_param)) {
    send_fallback_and_exit();
}

$fsk = 'preview_image_fsk16';
if (isset($_GET['fsk']) && abs((int)$_GET['fsk']) == 18) {
    $fsk = 'preview_image_fsk18';
}

$escaped_id = p4c_escape_string($album_param);
$numeric_id = ctype_digit((string)$album_param) ? abs((int)$album_param) : 0;

// Query photo_albums FIRST by valid columns (album_id or numeric id)
$rs_album = p4c_query("SELECT `id`, `merchant_id`, `storage_location`, `album_id`, `preview_image_fsk16`, `preview_image_fsk18` FROM `photo_albums` WHERE `album_id`='".$escaped_id."'" . ($numeric_id > 0 ? " OR `id`='".$numeric_id."'" : "") . " LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_album) == 0) {
    $rs_album = p4c_query("SELECT `id`, `merchant_id`, `storage_location`, `album_id`, `preview_image_fsk16`, `preview_image_fsk18` FROM `photo_albums_online` WHERE `album_id`='".$escaped_id."'" . ($numeric_id > 0 ? " OR `id`='".$numeric_id."'" : "") . " LIMIT 1;",__FILE__,__LINE__);
}

if (p4c_num_rows($rs_album) == 0) {
    send_fallback_and_exit();
}

$album_ary = p4c_fetch_object($rs_album);
$album_folder = PHOTO_ALBUMS_PATH.'/'.$album_ary->storage_location.'/'.$album_ary->merchant_id.'/'.$album_ary->id.'/';

$filename = false;

// 1. Check DB stored preview image (preview_image_fsk16 or preview_image_fsk18)
if (!empty($album_ary->$fsk) && file_exists($album_folder . $album_ary->$fsk)) {
    $filename = $album_folder . $album_ary->$fsk;
}

// 2. Check alternative FSK preview image if specific FSK preview doesn't exist
if (!$filename) {
    $alt_fsk = ($fsk == 'preview_image_fsk18') ? 'preview_image_fsk16' : 'preview_image_fsk18';
    if (!empty($album_ary->$alt_fsk) && file_exists($album_folder . $album_ary->$alt_fsk)) {
        $filename = $album_folder . $album_ary->$alt_fsk;
    }
}

// 3. Check first photo in album from photo_albums_photos database table
if (!$filename) {
    $rs_first_photo = p4c_query("SELECT `filename` FROM `photo_albums_photos` WHERE `album_id`='".$escaped_id."' OR `album_id`='".$album_ary->id."' ORDER BY `id` ASC LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_first_photo) > 0) {
        $photo_obj = p4c_fetch_object($rs_first_photo);
        $candidate = $album_folder . 'images/' . $photo_obj->filename;
        if (file_exists($candidate)) {
            $filename = $candidate;
        }
    }
}

// 4. Glob search in images subfolder
if (!$filename) {
    $files = glob($album_folder . 'images/*.{jpg,jpeg,png,gif,JPG,JPEG,PNG,GIF}', GLOB_BRACE);
    if (!empty($files)) {
        $filename = $files[0];
    }
}

// 5. Glob search in main album folder
if (!$filename) {
    $files = glob($album_folder . '*.{jpg,jpeg,png,gif,JPG,JPEG,PNG,GIF}', GLOB_BRACE);
    if (!empty($files)) {
        $filename = $files[0];
    }
}

if (!$filename || !is_file($filename)) {
    @p4c_close(DB_HOST);
    send_fallback_and_exit();
}
@p4c_close(DB_HOST);

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

    if ($size == 0 || $size > 1280) {
        $size = 1024;
    }

    if ($size >= $breite) {
        output_raw_file_and_exit($bild, $mime_type);
    }

    $prop = $hoehe / $breite;
    $neueBreite = (int)$size;
    $neueHoehe = (int)round($size * $prop);

    $cacheBase = (defined('TEMP_DIR') && is_dir(TEMP_DIR)) ? rtrim((string)TEMP_DIR, '/\\') : sys_get_temp_dir();
    $cacheDir  = $cacheBase . '/ec_poster_cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    $cacheFile = $cacheDir . '/pa_' . md5($bild . '|' . (int)@filemtime($bild) . '|' . $neueBreite) . '.bin';
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
                            $altesBild = @imagerotate($altesBild, 180, 0);
                            break;
                        case 6:
                            $altesBild = @imagerotate($altesBild, -90, 0);
                            break;
                        case 8:
                            $altesBild = @imagerotate($altesBild, 90, 0);
                            break;
                    }
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

    imagedestroy($neuesBild);
    imagedestroy($altesBild);
    exit;
}

$headers = getRequestHeaders();

if (!is_file($filename) || filesize($filename) == 0) {
    send_fallback_and_exit();
} else {
    $mime_content_type = mime_content_type($filename);
    
    header("Pragma: cache");
    header('Cache-control: max-age=31536000, public');
    header('Expires: '.gmdate(DATE_RFC1123, time() + 31536000));
    header('Content-type: '.$mime_content_type);
   
    $width = 0;
    if (isset($_GET['w']) AND !empty($_GET['w'])) {
        $width = abs((int)$_GET['w']);
    }
    
    if ($width > 1280) {$width = 1280;}
    if ($width < 50) {$width = 50;}
   
    if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($filename))) {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 304);
        exit;
    } else {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 200);
        header('Content-transfer-encoding: binary');
        to_thumb($filename, $width);
    }
}

?>