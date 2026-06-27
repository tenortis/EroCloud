<?php

define('SAFE_INC', 1);

$session_write_close = true;

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

$actor_id = abs($_GET['actor_id']);
$merchant_id = abs($_GET['merchant_id']);
$file_name = preg_replace( '/[^a-z0-9.]/i', '', $_GET['filename']);

$file_path = PROFILE_IMAGE_PATH.'/'.MERCHANT_DEFAULT_DIR.'/'.$merchant_id.'/messenger/'.$actor_id.'/'.$file_name;

if (!file_exists($file_path)) {
    exit;
}

$size = filesize($file_path);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header("Content-Type: application/force-download");
header('Content-Disposition: attachment; filename="'.$file_name.'"');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: '.$size);
ob_clean();
flush();
readfile($file_path); 

// Garbage Collection
p4c_close(DB_HOST);
	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>
