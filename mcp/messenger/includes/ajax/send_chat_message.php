<?php

define('SAFE_INC', 1);

$session_write_close = true;

include_once("../../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

if (!isset($_SESSION['merchant_id'])) {
    exit;
}

if (isset($_POST['message']) AND isset($_POST['chat_id']) AND isset($_SESSION['merchant_id'])) {
   
    $chat_id = preg_replace( '/[^a-z0-9_]/i', '', $_POST['chat_id']);
    #ä$message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
    $message = str_replace('<br>', "\n", $_POST['message']);
    $message = str_replace('&nbsp;', ' ', $message);
    $message = trim(strip_tags($message));
    
    
    $merchant_id= abs($_SESSION['merchant_id']);
    $actor_id   = abs($_SESSION['actor_id']);
    
    $rs_actor_member_info = p4c_query("SELECT * FROM `actor_member_info` WHERE `chat_id`='". p4c_escape_string($chat_id)."' AND `merchant_id`='".abs($merchant_id)."' AND `actor_id`='".abs($actor_id)."' LIMIT 1;",__FILE__,__LINE__);

    if (!empty($message) AND p4c_num_rows($rs_actor_member_info) === 1) {
        $member_info_obj = p4c_fetch_object($rs_actor_member_info);
        
        
        include_once(SOURCEDIR.'/includes/klassen/PointsSystem.inc.php');
        include_once(SOURCEDIR.'/includes/klassen/ChatMessage.inc.php');

        $mess = new ChatMessage;
            
        $mess->chat_id      = $member_info_obj->chat_id;
        $mess->p4c_shop_id  = $member_info_obj->p4c_shop_id;
        $mess->merchant_id  = $member_info_obj->merchant_id;
        $mess->erocms_von_id= $member_info_obj->erocms_amateur_id;
        $mess->erocms_an_id = $member_info_obj->erocms_member_id;
        $mess->domain       = $member_info_obj->domain;
        $mess->von          = 'actor';
        $mess->von_id       = $member_info_obj->actor_id;
        $mess->an_id        = $member_info_obj->member_id;
        $mess->message_price= $member_info_obj->pn_amount;
        $mess->message      = $message;
        $mess->gelesen      = 2; // 2=gesendet
        $mess->send_to      = 'erocms';
        echo $mess->send();
    }
}

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>