<?php

/**
 * Gekauftes Foto anzeigen
 * 
 */

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

function no_image() {
    $filename = MCP_DIR.'/images/movie_poster_nopic.jpg';
    header('Content-type: image/jpeg');
    header('Content-transfer-encoding: binary');
    header('Content-length: '.filesize($filename));
    readfile($filename);
    
    // Garbage Collection
    p4c_close(DB_HOST);

    // PHP Fehlermeldung loggen
    p4c_errorlog(error_get_last());
}

if (!isset($_GET['user_key'])) {
    $api['error'] = 'user_key not exists';
    print_xml($api);
}

if (!isset($_GET['photo_id'])) {
    no_image();
    exit;
}

if (!isset($_GET['access_token'])) {
    $api['error'] = 'access denid 1';
    print_xml($api);
}

$user_key = $_GET['user_key'];

// Prüfen ob der user_streaming_key existiert
$rs_user_key = p4c_query("SELECT * FROM `photo_albums_access` WHERE `user_key` = '".p4c_escape_string($user_key)."' LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_user_key) == 0) {
    $api['error'] = 'access denid 2';
    print_xml($api);
}

$access_obj = p4c_fetch_object($rs_user_key);

// Wenn das Accesstoken nicht korrekt oder abgelaufen ist
if ($access_obj->access_token != $_GET['access_token'] OR $access_obj->access_token_datetime < date("Y-m-d H:i:s")) {
    $api['error'] = 'access denid 3';
    print_xml($api);    
}

$photo_id = $_GET['photo_id'];

$rs_photo = p4c_query("SELECT `photo_albums`.`id` AS `id`, `photo_albums`.`storage_location`, `photo_albums_photos`.`merchant_id`, `photo_albums_photos`.`filename`
    FROM `photo_albums_photos`INNER JOIN `photo_albums` ON `photo_albums_photos`.`album_id`=`photo_albums`.`album_id` WHERE
        `file_id`='".p4c_escape_string($photo_id)."' AND
        `photo_albums`.`album_id`='". p4c_escape_string($access_obj->album_id)."'
    LIMIT 1;",__FILE__,__LINE__);

if (p4c_num_rows($rs_photo) == 0) {
    no_image();
    exit;
}

$photo_obj = p4c_fetch_object($rs_photo);

$file_dir   = PHOTO_ALBUMS_PATH.'/'.$photo_obj->storage_location.'/'.$photo_obj->merchant_id.'/'.$photo_obj->id.'/images/';
$file_name  = $photo_obj->filename;
$file_path  = $file_dir.$file_name;

function getRequestHeaders() {
    if (function_exists("apache_request_headers")) {
        if($headers = apache_request_headers()) {
            return $headers;
        }
    }
    $headers = array();
    // Grab the IF_MODIFIED_SINCE header
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        $headers['If-Modified-Since'] = $_SERVER['HTTP_IF_MODIFIED_SINCE'];
    }
    return $headers;
}

$headers = getRequestHeaders();

// Wenn Datei nicht existiert
if (!is_file($file_path)) {
    no_image();
    exit;

// Wenn Datei leer ist dann löschen
} elseif(filesize($file_path) == 0) {
    /*
    @unlink($file_path);
    */
    $file_path = MCP_DIR.'/images/movie_poster_nopic.jpg';
    no_image();
    exit;

} else {

    $mime_content_type = mime_content_type($file_path);
     
    header("Pragma: cache");
    header('Cache-control: max-age='.(60*60*24*360).', public');
    header('Expires: '.gmdate(DATE_RFC1123,time()+60*60*24*365));
    header('Content-type: '.$mime_content_type);
   
    $width = 800;
    if (isset($_GET['w']) AND !empty($_GET['w'])) {
        $width = abs($_GET['w']);
    }

    if ($width < 800) {$width = 800;}
    
    if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($file_path))) {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file_path)).' GMT', true, 304);
    } else {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file_path)).' GMT', true, 200);
        header('Content-transfer-encoding: binary');
        readfile($file_path);
    }
}

// Garbage Collection
p4c_close(DB_HOST);
	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>