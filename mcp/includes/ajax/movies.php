<?php
 
define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

header('Content-Type: text/html; charset=utf-8');

if (is_logged_in('mcp') === false) {
    exit;
}

$merchant_id = abs($_SESSION['merchant_id']);

#$rs_movies = p4c_query("SELECT `id`, `convert_status`, `file_id`, `filename`, `title`, `movie_checked`, `released`
#     FROM `movies` WHERE `merchant_id`='".abs($merchant_id)."' ORDER BY `id` DESC", __FILE__, __LINE__);

/*
$rs_movies = p4c_query("SELECT `movies`.`id`, `convert_status`, `file_id`, `checksum`, `filename`, `title`, `movie_checked`, `released`, `username`, `actor_id`, `convert_endtime`
    FROM `movies` LEFT JOIN `actors` ON `movies`.`actor_id`=`actors`.`id` WHERE `movies`.`merchant_id`='".abs($merchant_id)."' AND `movies`.`status`!='deleted' AND `actors`.`status`!='deleted' ORDER BY `id` DESC;", __FILE__, __LINE__);
*/
$rs_movies = p4c_query("SELECT `movies`.`id`, `convert_status`, `file_id`, `checksum`, `filename`, `title`, `movie_checked`, `released`, `actor_id`, `convert_endtime`
    FROM `movies` WHERE `movies`.`merchant_id`='".abs($merchant_id)."' AND `movies`.`status`!='deleted' ORDER BY `id` DESC;", __FILE__, __LINE__);


if (p4c_num_rows($rs_movies) > 0) {
    $output = array(
    	"sEcho" => 0,
    	"iTotalRecords" => p4c_num_rows($rs_movies),
    	"iTotalDisplayRecords" => 25,
    	"aaData" => array()
    );

    while($movie_ary = p4c_fetch_object($rs_movies)) {
        
        $rs_actors = p4c_query("SELECT * FROM `actors` WHERE `id`='".abs($movie_ary->actor_id)."' AND `merchant_id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__);
        if (p4c_num_rows($rs_actors) > 0) {
            
            $actor_obj = p4c_fetch_object($rs_actors);
            
            if ($actor_obj->status == 'deleted') {
                continue;
            }
            
            $username = '<a href="'.MCP_URL.'/Actor/'.$movie_ary->actor_id.'">'.$actor_obj->username.'</a>';
        } else {
            $username = '-';
        }
        
        if ($movie_ary->filename == '') {
            $status_img = '<i title="Film hochladen" class="material-symbols-outlined md-35 md-progress">cloud_upload</i>';
        } else if ($movie_ary->convert_status <= 1) {
            $status_img = '<i title="Wird konvertiert" class="material-symbols-outlined md-35 md-progress">settings</i>';
        } else if ($movie_ary->movie_checked != '0000-00-00 00:00:00') {
            $status_img = '<i title="Online" class="material-symbols-outlined md-35 md-ok">cloud_done</i>';
        } else if ($movie_ary->movie_checked == '0000-00-00 00:00:00' AND $movie_ary->released == 1) {
            $status_img = '<i title="Wird gepr&uuml;ft" class="material-symbols-outlined md-35 md-progress">cloud_done</i>';
        } else if ($movie_ary->movie_checked == '0000-00-00 00:00:00' AND $movie_ary->released == 2) {
            $status_img = '<i title="Der Film wurde abgelehnt." class="material-symbols-outlined md-35 md-error">cloud_off</i>';
        } else if ($movie_ary->convert_status == 2) {
            $status_img = '<i title="Jetzt ver&ouml;ffentlichen" class="material-symbols-outlined md-35 md-progress">cloud_done</i>';
        } else {
            $status_img = '';
        }
	   
        $rs_count_streaming = p4c_query("SELECT COUNT(*), SUM(`actor_commision`) FROM `movies_access` WHERE `movie_id`='". p4c_escape_string($movie_ary->file_id)."' AND `buy_as`='streaming';",__FILE__,__LINE__);
        $rs_count_download = p4c_query("SELECT COUNT(*), SUM(`actor_commision`) FROM `movies_access` WHERE `movie_id`='". p4c_escape_string($movie_ary->file_id)."' AND `buy_as`='download';",__FILE__,__LINE__);

        $commision_cent = p4c_result($rs_count_streaming, 0,1) + p4c_result($rs_count_download, 0,1);
        $commision = number_format(($commision_cent / 100), '2', ',', '.');

        $cs = $movie_ary->checksum;
        /*
        if (trim($cs) == '') {
            $cs = md5($movie_ary->convert_endtime);
        }
         */
        
    	$row = array();
        $row[] = $status_img;
        $row[] = $movie_ary->id;
        $row[] = '<div><img src="'.API_URL.'/PlayerPoster/FSK16/'.$movie_ary->file_id.'?w=100&cs='.$cs.'" /></div><div><img src="'.API_URL.'/PlayerPoster/FSK18/'.$movie_ary->file_id.'?w=100&cs='.$cs.'" /></div>';
        $row[] = '<a href="'.MCP_URL.'/video/'.$movie_ary->id.'">'.$movie_ary->title.'</a>';
        $row[] = $username;
        $row[] = p4c_result($rs_count_streaming, 0,0);
        $row[] = p4c_result($rs_count_download, 0,0);
        $row[] = $commision.' EUR';
    
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