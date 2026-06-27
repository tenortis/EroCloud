<?php

/**
 * Liste mit allen Fotoalben
 */

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

header('Content-Type: text/html; charset=utf-8');

// Wenn eine Darsteller angegeben wurde
 if(isset($_GET['actor_id'])) {
    
    $actor_id = abs($_GET['actor_id']);        
    // Alle Filme vom Amateur holen
    $rs_albums_online = p4c_query("SELECT `checksum`, `album_id`, `actor_id`, `online_at`, `status` FROM `photo_albums_online` WHERE `actor_id`='".abs($actor_id)."';",__FILE__,__LINE__);
    $number_of_albums = p4c_num_rows($rs_albums_online);
    if($number_of_albums == 0) {
        $api['error'] = 'no photo albums found';
        print_xml($api);
    } else {
        $api['number_of_albums'] = $number_of_albums;
        $api['albums'] = array();
        while($album_obj = p4c_fetch_object($rs_albums_online)) {
            $api['albums'][] = array(
                'album_id'      => $album_obj->album_id,
                'actor_id'      => $album_obj->actor_id,
                'checksum'      => $album_obj->checksum,
                'online_since'  => date("c", strtotime($album_obj->online_at)),
                'status'        => $album_obj->status
            );
        }
    }

// Wenn keine Darsteller angegeben wurde - Alle Filme auflisten
} else {
    $api['error'] = 'no actor selected';
    print_xml($api);
}