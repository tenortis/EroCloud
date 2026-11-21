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
        
    $actor_id  = abs((int)$_GET['actor_id']);
    $reqDomain = preg_replace('/^www\./', '', strtolower(rtrim(trim((string)($domain ?? '')), '/')));

    /*
     * Pfad zur temporären Datei
     * =======================================
     * Um die DB zu entlasten, wird die ungefilterte Rohliste des Darstellers (cache_v = 2 inkl. raw_status & domain)
     * für 50 Minuten zwischengespeichert und der effektive Status ('active'/'blocked'/'deleted') bei jedem Abruf
     * dynamisch gegen die anfragende $domain geprüft. Ältere Cache-Dateien ohne cache_v = 2 werden automatisch verworfen.
     */
    $temp_file = API_DIR.'/temp/get_movies_by_actor_id/'.$actor_id.'.tmp';

    if (is_file($temp_file)) {
        $temp_file_time = (int)@filemtime($temp_file);
    } else {
        $temp_file_time = 0;
    }

    $cached_data = null;
    if ($temp_file_time > 0 && $temp_file_time >= strtotime("-50 minutes")) {
        $raw_temp = @file_get_contents($temp_file);
        if (is_string($raw_temp) && $raw_temp !== '') {
            $decoded_temp = json_decode($raw_temp, true);
            if (
                is_array($decoded_temp)
                && isset($decoded_temp['cache_v'])
                && (int)$decoded_temp['cache_v'] === 2
            ) {
                $cached_data = $decoded_temp;
            }
        }
        if ($cached_data === null) {
            @unlink($temp_file);
        }
    }

    // Wenn Datei nicht existiert, altes Format hatte oder älter ist als 50 Minuten -> aus DB neu aufbauen
    if ($cached_data === null) {
        $rs_movies_online = p4c_query("SELECT `checksum`, `file_id`, `actor_id`, `online_at`, `status`, `visible_for_website` FROM `movies_online` WHERE `actor_id`='".abs((int)$actor_id)."';", __FILE__, __LINE__);

        $number_of_movies = $rs_movies_online ? (int)p4c_num_rows($rs_movies_online) : 0;
        if ($number_of_movies === 0) {
            $cached_data = array(
                'cache_v' => 2,
                'error'   => 'no movies found',
            );
        } else {
            $raw_movies = array();
            while ($movie_obj = p4c_fetch_object($rs_movies_online)) {
                $raw_movies[] = array(
                    'movie_id'     => (string)$movie_obj->file_id,
                    'actor_id'     => (string)$movie_obj->actor_id,
                    'checksum'     => (string)$movie_obj->checksum,
                    'online_since' => date("c", strtotime((string)$movie_obj->online_at)),
                    'raw_status'   => (string)$movie_obj->status,
                    'domain'       => (string)$movie_obj->visible_for_website,
                );
            }
            $cached_data = array(
                'cache_v'          => 2,
                'number_of_movies' => $number_of_movies,
                'movies'           => $raw_movies,
            );
        }

        $encoded_json = json_encode($cached_data, defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0);
        if ($encoded_json !== false) {
            $temp_dir = dirname($temp_file);
            if (!is_dir($temp_dir)) {
                @mkdir($temp_dir, 0777, true);
            }
            @file_put_contents($temp_file, $encoded_json, LOCK_EX);
        }
    }

    if (isset($cached_data['error'])) {
        $api['error'] = $cached_data['error'];
        print_xml($api);
    } else {
        $movies_list = (isset($cached_data['movies']) && is_array($cached_data['movies'])) ? $cached_data['movies'] : array();
        $api['number_of_movies'] = isset($cached_data['number_of_movies']) ? (int)$cached_data['number_of_movies'] : count($movies_list);
        $api['movies'] = array();

        foreach ($movies_list as $m_item) {
            $vis_website = trim((string)($m_item['domain'] ?? ''));
            $vis_norm    = preg_replace('/^www\./', '', strtolower(rtrim($vis_website, '/')));
            $raw_status  = (string)($m_item['raw_status'] ?? ($m_item['status'] ?? 'active'));

            if ($vis_norm === 'public' || ($vis_norm !== '' && $reqDomain === $vis_norm)) {
                $eff_status = $raw_status;
            } else {
                $eff_status = 'deleted';
            }

            $api['movies'][] = array(
                'movie_id'     => $m_item['movie_id'],
                'actor_id'     => $m_item['actor_id'],
                'checksum'     => $m_item['checksum'],
                'online_since' => $m_item['online_since'],
                'status'       => $eff_status,
                'domain'       => $vis_website,
            );
        }
    }

// Wenn keine Darsteller angegeben wurde - Alle Filme auflisten
} else {
    $api['error'] = 'no actor selected';
    print_xml($api);
}