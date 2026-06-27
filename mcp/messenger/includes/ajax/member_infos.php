<?php

define('SAFE_INC', 1);

$session_write_close = true;

include_once("../../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

if (!isset($_SESSION['merchant_id'])) {
    exit;
}

// Member infos 
if(isset($_POST['remote_member_id'])) {
    
    $chat_id = preg_replace ( '/[^a-z0-9_]/i', '', $_POST['remote_member_id']);
  
    $send_jason = array();

    #$class_errorlog->log(print_r($_SESSION,true),__FILE__,__LINE__);
    #my_chatgroup
    
    $rs_members_infos = p4c_query("SELECT * FROM `members`, `actor_member_info` WHERE
        `members`.`remote_member_id`=`actor_member_info`.`remote_member_id` AND
        `actor_member_info`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
        `actor_member_info`.`chat_id`='". p4c_escape_string($chat_id)."' LIMIT 1;",__FILE__,__LINE__);

    // Wenn noch kein Chat 
    if (p4c_num_rows($rs_members_infos) == 0) {
        
        $member_info_obj = p4c_fetch_object($rs_members_infos);
        
        $explode_chat_id = explode('_',$chat_id);
        $actor_id           = abs($explode_chat_id[0]);
        $erocms_member_id   = abs($explode_chat_id[2]);
        $remote_member_id   = $explode_chat_id['1'].'_'.$explode_chat_id['2'];
        
        $rs_member = p4c_query("SELECT * FROM `members` WHERE `remote_member_id` = '".p4c_escape_string($remote_member_id)."' LIMIT 1;",__FILE__,__LINE__);
        if (p4c_num_rows($rs_member) > 0) {
            $member_obj = p4c_fetch_object($rs_member);

            $rs_messenger_sync = p4c_query("SELECT * FROM `messenger_sync` WHERE
                `p4c_shop_id`= '".abs($member_obj->p4c_shop_id)."' AND
                `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                `actor_id`= '".abs($actor_id)."'
            LIMIT 1;",__FILE__,__LINE__);
            if (p4c_num_rows($rs_messenger_sync) == 0) {
                $send_jason['error'] = 'reload';
                echo json_encode($send_jason);
                exit;
            }

            $rs_actor = p4c_query("SELECT * FROM `actors` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `id`= '".abs($actor_id)."' LIMIT 1;",__FILE__,__LINE__);
            if (p4c_num_rows($rs_actor) == 0) {
                $send_jason['error'] = 'reload2';
                echo json_encode($send_jason);
                exit;
            }
            $actor_obj = p4c_fetch_object($rs_actor);
            
            $sync_obj = p4c_fetch_object($rs_messenger_sync);
            
            $erocms_chat_id = $sync_obj->remote_actor_id.'_'.$erocms_member_id;
            
            p4c_query("INSERT INTO `actor_member_info` SET 
                `chat_id`           = '".p4c_escape_string($chat_id)."',
                `erocms_amateur_id` = '".abs($sync_obj->remote_actor_id)."',
                `erocms_member_id`  = '".abs($erocms_member_id)."',
                `erocms_chat_id`    = '".p4c_escape_string($erocms_chat_id)."',
                `p4c_shop_id`       = '".abs($member_obj->p4c_shop_id)."',
                `remote_member_id`  = '".p4c_escape_string($member_obj->remote_member_id)."',
                `domain`            = '".p4c_escape_string($sync_obj->domain)."',
                `merchant_id`       = '".abs($_SESSION['merchant_id'])."',
                `actor_id`          = '".abs($sync_obj->actor_id)."',
                `member_id`         = '".abs($member_obj->id)."',
                `pn_amount`         = '".abs($actor_obj->pn_amount)."',
                `pn_free_when_webcam` = '".abs($actor_obj->pn_free_if_webcam)."',
                `cam2cam_amount`    = '".abs($actor_obj->cam2cam_amount)."',
                `cam_amount`        = '".abs($actor_obj->cam_amount)."',
                `is_cam_free`       = '0',
                `member_is_typing`  = '0',
                `actor_is_typing`   = '0',
                `user_notes`        = '',
                `is_synct`          = '1'
            ",__FILE__,__LINE__);

            $rs_members_infos = p4c_query("SELECT * FROM `members`, `actor_member_info` WHERE
                `members`.`remote_member_id`=`actor_member_info`.`remote_member_id` AND
                `actor_member_info`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                `actor_member_info`.`chat_id`='". p4c_escape_string($chat_id)."' LIMIT 1;",__FILE__,__LINE__);
        }
    }
    
    if (isset($rs_members_infos) AND p4c_num_rows($rs_members_infos) > 0) {
        $member_obj = p4c_fetch_object($rs_members_infos);
        
        $_SESSION['actor_id'] = $member_obj->actor_id;

        $rs_count_messages_from_user = p4c_query("SELECT COUNT(`id`) FROM `chat_messages_history` WHERE
            `chat_id`='".p4c_escape_string($member_obj->chat_id)."' AND `von`='member' AND `geloescht`='0' AND `systemnachricht`='0';", __FILE__, __LINE__);
        $count_messages_from_user = p4c_result($rs_count_messages_from_user,0);

        $rs_count_messages_from_actor = p4c_query("SELECT COUNT(`id`) FROM `chat_messages_history` WHERE
            `chat_id`='".p4c_escape_string($member_obj->chat_id)."' AND `von`='actor' AND `geloescht`='0' AND `systemnachricht`='0';", __FILE__, __LINE__);
        $count_messages_from_actor = p4c_result($rs_count_messages_from_actor,0);    

        $member_avatar_url = trim($member_obj->avatar_url);

        if(empty($member_avatar_url)) {
            $member_avatar_url = MCP_URL.'/images/movie_poster_nopic.jpg';
        }
        
        if ($member_obj->birthday === '0000-00-00') {
            $birthday = '';
        } else {
            
            $birthyear = date("y",strtotime($member_obj->birthday));
            $age = date("Y") - date("Y",strtotime($member_obj->birthday));
            
            if (date("m-d",strtotime($member_obj->birthday)) == date("m-d",strtotime("-2 days"))) {
                $birthday = '<span class="birthday_now">Vorgestern am '.date("d.m.Y",strtotime($member_obj->birthday)).' ('.$age.' Jahre)</span>';
            } else if (date("m-d",strtotime($member_obj->birthday)) == date("Y-m-d",strtotime("-1 days"))) {
                $birthday = '<span class="birthday_now">Gestern am '.date("d.m.Y",strtotime($member_obj->birthday)).' ('.$age.' Jahre)</span>';
            } else if (date("m-d",strtotime($member_obj->birthday)) == date("Y-m-d")) {
                $birthday = '<span class="birthday_now">Heute am '.date("d.m.Y",strtotime($member_obj->birthday)).' ('.$age.' Jahre)</span>';
            } else if (date("m-d",strtotime($member_obj->birthday)) == date("m-d",strtotime("+1 days"))) {
                $birthday = '<span class="birthday_now">Morgen am '.date("d.m.Y",strtotime($member_obj->birthday)).' ('.$age.' Jahre)</span>';
            } else if (date("m-d",strtotime($member_obj->birthday)) == date("m-d",strtotime("+2 days"))) {
                $birthday = utf8_encode('<span class="birthday_now">Übermorgen am '.date("d.m.Y",strtotime($member_obj->birthday)).' ('.$age.' Jahre)</span>');
            } else {
                $birthday = date("d.m.Y",strtotime($member_obj->birthday)).' ('.$age.' Jahre)';
            }
        }
        
        if ($member_obj->lastonline >= date("Y-m-d H:i:s",strtotime("-10 Minutes"))) {
            $online = 1;
        } else {
            $online = 0;
        }
        
        if (date("Y-m-d", strtotime($member_obj->lastonline)) == date("Y-m-d",strtotime("-1 days"))) {
            $lastonline = 'Gestern um '.date("H:i", strtotime($member_obj->lastonline));
        } else if (date("Y-m-d", strtotime($member_obj->lastonline)) == date("Y-m-d",time())) {
            $lastonline = 'Heute um '.date("H:i", strtotime($member_obj->lastonline));
        } else {
            $lastonline = date("d.m.Y - H:i", strtotime($member_obj->lastonline));
        }
        
        $send_jason['user'] = [
            'username'      => utf8_encode($member_obj->username),
            'domain'        => utf8_encode($member_obj->domain),
            'member_avatar' => utf8_encode($member_avatar_url),
            'count_mess_from_member'=> $count_messages_from_user,
            'count_mess_from_actor' => $count_messages_from_actor,
            'online_device' => $member_obj->online_device,
            'birthday'      => $birthday,
            'online'        => $online,
            'lastonline'    => $lastonline,
            'marked_as_unanswered' => $member_obj->marked_as_unanswered,
            'user_notes'    => $member_obj->user_notes,
            'pn_amount'     => $member_obj->pn_amount,
            'cam_amount'    => $member_obj->cam_amount,
            'cam2cam_amount'=> $member_obj->cam2cam_amount
        ];
        
        // Actor
        #############################################################
        
        include_once(SOURCEDIR.'/includes/klassen/actor.inc.php');
        $actor = new Actor($member_obj->actor_id);
     
        $actor_avatar_url = MCP_URL.'/ProfilePicture/'.$actor->get("profile_image_fsk16");
        
        $send_jason['actor'] = [
            'actor_avatar'  => utf8_encode($actor_avatar_url),
            'username'      => utf8_encode($actor->get('username')),
            'actor_id'      => $actor->get('id'),
            'actor_age'     => $actor->get('age'),
            'actor_marital_status'  => $actor->get('marital_status'),
            'actor_looking_for'     => str_replace('|', ' / ', $actor->get('looking_for')),
            'actor_interests'       => str_replace('|', ' / ', $actor->get('interests')),
            'takes_a_break' => $actor->get('messenger_takes_a_break'),
            'online_status' => $actor->get('messenger_online_status')         
        ];
        
        echo json_encode($send_jason);
    }
}

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>