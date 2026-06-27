<?php

/**
 * Für EroCMS: 
 * Zugang für Kunde auf Video anlegen
 */

#http://cloud2.erocms.net/api/buy_movie_erocms.php?user_streaming_key=4b508f23ecd4a2025f81f6cf89a57cd20c42d6d12da67fa94d3779d5b27aaeedb7471f9dd21830c96de32dfdbddabda33cfaf07bd75a748f88eb4e5e607616b6&movie_id=b1ab14c317def94c8f480cc65560b15a&user_id=1&shop_id=50135&buy_timestamp=1514414821

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

if (!isset($_SERVER['REMOTE_ADDR'])) {
    $api['error'] = 'access denid - no remote address';
    print_xml($api);
}

if (check_ip_whitelist($_SERVER['REMOTE_ADDR']) === false) {
    $api['error'] = 'access denid ip ('.$_SERVER['REMOTE_ADDR'].') not listet';
    print_xml($api);
}

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

// Prüfen ob der user_id existiert
if (!isset($_GET['user_id'])) {
    $api['error'] = 'no user_id received';
    print_xml($api);
}

// Prüfen ob der shop_id existiert
if (!isset($_GET['shop_id'])) {
    $api['error'] = 'no shop_id received';
    print_xml($api);
}

// Prüfen ob der Film für Streaming oder Download gekauft werden soll
if (!isset($_GET['buy_as']) OR ($_GET['buy_as'] != 'streaming' AND $_GET['buy_as'] != 'download')) {
    $api['error'] = 'no buy_as received';
    print_xml($api);
}

$input['user_streaming_key']= $_GET['user_streaming_key'];
$input['movie_id']          = $_GET['movie_id'];
$input['user_id']           = abs($_GET['user_id']);
$input['shop_id']           = abs($_GET['shop_id']);
$input['buy_as']            = $_GET['buy_as'];
$input['movie_price']       = abs($_GET['movie_price']);
$input['actor_commision']   = abs($_GET['actor_commision']);
$input['actor_commision_percent'] = abs($_GET['actor_commision_percent']);

// Der Streaming-Key erstellt sich wie folgt:
$user_streaming_key = generate_user_streaming_key($input);

#echo $user_streaming_key;

// Prüfen ob der user_streaming_key korrekt erstellt wurde
if ($input['user_streaming_key'] != $user_streaming_key) {
    $api['error'] = 'user_streaming_key created incorrectly';
    print_xml($api);
}

// Prüfen ob die movie_id existiert
$rs_movie_online = p4c_query("SELECT `file_id` FROM `movies_online` WHERE `file_id`='". p4c_escape_string($input['movie_id'])."' LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_movie_online) == 0) {
    $api['error'] = 'movie_id not exists';
    print_xml($api);
}

// Prüfen ob der user_streaming_key in der DB existiert, wenn nicht anlegen
$rs_user_streaming_key = p4c_query("SELECT * FROM `movies_access` WHERE
        `movie_id`              = '". p4c_escape_string($input['movie_id'])."' AND
        `user_id`               = '". p4c_escape_string($input['user_id'])."' AND
        `shop_id`               = '". p4c_escape_string($input['shop_id'])."' AND
        `buy_as`                = '". p4c_escape_string($input['buy_as'])."'
    LIMIT 1;",__FILE__,__LINE__);

if (p4c_num_rows($rs_user_streaming_key) == 0) {
    p4c_query("INSERT INTO `movies_access` SET
        `user_streaming_key`    = '". p4c_escape_string($input['user_streaming_key'])."',
        `movie_id`              = '". p4c_escape_string($input['movie_id'])."',
        `user_id`               = '". p4c_escape_string($input['user_id'])."',
        `shop_id`               = '". p4c_escape_string($input['shop_id'])."',
        `buy_as`                = '". p4c_escape_string($input['buy_as'])."',
        `buy_timestamp`         = '". date("Y-m-d H:i:s")."',
        `movie_price`           = '". p4c_escape_string($input['movie_price'])."',
        `actor_commision`       = '". p4c_escape_string($input['actor_commision'])."',
        `actor_commision_percent`= '". p4c_escape_string($input['actor_commision_percent'])."'
    ;",__FILE__,__LINE__);
}

#$api['server']      = print_r($_SERVER,true);
$api['version']     = '1.0';
$api['info']        = 'accass createt';
$api['user_streaming_key'] = $input['user_streaming_key'];
$api['movie_id']    = $input['movie_id'];
$api['user_id']     = $input['user_id'];
$api['shop_id']     = $input['shop_id'];
$api['buy_as']      = $input['buy_as'];

print_xml($api);

// Garbage Collection
p4c_close(DB_HOST);
	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>