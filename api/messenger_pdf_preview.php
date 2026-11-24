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

if (!class_exists('Imagick')) {
    http_response_code(503);
    p4c_close(DB_HOST);
    exit;
}

try {
    $im = new \Imagick($file_path.'[0]');
    $im->setImageFormat('jpg');
    $blob = $im->getImageBlob();
    $im->clear();
    unset($im);

    header("Content-Length: " . strlen($blob));
    header('Content-type: image/jpeg');
    header('Content-transfer-encoding: binary');
    echo $blob;
} catch (\Throwable $e) {
    $class_errorlog->log('PDF-Preview fehlgeschlagen: ' . $e->getMessage(), __FILE__, __LINE__);
    http_response_code(500);
}

// Garbage Collection
p4c_close(DB_HOST);
	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>