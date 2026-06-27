<?php
 
define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

header('Content-Type: text/html; charset=utf-8');

function file_error($msg) {
    $send_jason = array();
    $send_jason['error'] = $msg;
    echo json_encode($send_jason);	

    p4c_close(DB_HOST);
    p4c_errorlog(error_get_last());
    exit; 
}

if (is_logged_in('mcp') === false) {
    exit;
}

$merchant_id = abs($_SESSION['merchant_id']);

#$rs_movies = p4c_query("SELECT `id`, `convert_status`, `file_id`, `filename`, `title`, `movie_checked`, `released`
#     FROM `movies` WHERE `merchant_id`='".abs($merchant_id)."' ORDER BY `id` DESC", __FILE__, __LINE__);

$file_id = preg_replace( '/[^a-z0-9]/i', '', $_POST['photo_id']);

$send_jason = array();

$rs_photo = p4c_query("SELECT * FROM `photo_albums_photos` INNER JOIN `photo_albums` ON `photo_albums_photos`.`album_id`=`photo_albums`.`album_id` WHERE
    `file_id`='". p4c_escape_string($file_id)."' AND
    `photo_albums_photos`.`merchant_id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__);

if (p4c_num_rows($rs_photo) == 0) {
    file_error("Das Foto existiert nicht.");
}

$photo_obj = p4c_fetch_object($rs_photo);

$filename = PHOTO_ALBUMS_PATH.'/'.$photo_obj->storage_location.'/'.$photo_obj->merchant_id.'/'.$photo_obj->id.'/images/'.$photo_obj->filename;

if (is_file($filename)) {
    if (unlink($filename)) {
        p4c_query("DELETE FROM `photo_albums_photos` WHERE
            `file_id`='". p4c_escape_string($file_id)."' AND
            `photo_albums_photos`.`merchant_id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__);
    } else {
        file_error("Das Foto konnte nicht gel&ouml;scht werden.");    
    }
}


echo json_encode($send_jason);

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());


?>