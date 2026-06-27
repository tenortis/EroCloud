<?php
 
define('SAFE_INC', 1);

include_once("../../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

header('Content-Type: text/html; charset=utf-8');

if (is_logged_in('mcp') === false) {
    exit;
}

$merchant_id = abs($_SESSION['merchant_id']);

if (isset($_POST['mark_as_read']) AND isset($_POST['chat_id'])) {

    $chat_id = preg_replace ( '/[^a-z0-9_]/i', '', $_POST['chat_id']);
    
    p4c_query("UPDATE `amoredea_messages` SET `gelesen` = '1' WHERE `chat_id`='".p4c_escape_string($chat_id)."' AND `von`='member' AND `merchant_id`='".abs($merchant_id)."';",__FILE__,__LINE__);
    p4c_query("UPDATE `amoredea_messages_history` SET `gelesen` = '1' WHERE `chat_id`='".p4c_escape_string($chat_id)."' AND `von`='member' AND `merchant_id`='".abs($merchant_id)."';",__FILE__,__LINE__);
    
    echo "1";

    exit;
}

/*
if (isset($_POST['delete_id'])) {
    
    $comment_id = abs($_POST['delete_id']);
    $month = abs($_POST['month']);
    
    // DB von amoredea auswählen
    $mysql -> change_connect("amoredea");
    
    $rs_comments = p4c_query("SELECT * FROM `timeline_comments_".abs($month)."` WHERE `id`='".abs($comment_id)."' AND `merchant_id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__);
    
    if (p4c_num_rows($rs_comments) > 0) {
        $comment_obj = p4c_fetch_object($rs_comments);
        
        p4c_query("DELETE FROM `timeline_comments_".abs($month)."` WHERE `id`='".abs($comment_id)."' AND `merchant_id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__);  
    
        $rs_content = p4c_query("SELECT * FROM `timeline_".abs($month)."` WHERE `id`='".abs($comment_obj->content_id)."' LIMIT 1;",__FILE__,__LINE__);
        if (p4c_num_rows($rs_content)) {
            $content_obj = p4c_fetch_object($rs_content);
            
            $number_of_comments = $content_obj->number_of_comments - 1;
            if ($number_of_comments < 0) {$number_of_comments = 0;}        

            p4c_query("UPDATE `timeline_".abs($month)."` SET `number_of_comments`='".$number_of_comments."' WHERE `id`='".abs($comment_obj->content_id)."' LIMIT 1;",__FILE__,__LINE__);
        }
    }

    // DB von EroCloud auswählen
    $mysql -> change_connect("");

    echo "ok";
    exit;
}
*/


$count_total_chats = 0;

$rs_chats = p4c_query("SELECT *, COUNT(*) AS `count_messages` FROM `amoredea_messages` WHERE `merchant_id`='".abs($merchant_id)."' AND `gelesen`='0' AND `von`='member' GROUP BY `chat_id`;",__FILE__,__LINE__);
#$rs_chats = p4c_query("SELECT * FROM `amoredea_messages` WHERE `chat_id` LIKE '%_11' AND `gelesen`='0' AND `von`='member';",__FILE__,__LINE__);
while($chat_obj = p4c_fetch_object($rs_chats)) {
    
        // DB von amoredea auswählen
    $mysql -> change_connect("amoredea");
    
    $rs_member = p4c_query("SELECT `id`, AES_DECRYPT(`username`, '".AMOREDEA_AES_KEY."') AS `username` FROM `members` WHERE `id`='".abs($chat_obj->von_id)."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_member) == 0) {
        continue;
    }
    
    $member_obj = p4c_fetch_object($rs_member);

    $status = '<i onclick="jQuery(this).mark_as_read(\''.$chat_obj->chat_id.'\');" title="Als gelesen markieren" data-status="1" class="unchecked material-symbols-outlined">check_circle</i>';

    if ($chat_obj->count_messages > 1) {
        $message = $chat_obj->message.'<br /><span style="color:red;">In diesem Chat gibt es noch weitere ungelesene Nachrichten.</span>';
    } else {
        $message = $chat_obj->message;
    }
    
    $username = trim($member_obj->username);
    
    if (empty($username)) {
        $username = '<i>UID'.$member_obj->id.'</i>';
    }
    
    $row = array();
    $row[] = $status;
    $row[] = $username;
    $row[] = $chat_obj->datetime;
    $row[] = $message;
    $row[] = '<i title="Auf Nachricht antworten" onclick="jQuery(this).reply_message(\''.$chat_obj->chat_id.'\', '.$chat_obj->von_id.');" class="reply_message material-symbols-outlined">reply</i>';

    $output['aaData'][] = $row;
    
    $count_total_chats++;
    
}

if ($count_total_chats == 0) {
    $output = array(
        "sEcho" => 0,
        "iTotalRecords" => $count_total_chats,
        "iTotalDisplayRecords" => 25,
        "aaData" => array()
    );
}

echo json_encode($output);
