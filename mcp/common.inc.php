<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
  
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");


include_once(SOURCEDIR."/includes/functions.inc.php");

// SESSION starten
##############################################
session_save_path(TEMP_DIR.'/');
ini_set('session.gc_probability', 1);
ini_set('session.gc_maxlifetime', 10800);
if(!isset($_SESSION)) {p4c_session_start();}


// Error-Handler einbinden
include(SOURCEDIR.'/includes/klassen/errorlog.inc.php');
$class_errorlog = new p4c_errorlog();

// MYSQLi-Klasse einbinden 
include(SOURCEDIR.'/includes/klassen/mysqli.inc.php');
$mysql = new p4c_mysqli(DB_HOST, $config['db_user'], $config['db_pass'], $config['db_name']);
p4c_query("SET NAMES 'utf8';",__FILE__,__LINE__);

// Merchant-Klasse einbinden 
include(SOURCEDIR.'/includes/klassen/merchant.inc.php');

// Movie-Klasse einbinden 
include(SOURCEDIR.'/includes/klassen/movie.inc.php');

// Fotoalbum-Klasse einbinden 
include(SOURCEDIR.'/includes/klassen/PhotoAlbum.inc.php');

// SMTP-Klasse laden
include(SOURCEDIR.'/includes/klassen/sendSMTPmail.inc.php');

// ReCaptcha
include(SOURCEDIR.'/includes/klassen/recaptcha.inc.php');

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

function log_action($string) {
    if (isset($_SESSION['merchant_id'])) {
        $merchant_id = $_SESSION['merchant_id'];
    } else {
        $merchant_id = 0;
    }
    p4c_query("INSERT INTO `merchant_history` SET 
    `merchant_id` = '".abs($merchant_id)."',
    `description` = '".p4c_escape_string($string)."',
    `datetime`    = '".date("Y-m-d H:i:s")."',
    `ip`          = '".p4c_escape_string(GetUserIP())."';",__FILE__,__LINE__);
}

?>