<?php


/*
 * Diese Datei gibt die guuid anhand der E-Mail-Adresse zurück
 * 
 */

header('Access-Control-Allow-Origin: *');

define('SAFE_INC', 1);

include_once("../../config.inc.php");
include_once("common.inc.php");

$json_ary = array();


$email = filter_input(INPUT_GET, 'email', FILTER_SANITIZE_EMAIL);

if (!filter_input(INPUT_GET, 'email', FILTER_VALIDATE_EMAIL)) {
    $json_ary['error'] = 'not found';
    echo json_encode($json_ary);
    p4c_close(DB_HOST);
    exit;
}

$rs_check_user_exists = p4c_query("SELECT `guuid` FROM `user_tracking_users` WHERE `email`='".p4c_escape_string($email)."' ORDER BY `update_email` DESC LIMIT 1;",__FILE__,__LINE__); 

if (p4c_num_rows($rs_check_user_exists) == 0) {
    $json_ary['error'] = 'not found';
    echo json_encode($json_ary);
    p4c_close(DB_HOST);
    exit;
}

$json_ary['guuid'] = p4c_result($rs_check_user_exists,0,0);
echo json_encode($json_ary);

p4c_close(DB_HOST);
exit;