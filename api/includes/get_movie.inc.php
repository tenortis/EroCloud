<?php

/**
 * Daten zum Film X
 */

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

header('Content-Type: text/html; charset=utf-8');

// Wenn keine movie_id angegeben wurde
if(!isset($_GET['movie_id'])) {
    $api['error'] = 'movie_id not exists';
    print_xml($api);
}

$movie_id = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['movie_id']);


/*
 * Pfad zur temporären Datei
 * =======================================
 * Um die DB zu entlasten wird eine Datei erstellt in der die Ausgabe gespeichert und ausgelesen wird.
 * Die Datei wird in regelmäßigen abständen automatisch aktualisiert.
 */
$temp_file = API_DIR.'/temp/get_movie/'.$movie_id.'.tmp';

// Änderungszeitpunkt der temporären Datei
if (file_exists($temp_file)) {
    $temp_file_time = filemtime($temp_file);
} else {
    $temp_file_time = 0;
}

// tempfile löschen wenn letztes Update zu alt ist
if (file_exists($temp_file) AND $temp_file_time < strtotime("-7 hours")) {
    unlink($temp_file);
}

// Wenn tempfile nicht existiert oder die letzte Änderung der Datei älter ist als 50 Minuten
if (!file_exists($temp_file) OR $temp_file_time < strtotime("-50 Minutes")) {
    
    // Film n vom Amateur aus DB holen
    /*
    $rs_movie = p4c_query("SELECT `id` FROM `movies_online` WHERE `file_id`='". p4c_escape_string($movie_id)."' AND
        (
        `visible_for_website` = '". p4c_escape_string($domain)."' OR
        `visible_for_website` = 'public'
        )
    LIMIT 1; ",__FILE__,__LINE__);
    */
    
    $rs_movie = p4c_query("SELECT `id` FROM `movies_online` WHERE `file_id`='". p4c_escape_string($movie_id)."' LIMIT 1; ",__FILE__,__LINE__);

    
    if(p4c_num_rows($rs_movie) == 0 ) {
        $api['error'] = 'movie not exists';
        print_xml($api);
    } else {
        $movie_obj = p4c_fetch_object($rs_movie);

        $m = new MovieOnline($mysql,$movie_obj->id);

        if ($m->field('id') == '') {
            $api['error'] = 'movie_id not exists';
            print_xml($api);
        }

        $api['movie_id']        = $m->field('file_id');
        $api['actor_id']        = $m->field('actor_id');
        $api['checksum']        = $m->field('checksum');
        $api['resolution']      = $m->field('resolution');
        $api['playtime_string'] = $m->field('playtime_string');
        $api['playtime_seconds']= $m->field('playtime_seconds');
        $api['fsk16']           = API_URL.'/PlayerPoster/FSK16/'.$m->field('file_id');
        $api['fsk18']           = API_URL.'/PlayerPoster/FSK18/'.$m->field('file_id');
        $api['title']           = $m->field('title');
        $api['description']     = $m->field('description');
        $api['movie_language'] =  $m->field('movie_language');
        $api['meta_title']      = $m->field('meta_title');
        $api['meta_description']= $m->field('meta_description');
        $api['seo_url']         = $m->field('seo_url');
        $api['online_since']    = date("c", strtotime($m->field('online_at')));
        $api['amount_second']   = $m->field('amount_second');
        $api['amount_webmaster']= $m->field('amount_webmaster');
        $api['as_download']     = $m->field('as_download');
        $api['amount_download'] = $m->field('amount_download');
        $api['categories']      = $m->field('category_slave');
        $api['visible_for_website'] = $m->field('visible_for_website');

        // Wenn Film auf Website angezeigt werden darf
        if ($m->field('visible_for_website') == 'public') {
            $api['status'] = $m->field('status');
        } else {
            $api['status'] = 'deleted';
        }   
        
        // Temporäre Datei erstellen
        file_put_contents($temp_file,json_encode($api));

        // Wenn Film zur Domain gehört
        if ($domain == $m->field('visible_for_website')) {
            $api['domain'] = $domain;
            $api['status'] = $m->field('status');
        }
        
    }    
// Wenn temporäre Datei existiert
} else {
    $api = json_decode(file_get_contents($temp_file), true);

    // Wenn Film auf Website angezeigt werden darf
    if ($api['visible_for_website'] == 'public') {
    
    } else if ($domain == $api['visible_for_website']) {
        $m = new MovieOnline($mysql,$api['movie_id']);
        $api['domain'] = $domain;
        $api['status'] = $m->field('status');
    } else {
        $api['status'] = 'deleted';
    } 
    

}

// Auss Array entfernen um es nicht zu veröffentlichen. Nur für interne Verarbeitung
unset($api['visible_for_website']);    