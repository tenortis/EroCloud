<?php

$start = time();

define('SAFE_INC', 1);

$session_write_close = true;

include_once("../../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

// Wenn Messanger geöffnet ist, nicht ausloggen
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['merchant_id']) OR !isset($_SESSION['my_chatgroup'])) {
    exit;
}

// User mit ungelesenen Nachrichten
if(isset($_POST['userlist']) AND $_POST['userlist'] == 'unread') {
    
    function sksort(&$array, $subkey="id", $sort_ascending=false) {

        if (count($array)) {
            $temp_array[key($array)] = array_shift($array);
        }

        foreach($array as $key => $val){
            $offset = 0;
            $found = false;
            foreach($temp_array as $tmp_key => $tmp_val) {
                if(!$found and strtolower($val[$subkey]) > strtolower($tmp_val[$subkey])) {
                    $temp_array = array_merge(
                        (array)array_slice($temp_array,0,$offset),
                        array($key => $val),
                        array_slice($temp_array,$offset)
                    );
                    $found = true;
                }
                $offset++;
            }
            if(!$found) {
                $temp_array = array_merge($temp_array, array($key => $val));
            }
        }

        if ($sort_ascending) {
            $array = array_reverse($temp_array);
        } else {
            $array = $temp_array;
        }
    }
    
    $send_jason = array();
    
    $rs_actor_member_infos = p4c_query("SELECT * FROM `actor_member_info` WHERE `merchant_id`='".abs((int)$_SESSION['merchant_id'])."' AND `marked_as_unanswered`='1' ORDER BY `id` DESC;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_actor_member_infos) > 0) {
        $marked_as_unanswered_ary = array();
        while($marked_as_unanswered_obj = p4c_fetch_object($rs_actor_member_infos)) {
            $marked_as_unanswered_ary[] = $marked_as_unanswered_obj->chat_id;
        }
    }
    
    $rs_members = p4c_query("SELECT *, `members`.`id` AS `member_id` FROM `group_actors`, `members` LEFT JOIN `messenger_sync` ON `messenger_sync`.`p4c_shop_id`=`members`.`p4c_shop_id` WHERE
        `messenger_sync`.`actor_id`=`group_actors`.`actor_id` AND
        `group_actors`.`group_id`='".abs((int)$_SESSION['my_chatgroup'])."' AND
        (`group_actors`.`is_displayed_as`='only_chat_actor' OR `group_actors`.`is_displayed_as`='chat_upload_actor') AND
        `members`.`lastonline`>'".date("Y-m-d H:i:s",strtotime("-3 month"))."' AND
        `messenger_sync`.`sync_time`!='0000-00-00 00:00:00'
    ORDER BY `lastonline` DESC;",__FILE__,__LINE__);
      
    // Suche alle User mit ungelesenen Nachrichten von ActorID X
    $rs_count_unread_chat_messages = p4c_query("SELECT COUNT(*) AS `count`, `group_actors`.`actor_id`, `chat_messages`.`von_id`  FROM `group_actors`, `chat_messages`, `actors` WHERE
        `group_actors`.`actor_id`=`chat_messages`.`an_id` AND
        `actors`.`id`=`chat_messages`.`an_id` AND
        `group_actors`.`group_id` = '".abs((int)$_SESSION['my_chatgroup'])."' AND
        `group_actors`.`merchant_id`='".abs((int)$_SESSION['merchant_id'])."' AND
        `chat_messages`.`von`='member' AND
        `chat_messages`.`gelesen` != '1' AND
        (`actors`.`status`='active' OR `actors`.`status`='inactive')
    GROUP BY `chat_messages`.`chat_id`;",__FILE__,__LINE__);
   
    $unread_chat_messages_ary = [];
    if (p4c_num_rows($rs_count_unread_chat_messages) > 0) {
        while($unread_messages_obj = p4c_fetch_object($rs_count_unread_chat_messages)) {
            $unread_chat_messages_ary[$unread_messages_obj->actor_id.'_'.$unread_messages_obj->von_id] = $unread_messages_obj->count;
        }
    }    
    
    $count_unread = 0;
    $count_online = 0;
    $count_offline= 0;
    $count_marked_as_unanswered = 0;
       
    while($member_obj = p4c_fetch_object($rs_members)) {
        
        $member_unread = 0;
        if (!empty($unread_chat_messages_ary)) {
            $find = $member_obj->actor_id.'_'.$member_obj->member_id;
            if(array_key_exists($find, $unread_chat_messages_ary)) {
                $member_unread = $unread_chat_messages_ary[$find];
            }
        }
        
        $amount_coins = 0;
        if ($member_obj->amount_coins > 0) {
            $amount_coins = $member_obj->amount_coins;
        }

        $member_avatar_url = trim($member_obj->avatar_url);
        if(empty($member_avatar_url)) {
            $member_avatar_url = MCP_URL.'/images/movie_poster_nopic.jpg';
        }    
                
        $chat_id = $member_obj->actor_id.'_'.$member_obj->remote_member_id;
        
        if (isset($marked_as_unanswered_ary) AND in_array($chat_id,$marked_as_unanswered_ary)) {
            $order_id = strtotime($member_obj->lastonline);
            $count_marked_as_unanswered++;
            if ($member_obj->lastonline >= date("Y-m-d H:i:s",strtotime("-10 Minutes"))) {
                $online = 1;
            } else {
                $online = 0;
            }
            
            $send_jason['user_marked_as_unanswered'][] = [
                'order_id'      => $order_id,
                'chat_id'       => $chat_id,
                'username'      => utf8_encode($member_obj->username),
                'amount_coins'  => $amount_coins,
                'avatar_url'    => $member_avatar_url,
                'domain'        => $member_obj->domain,
                'online_status' => $online,
                'unread_messages'=> $member_unread
            ];
            
        } else if ($member_unread > 0) {
            
            $rs_last_id = p4c_query("SELECT `id` FROM `chat_messages` WHERE `gelesen` != '1' AND `von`='member' AND `chat_messages`.`an_id`='".$member_obj->actor_id."' AND `von_id`='".$member_obj->member_id."' ORDER BY `id` DESC LIMIT 1;",__FILE__,__LINE__);
            $order_id = p4c_result($rs_last_id,0);
            
            $count_unread++;
            if ($member_obj->lastonline >= date("Y-m-d H:i:s",strtotime("-10 Minutes"))) {
                $online = 1;
            } else {
                $online = 0;
            }
            
            $send_jason['user_unread'][] = [
                'order_id'      => $order_id,
                'chat_id'       => $chat_id,
                'username'      => utf8_encode($member_obj->username),
                'amount_coins'  => $amount_coins,
                'avatar_url'    => $member_avatar_url,
                'domain'        => $member_obj->domain,
                'online_status' => $online,
                'unread_messages'=> $member_unread
            ];
            
        } else {
            if ($member_obj->lastonline >= date("Y-m-d H:i:s",strtotime("-10 Minutes"))) {
                $online = 1;
                $count_online++;
                
                $send_jason['user_online'][] = [
                    'order_id'      => $amount_coins,
                    'chat_id'       => $chat_id,
                    'username'      => utf8_encode($member_obj->username),
                    'amount_coins'  => $amount_coins,
                    'avatar_url'    => $member_avatar_url,
                    'domain'        => $member_obj->domain,
                    'online_status' => $online,
                    'unread_messages'=> $member_unread
                ];
            } else {
                
                // Maxiaml 1000 offline User anzeigen / Liste lädt sonst zu lange
                if ($count_offline < 1000) {
                    // Nur offline User der letzte 3 Tage anzeigen
                    if ($member_obj->lastonline >= date("Y-m-d H:i:s",strtotime("-3 days"))) {
                        $online = 0;
                        $count_offline++;

                        $send_jason['user_offline'][] = [
                            'order_id'      => strtotime($member_obj->lastonline),
                            'chat_id'       => $chat_id,
                            'username'      => utf8_encode($member_obj->username),
                            'amount_coins'  => $amount_coins,
                            'avatar_url'    => $member_avatar_url,
                            'domain'        => $member_obj->domain,
                            'online_status' => $online,
                            'unread_messages'=> $member_unread
                        ];
                    }
                }
                 
            }  
        }
    }

    if (isset($send_jason['user_marked_as_unanswered'])) {
        sksort($send_jason['user_marked_as_unanswered'], "order_id");
    }
    
    if (isset($send_jason['user_unread'])) {
        sksort($send_jason['user_unread'], "order_id");
    }
    
    if (isset($send_jason['user_online'])) {
        sksort($send_jason['user_online'], "order_id");
    }
    
    if (isset($send_jason['user_offline'])) {
        sksort($send_jason['user_offline'], "order_id");
    }
    
    $send_jason['count_marked_as_unanswered'] = $count_marked_as_unanswered;
    $send_jason['count_user_unread'] = $count_unread;
    $send_jason['count_user_online'] = $count_online;
    $send_jason['count_user_offline'] = $count_offline;
    
    $end = time();
    
    $send_jason['scriptime'] = $end - $start;
    
    echo json_encode($send_jason);

}

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>