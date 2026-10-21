<?php

/**
 * Daten zum Fotoalbum X
 */

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

header('Content-Type: text/html; charset=utf-8');

// Wenn keine album_id angegeben wurde
if(!isset($_GET['album_id'])) {
    $api['error'] = 'album_id not exists';
    print_xml($api);
}


$album_id = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['album_id']);

/*
 * Pfad zur temporären Datei
 * =======================================
 * Um die DB zu entlasten wird eine Datei erstellt in der die Ausgabe gespeichert und ausgelesen wird.
 * Die Datei wird in regelmäßigen abständen automatisch aktualisiert.
 */
$temp_file = API_DIR.'/temp/get_photo_album/'.$album_id.'.tmp';

// Änderungszeitpunkt der temporären Datei
if (is_file($temp_file)) {
    $temp_file_time = @filemtime($temp_file);
} else {
    $temp_file_time = 0;
}

$cached_api = null;
if (is_file($temp_file) AND $temp_file_time >= strtotime("-50 minutes")) {
    $raw_temp = @file_get_contents($temp_file);
    if (is_string($raw_temp) && $raw_temp !== '') {
        $decoded_temp = json_decode($raw_temp, true);
        if (is_array($decoded_temp) && isset($decoded_temp['album_id'])) {
            $cached_api = $decoded_temp;
        }
    }
    if ($cached_api === null) {
        @unlink($temp_file);
    }
}

// Wenn Datei nicht existiert, ungültig war oder die letzte Änderung der Datei älter ist als 50 Minuten
if ($cached_api === null) {

    // Alle Fotoalben vom Amateur holen
    $rs_album = p4c_query("SELECT `id` FROM `photo_albums_online` WHERE `album_id`='". p4c_escape_string($album_id)."' LIMIT 1; ",__FILE__,__LINE__);
    if(p4c_num_rows($rs_album) == 0) {
        $api['error'] = 'photo album not exists';
        print_xml($api);
    } else {

        $album_obj = p4c_fetch_object($rs_album);

        $a = new PhotoAlbumOnline($mysql,$album_obj->id);

        if ($a->field('id') == '') {
            $api['error'] = 'album_id not exists';
            print_xml($api);
        }

        $api['album_id']        = $a->field('album_id');
        $api['actor_id']        = $a->field('actor_id');
        $api['checksum']        = $a->field('checksum');
        $api['fsk16']           = API_URL.'/PhotoAlbumPoster/FSK16/'.$a->field('album_id');
        $api['fsk18']           = API_URL.'/PhotoAlbumPoster/FSK18/'.$a->field('album_id');
        $api['title']           = $a->field('title');
        $api['description']     = $a->field('description');
        $api['meta_title']      = $a->field('meta_title');
        $api['meta_description']= $a->field('meta_description');
        $api['seo_url']         = $a->field('seo_url');
        $api['online_since']    = date("c", strtotime($a->field('online_at')));
        $api['amount_webmaster']= $a->field('amount_webmaster');
        $api['amount_download'] = $a->field('amount_download');
        $api['categories']      = $a->field('category_slave');
        $api['number_of_photos']= $a->field('number_of_photos');
        $api['status']          = $a->field('status');
        
        // Temporäre Datei erstellen
        $encoded_json = json_encode($api, defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0);
        if ($encoded_json !== false) {
            $temp_dir = dirname($temp_file);
            if (!is_dir($temp_dir)) {
                @mkdir($temp_dir, 0777, true);
            }
            @file_put_contents($temp_file, $encoded_json, LOCK_EX);
        }
    }    
// Wenn gültige temporäre Datei existiert
} else {
    $api = $cached_api;    
}