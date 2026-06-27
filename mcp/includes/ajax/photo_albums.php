<?php
 
define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

header('Content-Type: text/html; charset=utf-8');

if (is_logged_in('mcp') === false) {
    exit;
}

$merchant_id = abs($_SESSION['merchant_id']);

$rs_albums = p4c_query("SELECT `photo_albums`.`id`, `album_id`, `checksum`, `number_of_photos`, `title`, `album_checked`, `released`, `username`, `actor_id`
    FROM `photo_albums` LEFT JOIN `actors` ON `photo_albums`.`actor_id`=`actors`.`id` WHERE `photo_albums`.`merchant_id`='".abs($merchant_id)."' AND `photo_albums`.`status`!='deleted' ORDER BY `id` DESC;", __FILE__, __LINE__);


if (p4c_num_rows($rs_albums) > 0) {
    $output = array(
    	"sEcho" => 0,
    	"iTotalRecords" => p4c_num_rows($rs_albums),
    	"iTotalDisplayRecords" => 25,
    	"aaData" => array()
    );

    while($album_ary = p4c_fetch_object($rs_albums)) {

        $url = MCP_URL.'/Photo-Album-Upload?step=2&album_id='.$album_ary->id;
        
        if ($album_ary->number_of_photos == 0) {
            $status_img = '<i title="Fotos hochladen" class="material-symbols-outlined md-35 md-progress">cloud_upload</i>';
        } else if ($album_ary->album_checked == '0000-00-00 00:00:00' AND $album_ary->released == 1) {
            $status_img = '<i title="Wird gepr&uuml;ft" class="material-symbols-outlined md-35 md-progress">cloud_done</i>';
            $url = MCP_URL.'/Photo-Album/'.$album_ary->id;
        } else if ($album_ary->album_checked == '0000-00-00 00:00:00' AND $album_ary->released == 2) {
            $status_img = '<i title="Das Fotoalbum wurde abgelehnt." class="material-symbols-outlined md-35 md-error">cloud_off</i>';
        } else if ($album_ary->album_checked != '0000-00-00 00:00:00') {
            $status_img = '<i title="Online" class="material-symbols-outlined md-35 md-ok">cloud_done</i>';
            $url = MCP_URL.'/Photo-Album/'.$album_ary->id;
        } else {
            $status_img = '';
        }
	   
        #$rs_count_download = p4c_query("SELECT COUNT(*), SUM(`actor_commision`) FROM `movies_access` WHERE `movie_id`='". p4c_escape_string($album_ary->album_id)."' AND `buy_as`='download';",__FILE__,__LINE__);

        #$commision_cent = p4c_result($rs_count_streaming, 0,1) + p4c_result($rs_count_download, 0,1);
        #$commision = number_format(($commision_cent / 100), '2', ',', '.');

    	$row = array();
        $row[] = $status_img;
        $row[] = $album_ary->id;
        $row[] = '<div><img src="'.API_URL.'/PhotoAlbumPoster/FSK16/'.$album_ary->album_id.'?w=100&cs='.$album_ary->checksum.'" /></div><div><img src="'.API_URL.'/PhotoAlbumPoster/FSK18/'.$album_ary->album_id.'?w=100&cs='.$album_ary->checksum.'" /></div>';
        $row[] = '<a href="'.$url.'">'.$album_ary->title.'</a>';
        $row[] = '<a href="'.MCP_URL.'/Actor/'.$album_ary->actor_id.'">'.$album_ary->username.'</a>';
        $row[] = '';
        $row[] = '';
        $row[] = '- EUR';
    
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