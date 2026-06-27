<?php

/**
 * Liste mit allen Filmen
 */

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

header('Content-Type: text/html; charset=utf-8');

// Wenn eine Darsteller angegeben wurde
    // Die Anfrage an GET "actor" kann Ende 2018 gelöscht werdn
if(isset($_GET['actor']) AND $_GET['actor'] == 'me') {
    /*
    $merchant_id = p4c_result($rs_merchant, 0);

    // Alle Filme vom Amateur holen
    $rs_movies_online = p4c_query("SELECT `checksum`, `file_id`, `actor_id`, `online_at` FROM `movies_online` WHERE `status`!='deleted' AND `merchant_id`='".abs($merchant_id)."';",__FILE__,__LINE__);
    $number_of_movies = p4c_num_rows($rs_movies_online);
    if($number_of_movies == 0) {
        $api['error'] = 'no movies found';
        print_xml($api);
    } else {
        $api['number_of_movies'] = $number_of_movies;
        $api['movies'] = array();
        while($movie_obj = p4c_fetch_object($rs_movies_online)) {
            $api['movies'][] = array(
                'movie_id'      => $movie_obj->file_id,
                'actor_id'      => $movie_obj->actor_id,
                'checksum'      => $movie_obj->checksum,
                'online_since'  => date("c", strtotime($movie_obj->online_at))
            );
        }
    }
    */
} else if(isset($_GET['actor_id'])) {
        
    $actor_id = abs($_GET['actor_id']);        

    
    /*
     * Pfad zur temporären Datei
     * =======================================
     * Um die DB zu entlasten wird eine Datei erstellt in der die Ausgabe gespeichert und ausgelesen wird.
     * Die Datei wird in regelmäßigen abständen automatisch aktualisiert.
     */
    $temp_file = API_DIR.'/temp/get_movies_by_actor_id/'.$actor_id.'.tmp';

    // Änderungszeitpunkt der temporären Datei
    if (file_exists($temp_file)) {
        $temp_file_time = filemtime($temp_file);
    } else {
        $temp_file_time = 0;
    }

    // Wenn Datei nicht existiert oder die letzte Änderung der Datei älter ist als 50 Minuten
    if (!file_exists($temp_file) OR $temp_file_time  < strtotime("-50 minutes")) {

        // Alle Filme vom Amateur holen
        #$rs_movies_online = p4c_query("SELECT `checksum`, `file_id`, `actor_id`, `online_at` FROM `movies_online` WHERE `status`!='deleted' AND `actor_id`='".abs($actor_id)."';",__FILE__,__LINE__);
        /*
        $rs_movies_online = p4c_query("SELECT `checksum`, `file_id`, `actor_id`, `online_at`, `status`, `visible_for_website` FROM `movies_online` WHERE
            `actor_id`='".abs($actor_id)."' AND
            (
                `visible_for_website` = '". p4c_escape_string($domain)."' OR
                `visible_for_website` = 'public'
            )
        ",__FILE__,__LINE__);
        */
        $rs_movies_online = p4c_query("SELECT `checksum`, `file_id`, `actor_id`, `online_at`, `status`, `visible_for_website` FROM `movies_online` WHERE
            `actor_id`='".abs($actor_id)."' ",__FILE__,__LINE__);

        $number_of_movies = p4c_num_rows($rs_movies_online);
        if($number_of_movies == 0) {
            $api['error'] = 'no movies found';
            // Temporäre Datei erstellen
            file_put_contents($temp_file,json_encode($api));
            print_xml($api);
        } else {
            $api['number_of_movies'] = $number_of_movies;
            $api['movies'] = array();
            while($movie_obj = p4c_fetch_object($rs_movies_online)) {
                
                // Wenn Film auf Website angezeigt werden darf
                if ($movie_obj->visible_for_website == 'public' OR $domain == $movie_obj->visible_for_website) {
                    $api['movies'][] = array(
                        'movie_id'      => $movie_obj->file_id,
                        'actor_id'      => $movie_obj->actor_id,
                        'checksum'      => $movie_obj->checksum,
                        'online_since'  => date("c", strtotime($movie_obj->online_at)),
                        'status'        => $movie_obj->status,
                        'domain'        => $movie_obj->visible_for_website
                    );
                } else {
                    $api['movies'][] = array(
                        'movie_id'      => $movie_obj->file_id,
                        'actor_id'      => $movie_obj->actor_id,
                        'checksum'      => $movie_obj->checksum,
                        'online_since'  => date("c", strtotime($movie_obj->online_at)),
                        'status'        => 'deleted',
                        'domain'        => $movie_obj->visible_for_website
                    );
                }
            }
        
            // Temporäre Datei erstellen
            file_put_contents($temp_file,json_encode($api));
        }    
    // Wenn temporäre Datei existiert
    } else {
        $api = json_decode(file_get_contents($temp_file), true);    
    }

        

// Wenn keine Darsteller angegeben wurde - Alle Filme auflisten
} else {
    $api['error'] = 'no actor selected';
    print_xml($api);
}