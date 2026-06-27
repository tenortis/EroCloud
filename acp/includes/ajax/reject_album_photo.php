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

if (is_logged_in('acp') === false) {
    exit;
}

$rejected = 1;
if (isset($_POST['rejected']) AND $_POST['rejected'] == '0') {
    $rejected = 0;
}

$file_id = preg_replace( '/[^a-z0-9]/i', '', $_POST['photo_id']);

$send_jason = array();

$rs_photo = p4c_query("SELECT * FROM `photo_albums_photos` INNER JOIN `photo_albums` ON `photo_albums_photos`.`album_id`=`photo_albums`.`album_id` WHERE
    `file_id`='". p4c_escape_string($file_id)."' LIMIT 1;",__FILE__,__LINE__);

if (p4c_num_rows($rs_photo) == 0) {
    file_error("Das Foto existiert nicht.");
}

p4c_query("UPDATE `photo_albums_photos` SET `rejected` = '".abs($rejected)."' WHERE `file_id`='". p4c_escape_string($file_id)."' LIMIT 1;",__FILE__,__LINE__);

echo json_encode($send_jason);

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());


?>