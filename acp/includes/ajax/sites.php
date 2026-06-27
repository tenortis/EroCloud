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

$rs_sites = p4c_query("SELECT * FROM `sites` ORDER BY `id` DESC", __FILE__, __LINE__);

if (p4c_num_rows($rs_sites) > 0) {
    $output = array(
    	"sEcho" => 0,
    	"iTotalRecords" => p4c_num_rows($rs_sites),
    	"iTotalDisplayRecords" => 25,
    	"aaData" => array()
    );

    while($site_obj = p4c_fetch_object($rs_sites)) {
        
        $rs_ads = p4c_query("SELECT COUNT(*) FROM `ads_media` WHERE `site_id`='".abs($site_obj->id)."';",__FILE__,__LINE__);
        
        if ($site_obj->status == '1') {
            $status = '<img src="'.ACP_URL.'/images/icons/on.png" alt="" title="aktiv" />';
        } else {
            $status = '<img src="'.ACP_URL.'/images/icons/off.png" alt="" title="inaktiv" />';
        }

        if ($site_obj->is_eroads_active == '1') {
            $ads = '<img src="'.ACP_URL.'/images/icons/on.png" alt="" title="aktiv" />';
        } else {
            $ads = '<img src="'.ACP_URL.'/images/icons/off.png" alt="" title="inaktiv" />';
        }
        
    	$row = array();
        $row[] = $site_obj->id;
        $row[] = $status;
        $row[] = $ads;
        $row[] = $site_obj->webmaster_commision.'%';
        $row[] = '<a href="'.ACP_URL.'/Site/'.$site_obj->domain.'">'.$site_obj->domain.'</a>';
        $row[] = '<a href="'.ACP_URL.'/Haendler/'.$site_obj->partner_id.'">'.$site_obj->partner_id.'</a>';
        $row[] = p4c_result($rs_ads,0);
        $row[] = $site_obj->last_update_timestamp;

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