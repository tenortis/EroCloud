<?php

/**
 * Movie stream
 */

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

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

$user_streaming_key = $_GET['user_streaming_key'];

// Prüfen ob der user_streaming_key existiert
$rs_user_streaming_key = p4c_query("SELECT * FROM `movies_access` WHERE `user_streaming_key` = '".p4c_escape_string($user_streaming_key)."' LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_user_streaming_key) == 0) {
    $api['error'] = 'access denid';
    print_xml($api);
}

$access_obj = p4c_fetch_object($rs_user_streaming_key);

// Wenn das Accesstoken nicht korrekt oder abgelaufen ist
if ($access_obj->access_token != $_GET['access_token'] OR $access_obj->access_token_datetime < date("Y-m-d H:i:s")) {
    $api['error'] = 'access denid';
    print_xml($api);    
}

// Access Token verlängern wenn Seite innerhalb der Lebensdauer vom Token neu geladen wird.
p4c_query("UPDATE `movies_access` SET
    `access_token_datetime`='".date("Y-m-d H:i:s", strtotime("+30 minutes"))."'
WHERE `user_streaming_key`='".p4c_escape_string($user_streaming_key)."' LIMIT 1;",__FILE__,__LINE__);

$movie_id = p4c_escape_string($_GET['movie_id']);

$rs_movie = p4c_query("SELECT * FROM `movies` WHERE `file_id`='".$movie_id."' LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_movie) == 0) {
    $api['error'] = 'movie_id not exists';
    print_xml($api);;   
}

$movie_ary = p4c_fetch_object($rs_movie);

p4c_close(DB_HOST);
	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());


$file = MOVIES_PATH.'/'.$movie_ary->storage_location.'/'.$movie_ary->merchant_id.'/'.$movie_ary->id.'/'.$movie_ary->filename;

#header("Content-Type: video/mp4");
include(SOURCEDIR.'/includes/klassen/VideoStream.inc.php');        
$stream = new VideoStream($file);
$stream->start();


?>