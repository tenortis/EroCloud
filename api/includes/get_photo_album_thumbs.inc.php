<?php

/**
 * Liste mit allen Thumbnails aus dem Album
 */

// https://api.erocloud.net/index.php?api_name=get_photo_album_thumbs&api_key=MRT9NGSS9UACCP8E2EKGAMCISUPVBGGN&domain=test&album_id=078b4e91c5a6d33c2f25c60caf4188c3

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

header('Content-Type: text/html; charset=utf-8');

if (!isset($_GET['album_id'])) {
    $api['error'] = 'album_id not given';
    print_xml($api);
}

$album_id = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['album_id']);

$rs_photos = p4c_query("SELECT * FROM `photo_albums_photos` WHERE `album_id`='". p4c_escape_string($album_id)."' AND `rejected`='0' ORDER BY `id` DESC;",__FILE__,__LINE__);

$count_photos = p4c_num_rows($rs_photos);

if($count_photos == 0) {
    $api['error'] = 'no photos found';
    print_xml($api);
} else {
    
    $is_bought = '';
    
    // Prüfen ob der user_key angegeben wurde
    if (isset($_GET['user_key'])) {

        // Prüfen URL für Streaming oder Download generiert werden soll
        if (isset($_GET['buy_as']) AND ($_GET['buy_as'] == 'streaming' OR $_GET['buy_as'] == 'download')) {
            $user_key = $_GET['user_key'];

            // Prüfen ob der user_streaming_key existiert
            $rs_user_key = p4c_query("SELECT * FROM `photo_albums_access` WHERE `user_key` = '". p4c_escape_string($user_key)."' LIMIT 1;",__FILE__,__LINE__);
            if (p4c_num_rows($rs_user_key) != 0) {

                $access_obj = p4c_fetch_object($rs_user_key);
                $access_token = $access_obj->access_token;
                
                $is_bought = API_URL.'/MyAlbumPhoto/'.$user_key.'/'.$access_token.'/';
            }
        }
    }
    
    
    $api['number_of_photos'] = $count_photos;
    $api['album_id'] = $album_id;
    $api['photos'] = array();
            
    while($album_obj = p4c_fetch_object($rs_photos)) {
        $api['photos'][] = array(
            'file_id'       => $album_obj->file_id,
            'file_name'     => $album_obj->filename,
            'photo_url'     => API_URL.'/AlbumPhoto/'.$album_obj->file_id,
            'my_photo_url'  => $is_bought.$album_obj->file_id
                
        );
    }
}