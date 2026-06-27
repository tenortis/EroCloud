<?php

/**
 * URL zum Film X
 */

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

// Prüfen ob der user_streaming_key angegeben wurde
if (!isset($_GET['user_streaming_key'])) {
    $api['error'] = 'no user_streaming_key received';
    print_xml($api);
}

// Prüfen ob der movie_id existiert
if (!isset($_GET['movie_id'])) {
    $api['error'] = 'no movie_id received';
    print_xml($api);
}


// Prüfen URL für Streaming oder Download generiert werden soll
if (!isset($_GET['buy_as']) OR ($_GET['buy_as'] != 'streaming' AND $_GET['buy_as'] != 'download')) {
    $api['error'] = 'no buy_as received';
    print_xml($api);
}

$user_streaming_key = $_GET['user_streaming_key'];

// Prüfen ob der user_streaming_key existiert
$rs_user_streaming_key = p4c_query("SELECT * FROM `movies_access` WHERE `user_streaming_key` = '". p4c_escape_string($user_streaming_key)."' LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_user_streaming_key) == 0) {
    $api['error'] = 'access denid';
    print_xml($api);
}

$movie_id = p4c_escape_string($_GET['movie_id']);

$rs_movie = p4c_query("SELECT * FROM `movies` WHERE `file_id`='".$movie_id."' LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_movie) == 0) {
    $api['error'] = 'movie_id not exists';
    print_xml($api);;   
}

$movie_obj = p4c_fetch_object($rs_movie);
$resolution = explode('x',$movie_obj->resolution);

// Das Token ist nur 30 Sekunden gültig. Danach kann der Film von diesem Kunden nicht mehr direkt per URL aufgerufen werden
$access_token = randomString(10);

p4c_query("UPDATE `movies_access` SET 
    `access_token` = '".$access_token."',
    `access_token_datetime` = '".date("Y-m-d H:i:s", strtotime("+30 minutes"))."'
WHERE `user_streaming_key` = '". p4c_escape_string($user_streaming_key)."' LIMIT 1;",__FILE__,__LINE__);

if ($_GET['buy_as'] == 'download') {
    $api['movie_url'] = API_URL.'/Downloader/'.$user_streaming_key.'/'.$movie_id.'/'.$access_token;
} else {
    $api['movie_url']           = API_URL.'/Player/'.$user_streaming_key.'/'.$movie_id.'/'.$access_token;
    $api['movie_url_mobil_mp4'] = API_URL.'/Player/'.$user_streaming_key.'/'.$movie_id.'/'.$access_token.'&mobile=mp4';
    $api['movie_url_mobil_ogg'] = API_URL.'/Player/'.$user_streaming_key.'/'.$movie_id.'/'.$access_token.'&mobile=ogg';
    $api['movie_url_mobil_webm']= API_URL.'/Player/'.$user_streaming_key.'/'.$movie_id.'/'.$access_token.'&mobile=webm';
    $api['poster_url_fsk16']    = API_URL.'/PlayerPoster/FSK16/'.$movie_id;
    $api['poster_url_fsk18']    = API_URL.'/PlayerPoster/FSK18/'.$movie_id;
    $api['width']               = $resolution[0];
    $api['height']              = $resolution[1];

}

print_xml($api);

// Garbage Collection
p4c_close(DB_HOST);
	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>