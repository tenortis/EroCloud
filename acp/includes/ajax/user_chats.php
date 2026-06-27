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

$member_id = abs($_GET['member_id']);

function private_substr($str, $len=20) {
    if (strlen($str) > $len) {
        $str = substr($str,0,($len-5)).'[...]';
    }
    
    if(mb_detect_encoding($str) != 'UTF-8') {$str = utf8_encode($str);}
    return $str;
}

$rs_chats = p4c_query("SELECT * FROM `chat_messages_history` WHERE (`von`='member' AND `von_id`='".abs($member_id)."') OR (`von`='actor' AND `an_id`='".abs($member_id)."') GROUP BY `chat_id`", __FILE__, __LINE__);

if (p4c_num_rows($rs_chats) > 0) {
    $output = array(
    	"sEcho" => 0,
    	"iTotalRecords" => p4c_num_rows($rs_chats),
    	"iTotalDisplayRecords" => 25,
    	"aaData" => array()
    );

    while($chat_obj = p4c_fetch_object($rs_chats)) {

        if ($chat_obj->von == 'member') {
            $actor_id = $chat_obj->an_id;
        } else {
            $actor_id = $chat_obj->von_id;
        }
        
        $count_messages  = p4c_query("SELECT COUNT(*) FROM `chat_messages_history` WHERE `chat_id`='". p4c_escape_string($chat_obj->chat_id)."'", __FILE__,__LINE__);
        
        $rs_last_message = p4c_query("SELECT * FROM `chat_messages_history` WHERE `chat_id`='". p4c_escape_string($chat_obj->chat_id)."' ORDER BY `chat_id` DESC LIMIT 1;", __FILE__,__LINE__);
        $last_mess_obj = p4c_fetch_object($rs_last_message);
                
        $rs_actor = p4c_query("SELECT * FROM `actors` WHERE `id`='".abs($actor_id)."' LIMIT 1;",__FILE__,__LINE__);
        $actor_obj = p4c_fetch_object($rs_actor);
        
    	$row = array();
        $row[] = '<a href="'.ACP_URL.'/Chat/'.$chat_obj->chat_id.'" target="_blank">'.$chat_obj->chat_id.'</a>';
        $row[] = '<a href="'.ACP_URL.'/Actor/'.$actor_id.'">'.$actor_obj->username.'</a>';
        $row[] = $actor_id;
        $row[] = p4c_result($count_messages, 0);
        $row[] = $last_mess_obj->datetime;
        $row[] = '<span data-tooltip="'.str_replace('\n','<br />', strip_tags($last_mess_obj->message)).'">'. str_replace('\n',' ',substr($last_mess_obj->message,0,100)).'</span>';
    
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