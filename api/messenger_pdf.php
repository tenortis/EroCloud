<?php

define('SAFE_INC', 1);

$session_write_close = true;

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

$actor_id = (isset($_GET['actor_id']) && is_scalar($_GET['actor_id'])) ? abs((int)$_GET['actor_id']) : 0;
$merchant_id = (isset($_GET['merchant_id']) && is_scalar($_GET['merchant_id'])) ? abs((int)$_GET['merchant_id']) : 0;
$file_name = (isset($_GET['filename']) && is_string($_GET['filename'])) ? (string)preg_replace('/[^a-z0-9._-]/i', '', $_GET['filename']) : '';
$file_name = basename($file_name);

$file_path = PROFILE_IMAGE_PATH.'/'.MERCHANT_DEFAULT_DIR.'/'.$merchant_id.'/messenger/'.$actor_id.'/'.$file_name;

if ($file_name === '' || $file_name === '.' || $file_name === '..' || !is_file($file_path)) {
    p4c_close(DB_HOST);
    exit;
}

$size = (int)@filesize($file_path);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header("Content-Type: application/force-download");
header('Content-Disposition: attachment; filename="'.$file_name.'"');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: '.$size);
if (ob_get_level() > 0) {
    @ob_clean();
}
flush();
readfile($file_path); 

// Garbage Collection
p4c_close(DB_HOST);
	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>
