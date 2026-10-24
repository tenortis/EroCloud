<?php

  
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

include_once(SOURCEDIR."/includes/functions.inc.php");

// SESSION starten
##############################################
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_save_path(TEMP_DIR.'/');
    ini_set('session.gc_probability', 1);
}
if(!isset($_SESSION) && session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {p4c_session_start();}

// Error-Handler einbinden
include_once(SOURCEDIR.'/includes/klassen/errorlog.inc.php');
$class_errorlog = new p4c_errorlog();

// MYSQLi-Klasse einbinden 
include_once(SOURCEDIR.'/includes/klassen/mysqli.inc.php');
$mysql = new p4c_mysqli(DB_HOST, $config['db_user'], $config['db_pass'], $config['db_name']);
p4c_query("SET NAMES 'utf8mb4';",__FILE__,__LINE__);

// Merchant-Klasse einbinden 
include_once(SOURCEDIR.'/includes/klassen/merchant.inc.php');

// Movie-Klasse einbinden 
include_once(SOURCEDIR.'/includes/klassen/movie.inc.php');

// Movie-Klasse einbinden 
include_once(SOURCEDIR.'/includes/klassen/PhotoAlbum.inc.php');

// SMTP-Klasse laden
include_once(SOURCEDIR.'/includes/klassen/sendSMTPmail.inc.php');

/**
 * Schutz vor Hacks
 **/

if (isset($_GET['referer'])) {
    $_SERVER['HTTP_REFERER'] = $_GET['referer'];
}

// Cookie manipulation verhindern 
if(isset($_COOKIE["PHPSESSID"]) AND $_COOKIE["PHPSESSID"] == '') {
    $_COOKIE["PHPSESSID"] = "0x";
}
