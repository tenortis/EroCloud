<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
 
define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(ACP_DIR."/common.inc.php");

if (is_logged_in('acp') === false) {
    exit;   
}

function private_substr($str, $len=20) {
    if (strlen($str) > $len) {
        $str = substr($str,0,($len-5)).'[...]';
    }
    
    if(mb_detect_encoding($str) != 'UTF-8') {$str = utf8_encode($str);}
    return $str;
}

$rs_points_scoring = p4c_query("SELECT
    `actor_id`, `date`,
    SUM(messenger) AS `messenger`,
    SUM(webcam) AS `webcam`,
    SUM(movie_upload) AS `movie_upload`,
    SUM(photo_album_upload) AS `photo_album_upload`
FROM `points_scoring` GROUP BY `actor_id`", __FILE__, __LINE__);

if (p4c_num_rows($rs_points_scoring) > 0) {
    $output = array(
    	"sEcho" => 0,
    	"iTotalRecords" => p4c_num_rows($rs_points_scoring),
    	"iTotalDisplayRecords" => 25,
    	"aaData" => array()
    );

    while($scoring_obj = p4c_fetch_object($rs_points_scoring)) {

        $rs_actor = p4c_query("SELECT `username` FROM `actors`WHERE `id`='".abs($scoring_obj->actor_id)."' LIMIT 1;",__FILE__,__LINE__);
        
        if (p4c_num_rows($rs_actor) == 0) {
            $actor_name = '';
        } else {
            $actor_obj = p4c_fetch_object($rs_actor);
            $actor_name = $actor_obj->username;
        }
        
    	$row = array();
        $row[] = $actor_name;
        $row[] = $scoring_obj->messenger;
        $row[] = $scoring_obj->webcam;
        $row[] = $scoring_obj->movie_upload;
        $row[] = $scoring_obj->photo_album_upload;
        $row[] = '';
        

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