<?php

/**
 * Für EroCMS: 
 * Zugang für Kunde auf Album anlegen
 */

#https://api.erocloud.net/buy_photo_album_erocms.php?actor_commision=50&actor_commision_percent=25&album_id=c0ebd57492b6e3ae628cc69c6299416e&album_price=200&buy_as=download&shop_id=50013&user_id=1&user_key=3351f5ce8b722f47730e89a9b70e21896b313e4b5ee213a1a1415d850761524becb5d4920de23d1a37feb9483fd0a4e30f782e80ca166490978831c5d53ce89c

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

// Prüfen ob der user_key angegeben wurde
if (!isset($_GET['user_key'])) {
    $api['error'] = 'no user_key received';
    print_xml($api);
}

// Prüfen ob der album_id existiert
if (!isset($_GET['album_id'])) {
    $api['error'] = 'no album_id received';
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
if (!isset($_GET['buy_as']) OR ($_GET['buy_as'] != 'online' AND $_GET['buy_as'] != 'download')) {
    $api['error'] = 'no buy_as received';
    print_xml($api);
}

$input['user_key']          = $_GET['user_key'];
$input['album_id']          = $_GET['album_id'];
$input['user_id']           = abs($_GET['user_id']);
$input['shop_id']           = abs($_GET['shop_id']);
$input['buy_as']            = $_GET['buy_as'];
$input['album_price']       = abs($_GET['album_price']);
$input['actor_commision']   = abs($_GET['actor_commision']);
$input['actor_commision_percent'] = abs($_GET['actor_commision_percent']);

// Der Streaming-Key erstellt sich wie folgt:
$user_key = generate_user_album_key($input);

#echo $user_key;

// Prüfen ob der user_key korrekt erstellt wurde
if ($input['user_key'] != $user_key) {
    $api['error'] = 'user_key created incorrectly';
    print_xml($api);
}

// Prüfen ob die album_id existiert
$rs_album_online = p4c_query("SELECT `album_id` FROM `photo_albums_online` WHERE `album_id`='". p4c_escape_string($input['album_id'])."' LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_album_online) == 0) {
    $api['error'] = 'album_id not exists';
    print_xml($api);
}

// Prüfen ob der user_streaming_key in der DB existiert, wenn nicht anlegen
$rs_user_key = p4c_query("SELECT * FROM `photo_albums_access` WHERE
        `album_id`              = '". p4c_escape_string($input['album_id'])."' AND
        `user_id`               = '". p4c_escape_string($input['user_id'])."' AND
        `shop_id`               = '". p4c_escape_string($input['shop_id'])."' AND
        `buy_as`                = '". p4c_escape_string($input['buy_as'])."'
    LIMIT 1;",__FILE__,__LINE__);

if (p4c_num_rows($rs_user_key) == 0) {
    p4c_query("INSERT INTO `photo_albums_access` SET
        `user_key`              = '". p4c_escape_string($input['user_key'])."',
        `album_id`              = '". p4c_escape_string($input['album_id'])."',
        `user_id`               = '". p4c_escape_string($input['user_id'])."',
        `shop_id`               = '". p4c_escape_string($input['shop_id'])."',
        `buy_as`                = '". p4c_escape_string($input['buy_as'])."',
        `buy_timestamp`         = '". date("Y-m-d H:i:s")."',
        `album_price`           = '". p4c_escape_string($input['album_price'])."',
        `actor_commision`       = '". p4c_escape_string($input['actor_commision'])."',
        `actor_commision_percent`= '". p4c_escape_string($input['actor_commision_percent'])."'
    ;",__FILE__,__LINE__);
}

#$api['server']      = print_r($_SERVER,true);
$api['version']     = '1.0';
$api['info']        = 'accass createt';
$api['user_key'] = $input['user_key'];
$api['album_id']    = $input['album_id'];
$api['user_id']     = $input['user_id'];
$api['shop_id']     = $input['shop_id'];
$api['buy_as']      = $input['buy_as'];

print_xml($api);

// Garbage Collection
p4c_close(DB_HOST);
	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>