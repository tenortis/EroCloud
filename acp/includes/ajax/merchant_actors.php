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

$rs_actors = p4c_query("SELECT 
    `actors`.`id`,
    `status`,
    `actors`.`username` AS `profilename`,
    `profile_image_fsk16`,
    `profile_image_fsk18`,
    `lastonline`
    FROM `actors` WHERE `actors`.`merchant_id`='".abs($_GET['merchant_id'])."' ORDER BY `actors`.`id` DESC", __FILE__, __LINE__);

if (p4c_num_rows($rs_actors) > 0) {
    $output = array(
    	"sEcho" => 0,
    	"iTotalRecords" => p4c_num_rows($rs_actors),
    	"iTotalDisplayRecords" => 25,
    	"aaData" => array()
    );

    while($actor_obj = p4c_fetch_object($rs_actors)) {
 	   
#        $rs_movies = p4c_query("SELECT * FROM `movies` WHERE `merchant_id`='". p4c_escape_string($merchant_ary->id)."' AND `movie_checked`='000-00-00 00:00:00' AND `released`='1';", __FILE__, __LINE__);
#        $rs_movies_online = p4c_query("SELECT * FROM `movies_online` WHERE `merchant_id`='". p4c_escape_string($merchant_ary->id)."';",__FILE__,__LINE__);

        $avatar_fsk16 = API_URL.'/ProfilePicture/'.$actor_obj->profile_image_fsk16.'&w=80';
        $avatar_fsk18 = API_URL.'/ProfilePicture/'.$actor_obj->profile_image_fsk18.'&w=80';
        
        if ($actor_obj->status == 'active') {
            $status = '<img src="'.ACP_URL.'/images/icons/on.png" alt="" title="aktiv" />';
        } else if ($actor_obj->status == 'inactive') {
            $status = '<img src="'.ACP_URL.'/images/icons/off.png" alt="" title="inaktiv" />';
        } else if ($actor_obj->status == 'blocked') {
            $status = '<img src="'.ACP_URL.'/images/icons/off.png" alt="" title="gesperrt" />';
        } else if ($actor_obj->status == 'deleted') {
            $status = '<img src="'.ACP_URL.'/images/icons/off.png" alt="" title="gel&ouml;scht" />';
        }
        
    	$row = array();
        $row[] = $actor_obj->id;
        $row[] = '<a href="'.ACP_URL.'/Actor/'.$actor_obj->id.'">'.$actor_obj->profilename.'</a>';
        $row[] = '<div class="actor_avatar"><img src="'.$avatar_fsk16.'" /></div>';
        $row[] = '<div class="actor_avatar"><img src="'.$avatar_fsk18.'" /></div>';
        $row[] = $status;
        $row[] = date("Y-m-d H:i:s", $actor_obj->lastonline);

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