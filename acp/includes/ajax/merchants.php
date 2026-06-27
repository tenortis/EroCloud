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

$rs_merchants = p4c_query("SELECT 
    `id`, `partner_id`,
     AES_DECRYPT(`username`, '".AES_KEY."') AS `username`,
     AES_DECRYPT(`email`, '".AES_KEY."') AS `email`,
     AES_DECRYPT(`last_ip`, '".AES_KEY."') AS `last_ip`,
    last_ip_datetime,
    accept_content_rules
    FROM `merchants` ORDER BY `id` DESC", __FILE__, __LINE__);

if (p4c_num_rows($rs_merchants) > 0) {
    $output = array(
    	"sEcho" => 0,
    	"iTotalRecords" => p4c_num_rows($rs_merchants),
    	"iTotalDisplayRecords" => 25,
    	"aaData" => array()
    );

    while($merchant_ary = p4c_fetch_object($rs_merchants)) {
 	   
        $rs_movies = p4c_query("SELECT * FROM `movies` WHERE `merchant_id`='". p4c_escape_string($merchant_ary->id)."' AND `movie_checked`='000-00-00 00:00:00' AND `released`='1';", __FILE__, __LINE__);
        $rs_movies_online = p4c_query("SELECT * FROM `movies_online` WHERE `merchant_id`='". p4c_escape_string($merchant_ary->id)."';",__FILE__,__LINE__);

        $rs_actors = p4c_query("SELECT * FROM `actors` WHERE `merchant_id`='".abs($merchant_ary->id)."';", __FILE__,__LINE__);
        
        $accept_content_rules = '0000-00-00 00:00:00';
        if ($merchant_ary->accept_content_rules != '0000-00-00 00:00:00') {
            $accept_content_rules = $merchant_ary->accept_content_rules;
        }
        
    	$row = array();
        $row[] = $merchant_ary->id;
        $row[] = '<a href="'.ACP_URL.'/Haendler/'.$merchant_ary->partner_id.'">'.$merchant_ary->partner_id.'</a>';
        $row[] = '<a href="'.ACP_URL.'/Haendler/'.$merchant_ary->partner_id.'">'.$merchant_ary->username.'</a>';
        $row[] = abs(p4c_num_rows($rs_actors));
        $row[] = abs(p4c_num_rows($rs_movies_online));
        $row[] = p4c_num_rows($rs_movies);
        $row[] = '<a href="mailto:'.$merchant_ary->email.'">'.$merchant_ary->email.'</a>';
        $row[] = $merchant_ary->last_ip;
        $row[] = $merchant_ary->last_ip_datetime;
        $row[] = $accept_content_rules;
    
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