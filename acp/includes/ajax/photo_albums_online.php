<?php
 
define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(ACP_DIR."/common.inc.php");

if (is_logged_in('acp') === false) {
    exit;
}

$rs_albums = p4c_query("SELECT `photo_albums_online`.`id`, `album_id`, `online_at`, `number_of_photos`, `checksum`, `number_of_photos`, `title`, `album_checked`, `released`, `username`, `actor_id`, `photo_albums_online`.`status`
    FROM `photo_albums_online` LEFT JOIN `actors` ON `photo_albums_online`.`actor_id`=`actors`.`id` ORDER BY `id` DESC;", __FILE__, __LINE__);

if (p4c_num_rows($rs_albums) > 0) {
    $output = array(
    	"sEcho" => 0,
    	"iTotalRecords" => p4c_num_rows($rs_albums),
    	"iTotalDisplayRecords" => 25,
    	"aaData" => array()
    );

    while($album_ary = p4c_fetch_object($rs_albums)) {
        
        /*
        if ($album_ary->number_of_photos == 0) {
            $status_img = '<i title="Fotos hochladen" class="material-symbols-outlined md-35 md-progress">cloud_upload</i>';
        } else if ($album_ary->album_checked != '0000-00-00 00:00:00') {
            $status_img = '<i title="Online" class="material-symbols-outlined md-35 md-ok">cloud_done</i>';
        } else if ($album_ary->album_checked == '0000-00-00 00:00:00' AND $album_ary->released == 1) {
            $status_img = '<i title="Wird gepr&uuml;ft" class="material-symbols-outlined md-35 md-progress">cloud_done</i>';
        } else if ($album_ary->album_checked == '0000-00-00 00:00:00' AND $album_ary->released == 2) {
            $status_img = '<i title="Das Fotoalbum wurde abgelehnt." class="material-symbols-outlined md-35 md-error">cloud_off</i>';
        } else {
            $status_img = '';
        }
        */
        
        if ($album_ary->status == 'active') {
            $status = '<img src="'.ACP_URL.'/images/icons/on.png" alt="" title="aktiv" class="status" />';
        } else if ($album_ary->status == 'blocked') {
            $status = '<img src="'.ACP_URL.'/images/icons/off.png" alt="" title="gesperrt" class="status" />';
        } else if ($album_ary->status == 'deleted') {
            $status = '<img src="'.ACP_URL.'/images/icons/off.png" alt="" title="gel&ouml;scht" class="status" />';
        }
        
        

    	$row = array();
        #$row[] = $status_img;
        $row[] = $status;
        $row[] = $album_ary->id;
        $row[] = '<div><img src="'.API_URL.'/PhotoAlbumPoster/FSK16/'.$album_ary->album_id.'?w=100&cs='.$album_ary->checksum.'" /></div><div><img src="'.API_URL.'/PhotoAlbumPoster/FSK18/'.$album_ary->album_id.'?w=100&cs='.$album_ary->checksum.'" /></div>';
        $row[] = '<a href="'.ACP_URL.'/Actor/'.$album_ary->actor_id.'">'.$album_ary->username.'</a>';
        $row[] = $album_ary->online_at;
        $row[] = $album_ary->number_of_photos;
        $row[] = '<a href="'.ACP_URL.'/Fotoalbum-bearbeiten/'.$album_ary->id.'">'.$album_ary->title.'</a>';

    	$output['aaData'][] = $row;
    }

} else {
    $output = array(
    	"sEcho" => 0,
    	"iTotalRecords" => 0,
    	"iTotalDisplayRecords" => 0,
    	"aaData" => 0
    );
}

echo json_encode($output);


p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());


?>