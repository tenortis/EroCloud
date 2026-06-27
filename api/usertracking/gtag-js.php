<?php

// Global Site Tag

/*
 * Setzt Tracking "Cookie" => LocalStorage
 * <iframe referrerpolicy="origin-when-cross-origin" sandbox="allow-same-origin allow-scripts" frameborder="0" style="width:0px; height:0px; overflow:hidden; border:none;" src="https://api.erocloud.net/gtag/js?id=US-[*********]"></iframe>
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

$param='';
if (isset($_GET['param']) AND trim($_GET['param']) != '') {
    $param=$_GET['param'];
}

    
$output = 'document.addEventListener ("DOMContentLoaded", () => {
    var body = document.getElementsByTagName("body")[0];
    var substack = document.createElement("iframe");

    substack.src = "https://api.erocloud.net/gtag/iframe?id='.$site_id.'&param='.$param.'&ref="+encodeURIComponent(window.location.href);
    substack.width = "0";
    substack.height = "0";
    substack.frameBorder ="0";
    substack.scrolling = "0";
    substack.style.border= "none";
    substack.sandbox = "allow-same-origin allow-scripts";
    
    body.append(substack);
})';

echo $output;


p4c_close(DB_HOST);
