<?php

// Global Site Tag

/*
 * Setzt Tracking "Cookie" => LocalStorage
 * <iframe src="https://api.erocloud.net/gtag/iframe?id=US-[xxxxxxxxxx]&amp;ref=[REFERER]" width="0" height="0" frameborder="0" scrolling="0" sandbox="allow-same-origin allow-scripts" style="border: none;"></iframe>
 */
header('Access-Control-Allow-Origin: *');

define('SAFE_INC', 1);

include_once("../../config.inc.php");
include_once(API_DIR."/common.inc.php");

################################################
### Prüfen ob Zugriff auf Script erlaubt ist ###
################################################

// echo generate_domain_id('lady-julina.com');
function generate_domain_id( $input, $length = 8 ){
    // Create a raw binary sha256 hash and base64 encode it.
    $hash_base64 = base64_encode( hash( 'sha256', $input, true ) );
    // Replace non-urlsafe chars to make the string urlsafe.
    $hash_urlsafe = strtr( $hash_base64, '+/', '-_' );
    // Trim base64 padding characters from the end.
    $hash_urlsafe = rtrim( $hash_urlsafe, '=' );
    // Shorten the string before returning.
    return "US-".substr( $hash_urlsafe, 0, $length );
}

if (!isset($_GET['id'])) {
    exit;
}


$site_id = $_GET['id']; // eindeutige SiteID - wird erstellt aus generate_domain_id() und muss dem Seitenbetreiber mitgeteilt werden
$explod_id = explode('-', $site_id);

if (count($explod_id) != 2) {
    exit;
}

if ($explod_id[0] != 'US' OR strlen($explod_id[1]) != 8) {
    exit;
}

if (!isset($_SERVER['HTTP_REFERER'])) {
    exit;
}

$domain = parse_url($_SERVER['HTTP_REFERER']);

if (!isset($domain['host']) OR generate_domain_id($domain['host']) != $site_id) {
    exit;
}

### ENDE - Prüfen ob Zugriff auf Script erlaubt ist ###


// erster Aufruf dieser Datei
########################################################################
### Unique User ID generieren und beim User im local storage ablegen ###
########################################################################
if (!isset($_GET['guuid'])) {
    
    $ref='';
    if (isset($_GET['ref'])) {
        $ref = $_GET['ref'];
    }
    /*
    $param='';
    if (isset($_GET['param']) AND trim($_GET['param']) != '') {
        $param = $_GET['param'];
    }
    */

    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $param = array(
            'useragent' => $_SERVER['HTTP_USER_AGENT']
        );
    }
    
    if (isset($_GET['param']) AND trim($_GET['param']) != '') {
        $param_json = base64_decode($_GET['param']);
        $param_get_ary = json_decode($param_json, true);        
        $param = array_merge($param, $param_get_ary);
    }
    
    $param = base64_encode(json_encode($param));
    
    function uniqidReal($lenght = 15) {
        // uniqid gives 13 chars, but you could adjust it to your needs.
        if (function_exists("random_bytes")) {
            $bytes = random_bytes(ceil($lenght / 2));
        } elseif (function_exists("openssl_random_pseudo_bytes")) {
            $bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
        } else {
            throw new Exception("no cryptographically secure random function available");
        }
        return substr(bin2hex($bytes), 0, $lenght);
    }

    // global unique user id
    $guuid = uniqidReal();
    
    $output = '<html><head></head><body><script>
    function supports_html5_storage() {
        try {
            if ("localStorage" in window && window["localStorage"] !== null) {
                localStorage.setItem("testitem",true);
                localStorage.removeItem("testitem");
                return true;
            }
        } catch (e) {
            return false;
        }
    }

    if ("localStorage" in window && window["localStorage"] !== null) {

        // no local storage
        if (localStorage.getItem("guuid") === null) {
            var guuid = "'.$guuid.'";
            localStorage.setItem("guuid", guuid);
            //console.log("1 guuid: "+guuid);

        } else {
            var guuid = localStorage.getItem("guuid");
            //console.log("2 guuid: "+guuid);
        }

        var xhttp = new XMLHttpRequest();
        xhttp.open("GET", "https://api.erocloud.net/gtag/iframe?id='.$site_id.'&guuid="+guuid+"&param='.$param.'&referer='.$ref.'");
        xhttp.onreadystatechange = function() {
            if(xhttp.readyState === XMLHttpRequest.DONE) {
                var status = xhttp.status;
                if (status === 0 || (status >= 200 && status < 400)) {
                    //console.log(xhttp.responseText);
                }
            }
        };
        xhttp.send();
    }
    </script></body></html>';

    echo $output;
}


