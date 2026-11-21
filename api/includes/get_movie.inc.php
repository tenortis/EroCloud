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
if (is_file($temp_file)) {
    $temp_file_time = @filemtime($temp_file);
} else {
    $temp_file_time = 0;
}

// tempfile löschen wenn letztes Update zu alt ist
if (is_file($temp_file) AND $temp_file_time < strtotime("-7 hours")) {
    @unlink($temp_file);
}

$cached_api = null;
if (is_file($temp_file) && $temp_file_time >= strtotime("-50 Minutes")) {
    $raw_temp = @file_get_contents($temp_file);
    if (is_string($raw_temp) && $raw_temp !== '') {
        $decoded_temp = json_decode($raw_temp, true);
        if (
            is_array($decoded_temp)
            && array_key_exists('visible_for_website', $decoded_temp)
            && array_key_exists('raw_status', $decoded_temp)
        ) {
            $cached_api = $decoded_temp;
        }
    }
    if ($cached_api === null) {
        @unlink($temp_file);
    }
}

// Wenn tempfile nicht existiert, altes Format hatte oder älter ist als 50 Minuten
if ($cached_api === null) {
    $rs_movie = p4c_query("SELECT `id` FROM `movies_online` WHERE `file_id`='". p4c_escape_string($movie_id)."' LIMIT 1; ",__FILE__,__LINE__);

    if (!$rs_movie || p4c_num_rows($rs_movie) == 0) {
        $api['error'] = 'movie not exists';
        print_xml($api);
    } else {
        $movie_obj = p4c_fetch_object($rs_movie);

        $m = new MovieOnline($mysql, $movie_obj->id);

        if ($m->field('id') == '') {
            $api['error'] = 'movie_id not exists';
            print_xml($api);
        }

        $api['movie_id']            = $m->field('file_id');
        $api['actor_id']            = $m->field('actor_id');
        $api['checksum']            = $m->field('checksum');
        $api['resolution']          = $m->field('resolution');
        $api['playtime_string']     = $m->field('playtime_string');
        $api['playtime_seconds']    = $m->field('playtime_seconds');
        $api['fsk16']               = API_URL.'/PlayerPoster/FSK16/'.$m->field('file_id');
        $api['fsk18']               = API_URL.'/PlayerPoster/FSK18/'.$m->field('file_id');
        $api['title']               = $m->field('title');
        $api['description']         = $m->field('description');
        $api['movie_language']      = $m->field('movie_language');
        $api['meta_title']          = $m->field('meta_title');
        $api['meta_description']    = $m->field('meta_description');
        $api['seo_url']             = $m->field('seo_url');
        $api['online_since']        = date("c", strtotime((string)$m->field('online_at')));
        $api['amount_second']       = $m->field('amount_second');
        $api['amount_webmaster']    = $m->field('amount_webmaster');
        $api['as_download']         = $m->field('as_download');
        $api['amount_download']     = $m->field('amount_download');
        $api['categories']          = $m->field('category_slave');
        $api['visible_for_website'] = (string)$m->field('visible_for_website');
        $api['raw_status']          = (string)$m->field('status');

        // Temporäre Datei mit neutralem Rohstatus erstellen
        $encoded_json = json_encode($api, defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0);
        if ($encoded_json !== false) {
            $temp_dir = dirname($temp_file);
            if (!is_dir($temp_dir)) {
                @mkdir($temp_dir, 0777, true);
            }
            @file_put_contents($temp_file, $encoded_json, LOCK_EX);
        }
    }
} else {
    $api = $cached_api;
}

// Effektiven Status und optionale Domain-Zuordnung dynamisch anhand der anfragenden $domain auflösen
if (!isset($api['error'])) {
    $vis_website = trim((string)($api['visible_for_website'] ?? ''));
    $raw_status  = (string)($api['raw_status'] ?? ($api['status'] ?? 'active'));
    $norm_vis    = preg_replace('/^www\./', '', strtolower(rtrim($vis_website, '/')));
    $norm_req    = preg_replace('/^www\./', '', strtolower(rtrim(trim((string)($domain ?? '')), '/')));

    if ($norm_vis === 'public') {
        $api['status'] = $raw_status;
    } elseif ($norm_vis !== '' && $norm_req === $norm_vis) {
        $api['domain'] = $domain;
        $api['status'] = $raw_status;
    } else {
        $api['status'] = 'deleted';
    }
}

// Interne Cache-Felder vor der Veröffentlichung entfernen
unset($api['visible_for_website'], $api['raw_status']);    