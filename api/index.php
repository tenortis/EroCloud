<?php

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

$api['version'] = '1.0';

// Wenn keine API-Key angegeben wurde
if(!isset($_GET['api_key'])) {
    $api['error'] = 'api_key not exists';
    print_xml($api);
}

// Wenn keine API angegeben wurde
if(!isset($_GET['api_name'])) {
    $api['error'] = 'api_name not exists';
    print_xml($api);
}

// API-Key wurde angegeben
$api['api_key'] = strip_tags($_GET['api_key']);
$api_key = $api['api_key'];

// Wenn keine Domain angegeben wurde
if(!isset($_GET['domain'])) {
    $api['error'] = 'domain not exists';
    print_xml($api);
}

$domain          = strip_tags($_GET['domain']);
$p4c_shop_id     = abs(filter_input(INPUT_GET, 'p4c_shop_id', FILTER_SANITIZE_NUMBER_INT));
$remote_actor_id = abs(filter_input(INPUT_GET, 'sender_id', FILTER_SANITIZE_NUMBER_INT));

// Domainname säubern
$domain = str_replace(array("http://", "https://"), "", $domain);

/*
 *  Prüfen ob der API-Key existiert.
 *  Wenn ja, speichere die Information in der Session.
 *  Grund: Damit nicht bei jedem Aufruf die DB angesprochen werden muss.
 */
function check_api_key_exists($api_key) {
    global $class_errorlog;
    
    if (!isset($_SESSION['api_key']) OR $_SESSION['api_key'] != $api_key) {
        
        $rs_merchant = p4c_query("SELECT `id` FROM `merchants` WHERE AES_DECRYPT(`api_key`, '".AES_KEY."') = '".p4c_escape_string($api_key)."' LIMIT 1;",__FILE__,__LINE__);

        // API-Key ist nicht korrekt
        if(p4c_num_rows($rs_merchant) == 0) {
            $api['error'] = 'api_key false';
            print_xml($api);
        }
        
        $_SESSION['api_key'] = $api_key;
        //$class_errorlog->log(session_id().' - '.$_SESSION['api_key'],__FILE__,__LINE__);
    }
}

// Alle Filme
if($_GET['api_name'] == 'get_movies') {
    check_api_key_exists($api_key);
    include_once(API_DIR.'/includes/get_movies.inc.php');

// Daten zum Film mit der ID X laden
} else if($_GET['api_name'] == 'get_movie') {
    check_api_key_exists($api_key);
    include_once(API_DIR.'/includes/get_movie.inc.php');

// Alle Film-Kategorien laden
} else if($_GET['api_name'] == 'get_movie_categories') {
    check_api_key_exists($api_key);
    include_once(API_DIR.'/includes/get_movie_categories.inc.php');
    
// Alle Fotoalben
} else if($_GET['api_name'] == 'get_photo_albums') {
    check_api_key_exists($api_key);
    include_once(API_DIR.'/includes/get_photo_albums.inc.php');

// Daten zum Fotoalbum mit der ID X laden
} else if($_GET['api_name'] == 'get_photo_album') {
    check_api_key_exists($api_key);
    include_once(API_DIR.'/includes/get_photo_album.inc.php');

// Thumbnails zum Fotoalbum mit der ID X laden
} else if($_GET['api_name'] == 'get_photo_album_thumbs') {
    check_api_key_exists($api_key);
    include_once(API_DIR.'/includes/get_photo_album_thumbs.inc.php');
    
// (Gekaufte) Fotos zum Fotoalbum mit der ID X laden
} else if($_GET['api_name'] == 'get_my_photo_album') {
    check_api_key_exists($api_key);
    include_once(API_DIR.'/includes/get_my_photo_album.inc.php');
    
// Amateur-online-Status
} else if($_GET['api_name'] == 'get_actors_online_status') {
    check_api_key_exists($api_key);
    include_once(API_DIR.'/includes/get_actors_online_status.inc.php');

// Liste mit allen Darsteller-Kategorien
} else if($_GET['api_name'] == 'get_actor_categories') {
    check_api_key_exists($api_key);
    include_once(API_DIR.'/includes/get_actor_categories.inc.php');
    
// Liste mit allen Profilen
} else if($_GET['api_name'] == 'get_actors') {
    check_api_key_exists($api_key);
    include_once(API_DIR.'/includes/get_actors.inc.php');
    
// Liste mit allen Profilen
} else if($_GET['api_name'] == 'get_my_actors') {
    check_api_key_exists($api_key);
    include_once(API_DIR.'/includes/get_my_actors.inc.php');

// GET Darsteller Profil 
} else if($_GET['api_name'] == 'get_actor') {
    check_api_key_exists($api_key);
    include_once(API_DIR.'/includes/get_actor.inc.php');

// Webseite Zugriff erlauben den Darsteller erlauben
} else if($_GET['api_name'] == 'connect_actor') {
    check_api_key_exists($api_key);
    include_once(API_DIR.'/includes/connect_actor.inc.php');
    
// SET Ca-Members // user who want to see the webcam
} else if($_GET['api_name'] == 'set_cam_member') {
    check_api_key_exists($api_key);
    include_once(API_DIR.'/includes/set_cam_member.inc.php');
    
// Neue Chat Nachricht empfangen
} else if($_GET['api_name'] == 'receive_message') {
    check_api_key_exists($api_key);
    include_once(API_DIR.'/includes/receive_message.inc.php');
    
// Chat Nachricht updaten
} else if($_GET['api_name'] == 'update_message') {
    check_api_key_exists($api_key);
    include_once(API_DIR.'/includes/update_message.inc.php');

// Verbindung zw. EroCloud und Webseite herstellen (DB: `messenger_sync`)
} else if($_GET['api_name'] == 'check_api_key') {
    check_api_key_exists($api_key);
    include_once(API_DIR.'/includes/check_api_key.inc.php');
}

#echo '<pre>';
#print_r($api);
#echo '</pre>';

print_xml($api);

// Garbage Collection
p4c_close(DB_HOST);
	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>