// zweiter Aufruf dieser Datei
###############################################
### uuid und Referer in Datenbank speichern ###
###############################################
if (isset($_GET['guuid'])) {

    $guuid = $_GET['guuid'];
    
    if (trim($guuid) == '') {
        exit;
    }
            
    // User loggen
    $rs_check_user_exists = p4c_query("SELECT * FROM `user_tracking_users` WHERE `guuid`='".p4c_escape_string($guuid)."' LIMIT 1;",__FILE__,__LINE__); 

    $email = '';
    $username = '';
    $last_shop = '';
    $useragent = '';
    
    // Wenn User noch nicht geloggt, jetzt loggen
    if (p4c_num_rows($rs_check_user_exists) == 0) {

        p4c_query("INSERT INTO `user_tracking_users` SET
            `guuid` = '". p4c_escape_string($guuid)."',
            `update_time` = '".date("Y-m-d H:i:s")."' 
        ;",__FILE__,__LINE__);

        $user_id = p4c_insert_id();
    } else {
        $user_obj = p4c_fetch_object($rs_check_user_exists);
        $user_id = $user_obj->id;

        $email = $user_obj->email;
        $username = $user_obj->username;
        $last_shop = $user_obj->last_shop;
        $useragent = $user_obj->useragent;
        
        p4c_query("UPDATE `user_tracking_users` SET
            `update_time` = '".date("Y-m-d H:i:s")."'
        WHERE 
            `guuid` = '". p4c_escape_string($guuid)."'
        LIMIT 1;",__FILE__,__LINE__);
    }

    
    if (isset($_GET['param']) AND trim($_GET['param']) != '') {

        $param_json = base64_decode($_GET['param']);
        $param_ary = json_decode($param_json, true);

        #print_r($param_ary);
        #$class_errorlog->log($guuid.' - '.print_r($param_ary, true),__FILE__,__LINE__);

          
        if (is_array($param_ary) AND isset($param_ary['email']) AND $param_ary['email'] != '') {
            $email = filter_var($param_ary['email'], FILTER_SANITIZE_EMAIL);
        }

        if (is_array($param_ary) AND isset($param_ary['username']) AND $param_ary['username'] != '') {
            $username = strip_tags($param_ary['username']);
        }

        if (is_array($param_ary) AND isset($param_ary['last_shop']) AND $param_ary['last_shop'] != '') {
            $last_shop = $param_ary['last_shop'];
        }

        if (is_array($param_ary) AND isset($param_ary['useragent']) AND $param_ary['useragent'] != '') {
            $useragent = $param_ary['useragent'];
        }
        
        p4c_query("UPDATE `user_tracking_users` SET
            `email`='". p4c_escape_string($email)."',
            `username`='". p4c_escape_string($username)."',
            `last_shop`='". p4c_escape_string($last_shop)."',
            `useragent`='". p4c_escape_string($useragent)."',
            `update_email` = '".date("Y-m-d H:i:s")."'
        WHERE 
            `guuid` = '". p4c_escape_string($guuid)."'
        LIMIT 1;",__FILE__,__LINE__);
    }
        
   
    
    if (isset($_GET['referer'])) {
        // Seitenaufruf loggen
        $ref = urldecode($_GET['referer']);

        // Website-Suche analysieren
        // Refferer-Parameter in einzelne elemente zerlegen
        parse_str($ref, $output);

        /*
        $search_parameters = array(
            'suche'
        );

        foreach ($search_parameters as $value) {
            if (array_key_exists($value, $output)) {
                echo $output[$value];
            }
        }
        */
        // Whitelist
        $whitelist = array(
            '/Video/',
            '/Fotoalbum/'
        );

        // Nur Seiten loggen die auf der Whitelist stehen
        $match = (str_replace($whitelist, '', $ref) != $ref);
        if ($match == true) {
            #$ref = filter_var($ref, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

            $url_id = md5($ref);

            $rs_check_user_exists = p4c_query("SELECT * FROM `user_tracking_sites` WHERE `url_id`='".p4c_escape_string($url_id)."' LIMIT 1;",__FILE__,__LINE__); 

            if (p4c_num_rows($rs_check_user_exists) == 0) {
                p4c_query("INSERT INTO `user_tracking_sites` SET
                    `gtag`='". p4c_escape_string($site_id)."',
                    `url_id`= '". p4c_escape_string($url_id)."',
                    `url` = '". p4c_escape_string($ref)."',
                    `log_datetime` = '".date("Y-m-d H:i:s")."';", __FILE__,__LINE__);
            }

            // Loggen, wann der User die URL aufgerufen hat
            $check_website_access = p4c_query("SELECT * FROM `user_tracking_website_access` WHERE `url_id`='".p4c_escape_string($url_id)."' AND `user_id`='".abs($user_id)."' LIMIT 1;",__FILE__,__LINE__);
            if (p4c_num_rows($check_website_access) == 0) {
                p4c_query("INSERT INTO `user_tracking_website_access` SET
                    `gtag`='". p4c_escape_string($site_id)."',
                    `url_id`= '". p4c_escape_string($url_id)."',
                    `user_id` = '". abs($user_id)."',
                    `log_datetime` = '".date("Y-m-d H:i:s")."';", __FILE__,__LINE__);            
            }
        }
    }
}

p4c_close(DB_HOST);
