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

$rs_movies = p4c_query("SELECT `id`, `file_id`, `quality`, `merchant_id`, `actor_id`, `checksum`, `title`, `description`, `online_at`, `movie_language`, `movie_checked`, `status`, `category_master` FROM `movies_online` ORDER BY `id` DESC", __FILE__, __LINE__);
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
   
        $select_quality_ary = array(
            'sd' => 'SD',
            'hd' => 'HD',
            'fhd' => 'Full HD',
            '2k' => '2K',
            '4k' => '4K',
            '6k' => '6K'
        );

        if (empty($movie_ary->quality)) {
            $quality = '';
        } else {
            $quality = $select_quality_ary[$movie_ary->quality];
        }
        
        $rs_actors = p4c_query("SELECT `username`  FROM `actors` WHERE
            `id`='".abs($movie_ary->actor_id)."' AND
            `merchant_id` = '".abs($movie_ary->merchant_id)."';",__FILE__,__LINE__);
        
        if (p4c_num_rows($rs_actors) > 0) {
            $actor = '<a href="'.ACP_URL.'/Actor/'.$movie_ary->actor_id.'" target="_blank">'. utf8_decode(p4c_result($rs_actors,0)).'</a>';
        } else {
            $actor = '-';
        }
            
        if ($movie_ary->status == 'active') {
            $status = '<img src="'.ACP_URL.'/images/icons/on.png" alt="" title="aktiv" class="status" />';
        } else if ($movie_ary->status == 'blocked') {
            $status = '<img src="'.ACP_URL.'/images/icons/off.png" alt="" title="gesperrt" class="status" />';
        } else if ($movie_ary->status == 'deleted') {
            $status = '<img src="'.ACP_URL.'/images/icons/off.png" alt="" title="gel&ouml;scht" class="status" />';
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
        
        if ($movie_ary->category_master == 'porn') {
            $category_master = 'Porno';
        } else if ($movie_ary->category_master == 'fetish') {
            $category_master = 'Fetisch';
        } else {
            $category_master = '-';
        }
        
    	$row = array();
        
        #$row[] = $status_img;
        $row[] = '<a href="'.ACP_URL.'/Film-bearbeiten/'.$movie_ary->id.'">'.$movie_ary->id.'</a>';
        $row[] = '<a href="'.ACP_URL.'/Haendler/'.$movie_ary->merchant_id.'">'.$movie_ary->merchant_id.'</a>';
        $row[] = $status;
        #$row[] = '<div><img src="'.API_URL.'/PlayerPoster/FSK16/'.$movie_ary->file_id.'?w=100&cs='.$movie_ary->checksum.'" /></div><div><img src="'.API_URL.'/PlayerPoster/FSK18/'.$movie_ary->file_id.'?w=100&cs='.$movie_ary->checksum.'" /></div>';
        $row[] = $actor;
        $row[] = $quality;
        $row[] = $movie_ary->movie_checked;
        $row[] = $movie_ary->online_at;
        $row[] = $movie_language_ary[$movie_ary->movie_language];
        $row[] = $category_master;
        $row[] = '<a href="'.ACP_URL.'/Film-bearbeiten/'.$movie_ary->id.'">'.utf8_decode($title).'</a>';
        $row[] = $movie_ary->description;
   
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