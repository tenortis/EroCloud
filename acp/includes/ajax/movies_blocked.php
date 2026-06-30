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

$rs_movies = p4c_query("SELECT `id`, `file_id`, `merchant_id`, `actor_id`, `checksum`, `title`, `online_at`, `status`, `convert_status`, `movie_language`, `category_master`, `movie_checked`, `deleted_datetime`, `last_updated_datetime` FROM `movies` WHERE `convert_status` > '1' AND (`released`='2' OR `status`!='active') ORDER BY `id` DESC;", __FILE__, __LINE__);
if (p4c_num_rows($rs_movies) > 0) {
    $output = array(
    	"sEcho" => 0,
    	"iTotalRecords" => p4c_num_rows($rs_movies),
    	"iTotalDisplayRecords" => 25,
    	"aaData" => array()
    
    );
    
    while($movie_ary = p4c_fetch_object($rs_movies)) {
    /*
        if ($movie_ary->blocked == '1') {
            $status_img = '<img src="'.ACP_URL.'/images/icons/off.png" alt="" title="1" />';
        } else if ($movie_ary->blocked == '2') {    
            $status_img = '<img src="'.ACP_URL.'/images/icons/wait.png" alt="" title="2" />';
        } else {
            $status_img = '<img src="'.ACP_URL.'/images/icons/on.png" alt="" title="0" />';
        }
      */  
	$title = $movie_ary->title;
        if(mb_detect_encoding($movie_ary->title, 'UTF-8', false)) {
            $title = utf8_encode($movie_ary->title);
        }
        
        $rs_actors = p4c_query("SELECT `username`  FROM `actors` WHERE
            `id`='".abs($movie_ary->actor_id)."' AND
            `merchant_id` = '".abs($movie_ary->merchant_id)."';",__FILE__,__LINE__);
        
        if (p4c_num_rows($rs_actors) > 0) {
            $actor = '<a href="'.ACP_URL.'/Actor/'.$movie_ary->actor_id.'" target="_blank">'. utf8_decode(p4c_result($rs_actors,0)).'</a>';
        } else {
            $actor = '-';
        }
        
            
        if ($movie_ary->status == 'active' AND $movie_ary->convert_status == 1) {
            $status = '<img src="'.ACP_URL.'/images/icons/on.png" alt="" title="aktiv" class="status" />';
        } else if ($movie_ary->status == 'blocked') {
            $status = '<img src="'.ACP_URL.'/images/icons/off.png" alt="" title="gesperrt" class="status" /> <span style="display:inline-block; background-color: #f0f0f0; border: 1px solid #888; color: #666; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; margin-left:5px;">Gesperrt</span>';
        } else if ($movie_ary->status == 'deleted') {
            $status = '<img src="'.ACP_URL.'/images/icons/off.png" alt="" title="gel&ouml;scht" class="status" /> <span style="display:inline-block; background-color: #fde8e8; border: 1px solid #e53e3e; color: #e53e3e; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; margin-left:5px;">Löschung</span>';
        } else {
            $status = '<img src="'.ACP_URL.'/images/icons/off.png" alt="" title="abgeleht" class="status" /> <span style="display:inline-block; background-color: #ffe8d6; border: 1px solid #dd8047; color: #dd8047; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; margin-left:5px;">Abgelehnt</span>';
        }
        
        if ($movie_ary->category_master == 'porn') {
            $category_master = 'Porno';
        } else if ($movie_ary->category_master == 'fetish') {
            $category_master = 'Fetisch';
        } else {
            $category_master = '-';
        }
        
        $movie_language_ary = array(
            'de' => 'Deutsch',
            'en' => 'Englisch',
            'fr' => 'Franz&ouml;sisch',
            'es' => 'Spanisch',
            'nl' => 'Niederl&auml;ndisch',
            'ru' => 'Russisch',
            'pl' => 'Polnisch'
        );
        
    	$row = array();
        
        #$row[] = $status_img;
        $row[] = '<a href="'.ACP_URL.'/Film-pruefen/'.$movie_ary->id.'">'.$movie_ary->id.'</a>';
        $row[] = '<a href="'.ACP_URL.'/Haendler/'.$movie_ary->merchant_id.'">'.$movie_ary->merchant_id.'</a>';
        $row[] = $status;
        $row[] = '<div><img src="'.API_URL.'/PlayerPoster/FSK16/'.$movie_ary->file_id.'?w=100&cs='.$movie_ary->checksum.'" /></div><div><img src="'.API_URL.'/PlayerPoster/FSK18/'.$movie_ary->file_id.'?w=100&cs='.$movie_ary->checksum.'" /></div>';
        $row[] = $actor;
        
        $online_ts = ($movie_ary->online_at != '0000-00-00 00:00:00' && $movie_ary->online_at != '') ? strtotime($movie_ary->online_at) : 0;
        $online_date_cell = '<span style="display:none;">'.$online_ts.'</span>' . (($online_ts > 0) ? date("d.m.Y H:i:s", $online_ts) : '-');
        $row[] = $online_date_cell;
        
        $action_ts = 0;
        if ($movie_ary->status == 'deleted') {
            if ($movie_ary->deleted_datetime != '0000-00-00 00:00:00') {
                $action_ts = strtotime($movie_ary->deleted_datetime);
            }
        } else {
            if ($movie_ary->movie_checked != '0000-00-00 00:00:00') {
                $action_ts = strtotime($movie_ary->movie_checked);
            } elseif ($movie_ary->last_updated_datetime != '0000-00-00 00:00:00') {
                $action_ts = strtotime($movie_ary->last_updated_datetime);
            }
        }
        $action_date_cell = '<span style="display:none;">'.$action_ts.'</span>' . (($action_ts > 0) ? date("d.m.Y H:i:s", $action_ts) : '-');
        $row[] = $action_date_cell;
        
        $row[] = $movie_language_ary[$movie_ary->movie_language];
        $row[] = $category_master;
        $row[] = '<a href="'.ACP_URL.'/Film-pruefen/'.$movie_ary->id.'">'.utf8_decode($title).'</a>';
   
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