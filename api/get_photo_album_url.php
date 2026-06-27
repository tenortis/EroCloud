<?php

/**
 * Download Fotoalbum X
 */

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

// Prüfen ob der user_key angegeben wurde
if (!isset($_GET['user_key'])) {
    $api['error'] = 'no user_key received';
    print_xml($api);
}

// Prüfen ob der album_id existiert
if (!isset($_GET['album_id'])) {
    $api['error'] = 'no movie_id received';
    print_xml($api);
}

// Prüfen URL für Streaming oder Download generiert werden soll
if (!isset($_GET['buy_as']) OR ($_GET['buy_as'] != 'streaming' AND $_GET['buy_as'] != 'download')) {
    $api['error'] = 'no buy_as received';
    print_xml($api);
}

$user_key = $_GET['user_key'];

// Prüfen ob der user_streaming_key existiert
$rs_user_key = p4c_query("SELECT * FROM `photo_albums_access` WHERE `user_key` = '". p4c_escape_string($user_key)."' LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_user_key) == 0) {
    $api['error'] = 'access denid';
    print_xml($api);
}

$album_id = p4c_escape_string($_GET['album_id']);

$rs_album = p4c_query("SELECT * FROM `photo_albums` WHERE `album_id`='".$album_id."' LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_album) == 0) {
    $api['error'] = 'album_id not exists';
    print_xml($api);;   
}

// Das Token ist nur 30 Minuten gültig. Danach kann das Album von diesem Kunden nicht mehr direkt per URL aufgerufen werden
$album_access_obj = p4c_fetch_object($rs_user_key);
$access_token = $album_access_obj->access_token;

// Wenn acces_token älter als 30 Minuten, dann aktualisieren
if ($album_access_obj->access_token_datetime < date("Y-m-d H:i:s")) {
    $access_token = randomString(10);
    
    p4c_query("UPDATE `photo_albums_access` SET 
        `access_token` = '".$access_token."',
        `access_token_datetime` = '".date("Y-m-d H:i:s", strtotime("+30 minutes"))."'
    WHERE `user_key` = '". p4c_escape_string($user_key)."' LIMIT 1;",__FILE__,__LINE__);
}

if ($_GET['buy_as'] == 'download') {
    $api['album_url'] = API_URL.'/PhotoAlbumDownloader/'.$user_key.'/'.$album_id.'/'.$access_token;
    
    $rs_photos = p4c_query("SELECT * FROM `photo_albums_photos` WHERE `album_id`='". p4c_escape_string($album_id)."' AND `rejected`='0' ORDER BY `id` DESC;",__FILE__,__LINE__);
    $count_photos = p4c_num_rows($rs_photos);

    if($count_photos > 0) {
        $api['number_of_photos'] = $count_photos;
        $api['album_id'] = $album_id;
        $api['photos'] = array();
        while($album_obj = p4c_fetch_object($rs_photos)) {
            $api['photos'][] = array(
                'file_id'       => $album_obj->file_id,
                'file_name'     => $album_obj->filename,
                'photo_url'     => API_URL.'/MyAlbumPhoto/'.$user_key.'/'.$access_token.'/'.$album_obj->file_id
            );
        }
    }
}

print_xml($api);

// Garbage Collection
p4c_close(DB_HOST);
	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>