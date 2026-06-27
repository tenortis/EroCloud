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

$rs_members = p4c_query("SELECT * FROM `members`", __FILE__, __LINE__);

if (p4c_num_rows($rs_members) > 0) {
    $output = array(
    	"sEcho" => 0,
    	"iTotalRecords" => p4c_num_rows($rs_members),
    	"iTotalDisplayRecords" => 25,
    	"aaData" => array()
    );

    while($member_obj = p4c_fetch_object($rs_members)) {

        #$rs_shop = p4c_query("SELECT * FROM `sites` WHERE `p4c_shop_id`='".abs($member_obj->p4c_shop_id)."' LIMIT 1;",__FILE__,__LINE__);
        #$shop_obj = p4c_fetch_object($rs_shop);
        
        #$rs_count_messages = p4c_query("SELECT COUNT(*) FROM `chat_messages_history` WHERE (`von`='member' AND `von_id`='".abs($member_obj->id)."') OR (`von`='actor' AND `an_id`='".abs($member_obj->id)."')", __FILE__,__LINE__);
        #$count_messages = p4c_result($rs_count_messages,0);
        
        #$count_chats = 0;
        #if ($count_messages > 0) {
        #    $rs_count_chats = p4c_query("SELECT COUNT(*) FROM `chat_messages_history` WHERE (`von`='member' AND `von_id`='".abs($member_obj->id)."') OR (`von`='actor' AND `an_id`='".abs($member_obj->id)."') GROUP BY `chat_id`", __FILE__, __LINE__);
        #    $count_chats = p4c_num_rows($rs_count_chats);
        #}
        
    	$row = array();
        $row[] = $member_obj->id;
        $row[] = '<a href="'.ACP_URL.'/User/'.$member_obj->id.'" target="_blank">'.$member_obj->username.'</a>';
        $row[] = '<a href="mailto:'.$member_obj->email.'">'.$member_obj->email.'</a>';
        $row[] = $member_obj->amount_coins;
        $row[] = $member_obj->lastonline;
        $row[] = '<a href="'.ACP_URL.'/Site/'.$member_obj->domain.'" target="_blank">'.$member_obj->p4c_shop_id.'</a>';
        $row[] = $member_obj->domain;
        $row[] = $member_obj->count_chats;
        $row[] = $member_obj->count_messages;
        $row[] = $member_obj->online_device;
    
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