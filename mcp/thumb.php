<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
 */

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

if (!isset($_GET['movie_id']) || empty($_GET['movie_id'])) {
    $fallback = MCP_DIR.'/images/movie_poster_nopic.jpg';
    if (is_file($fallback)) {
        header('Content-type: image/jpeg');
        header('Content-length: '.filesize($fallback));
        readfile($fallback);
    }
    exit;
}

$movie_id_param = p4c_escape_string($_GET['movie_id']);
$thumb_num = isset($_GET['thumb_number']) ? abs($_GET['thumb_number']) : 1;

$rs_movie = p4c_query("SELECT `id`, `file_id`, `merchant_id`, `filename`, `storage_location` FROM `movies` WHERE `file_id`='".$movie_id_param."' LIMIT 1;",__FILE__,__LINE__);

if (p4c_num_rows($rs_movie) == 0) {
    $fallback = MCP_DIR.'/images/movie_poster_nopic.jpg';
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

$movie_ary = p4c_fetch_object($rs_movie);
$folder_path = MOVIES_PATH.'/'.$movie_ary->storage_location.'/'.$movie_ary->merchant_id.'/'.$movie_ary->id.'/';

// Try primary pattern: thumb_[id]_[file_id]_[thumb_num]
$filename_patterns = array(
    $folder_path . 'thumb_' . $movie_ary->id . '_' . $movie_ary->file_id . '_' . $thumb_num,
    $folder_path . 'thumb_' . substr($movie_ary->filename, 0, -4) . '_' . $thumb_num,
    $folder_path . 'thumb_' . $movie_ary->id . '_' . $movie_ary->file_id . '_1',
    $folder_path . 'thumb_' . substr($movie_ary->filename, 0, -4) . '_1'
);

$target_file = false;
foreach ($filename_patterns as $pattern) {
    foreach (array('.jpg', '.jpeg', '.png', '.gif') as $ext) {
        if (file_exists($pattern . $ext)) {
            $target_file = $pattern . $ext;
            break 2;
        }
    }
}

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
        $fallback = MCP_DIR.'/images/movie_poster_nopic.jpg';
        if (is_file($fallback)) {
            header('Content-type: image/jpeg');
            header('Content-length: '.filesize($fallback));
            readfile($fallback);
        }
        return;
    }

    $image_info = @getimagesize($bild);
    if (!$image_info) {
        $mime_type = @mime_content_type($bild);
        if (!$mime_type) { $mime_type = 'image/jpeg'; }
        header('Content-type: '.$mime_type);
        header('Content-length: '.filesize($bild));
        readfile($bild);
        return;
    }

    $mime_type = $image_info['mime'];
    $breite = $image_info[0];
    $hoehe = $image_info[1];

    if ($breite <= 0 || $hoehe <= 0) {
        $fallback = MCP_DIR.'/images/movie_poster_nopic.jpg';
        if (is_file($fallback)) {
            header('Content-type: image/jpeg');
            readfile($fallback);
        }
        return;
    }

    if ($size == 0 || $size > 1024) {
        $size = 1024;
    }

    if ($size >= $breite) {
        header('Content-type: '.$mime_type);
        header('Content-length: '.filesize($bild));
        readfile($bild);
        return;
    }

    $prop = $hoehe / $breite;
    $neueBreite = (int)$size;
    $neueHoehe = (int)round($size * $prop);

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
        header('Content-type: '.$mime_type);
        header('Content-length: '.filesize($bild));
        readfile($bild);
        return;
    }

    $neuesBild = imagecreatetruecolor($neueBreite, $neueHoehe);
    if ($mime_type == 'image/png' || $mime_type == 'image/gif') {
        imagealphablending($neuesBild, false);
        imagesavealpha($neuesBild, true);
    }

    imagecopyresampled($neuesBild, $altesBild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $breite, $hoehe);

    header('Content-type: '.$mime_type);
    ob_start();
    if ($mime_type == 'image/png') {
        imagepng($neuesBild, null, 9);
    } elseif ($mime_type == 'image/gif') {
        imagegif($neuesBild, null);
    } else {
        imagejpeg($neuesBild, null, 90);
    }
    $length = ob_get_length();
    header('Content-length: '.$length);
    ob_end_flush();

    imagedestroy($neuesBild);
    imagedestroy($altesBild);
}

$headers = getRequestHeaders();

// Wenn Datei nicht existiert oder leer ist
if (!$target_file || !is_file($target_file) || filesize($target_file) == 0) {
    $fallback = MCP_DIR.'/images/movie_poster_nopic.jpg';
    header("Pragma: cache");
    header('Cache-control: max-age=31536000, public');
    header('Expires: '.gmdate(DATE_RFC1123, time() + 31536000));
    header('Content-type: image/jpeg');
    if (is_file($fallback)) {
        header('Content-length: '.filesize($fallback));
        readfile($fallback);
    }
} else {
    $mime_content_type = mime_content_type($target_file);
    
    header("Pragma: cache");
    header('Cache-control: max-age=31536000, public');
    header('Expires: '.gmdate(DATE_RFC1123, time() + 31536000));
    header('Content-type: '.$mime_content_type);
   
    $width = 0;
    if (isset($_GET['w']) AND !empty($_GET['w'])) {
        $width = abs($_GET['w']);
    }
    
    if ($width > 1024) {$width = 1024;}
    if ($width < 50) {$width = 50;}
   
    if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($target_file))) {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($target_file)).' GMT', true, 304);
    } else {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($target_file)).' GMT', true, 200);
        header('Content-transfer-encoding: binary');
        to_thumb($target_file, $width);
    }
}

// Garbage Collection
p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>