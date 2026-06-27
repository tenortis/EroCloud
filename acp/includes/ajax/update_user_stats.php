<?php
 
define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(ACP_DIR."/common.inc.php");

if (is_logged_in('acp') === false) {
    exit;   
}

$rs_members = p4c_query("SELECT `members`.*, `sites`.`domain` FROM `members` INNER JOIN `sites` ON `members`.`p4c_shop_id`=`sites`.`p4c_shop_id` AND `last_stats_update`<='".date("Y-m-d H:i:s", strtotime("-1 day"))."' LIMIT 5000;", __FILE__, __LINE__);
if (p4c_num_rows($rs_members) == 0) {
    $rs_members = p4c_query("SELECT `members`.*, `sites`.`domain` FROM `members` INNER JOIN `sites` ON `members`.`p4c_shop_id`=`sites`.`p4c_shop_id` AND `last_stats_update`<='".date("Y-m-d H:i:s", strtotime("-1 hours"))."' LIMIT 1000;", __FILE__, __LINE__);
}


if (p4c_num_rows($rs_members) > 0) {
    
    while($member_obj = p4c_fetch_object($rs_members)) {

        $rs_count_messages = p4c_query("SELECT COUNT(*) FROM `chat_messages_history` WHERE `von`='member' AND `von_id`='".abs($member_obj->id)."';", __FILE__,__LINE__);
        $count_messages = p4c_result($rs_count_messages,0);
        
        $rs_count_chats = p4c_query("SELECT COUNT(*) FROM `chat_messages_history` WHERE (`von`='member' AND `von_id`='".abs($member_obj->id)."') OR (`von`='actor' AND `an_id`='".abs($member_obj->id)."') GROUP BY `chat_id`", __FILE__, __LINE__);
        $count_chats = p4c_num_rows($rs_count_chats);
        
        p4c_query("UPDATE `members` SET 
            `last_stats_update` = '".date("Y-m-d H:i:s")."',
            `domain`            = '".$member_obj->domain."',
            `count_chats`       = '".$count_chats."',
            `count_messages`    = '".$count_messages."'
        WHERE
            `id` = '".abs($member_obj->id)."' LIMIT 1;",__FILE__,__LINE__);
    }

}

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());


?>