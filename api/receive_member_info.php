<?php

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

if (!isset($_SERVER['REMOTE_ADDR'])) {
    #$api['error'] = 'access denid - no remote address';
    #print_xml($api);
}

if (check_ip_whitelist($_SERVER['REMOTE_ADDR']) === false) {
    #$api['error'] = 'access denid ip ('.$_SERVER['REMOTE_ADDR'].') not listet';
    #$class_errorlog->log($api['error']."\n".print_r(filter_input_array(INPUT_GET), true),__FILE__,__LINE__);
    #print_xml($api);
}

$get_p4c_shop_id = abs(filter_input(INPUT_GET, 'p4c_shop_id', FILTER_SANITIZE_NUMBER_INT));

$get_remote_member_id = abs(filter_input(INPUT_GET, 'remote_member_id', FILTER_SANITIZE_NUMBER_INT));
$erocms_member_id = $get_remote_member_id;
$get_remote_member_id = $get_p4c_shop_id.'_'.$get_remote_member_id;

$get_lastonline = filter_input(INPUT_GET, 'lastonline', FILTER_SANITIZE_NUMBER_INT);
$get_lastonline = date("Y-m-d H:i:s", $get_lastonline);

$get_birthday   = filter_input(INPUT_GET, 'birthday', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
$get_birthday   = date("Y-m-d", strtotime($get_birthday));
if ($get_birthday < "0000-00-00") {$get_birthday = '0000-00-00';}


$get_amount_coins   = abs(filter_input(INPUT_GET, 'coins', FILTER_SANITIZE_NUMBER_INT));
$get_locked         = abs(filter_input(INPUT_GET, 'locked', FILTER_SANITIZE_NUMBER_INT));

$get_online_device = filter_input(INPUT_GET, 'online_device', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
$get_online_device = preg_replace( '/[^a-z]/i', '', $get_online_device);

$get_avatar_url = filter_input(INPUT_GET, 'avatar_url', FILTER_VALIDATE_URL);
if ($get_avatar_url === 0) {$get_avatar_url = '';}

$get_email = filter_input(INPUT_GET, 'email', FILTER_VALIDATE_EMAIL);
if ($get_email === 0) {$get_email = '';}

$get_username = filter_input(INPUT_GET, 'username', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

$get_user_cam_timestamp = abs(filter_input(INPUT_GET, 'user_cam_timestamp', FILTER_SANITIZE_NUMBER_INT));

$get_user_cam_stream_id = filter_input(INPUT_GET, 'user_cam_stream_id', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
$get_user_cam_stream_id = preg_replace( '/[^a-z0-9]/i', '', $get_user_cam_stream_id);

$get_cam_visible_for_actor = abs(filter_input(INPUT_GET, 'user_cam_visible_for_actor', FILTER_SANITIZE_NUMBER_INT));

$get_user_cam_streamserver_url = filter_input(INPUT_GET, 'user_cam_streamserver_url', FILTER_VALIDATE_URL);

$rs_member = p4c_query("SELECT * FROM `members` WHERE `remote_member_id` = '". p4c_escape_string($get_remote_member_id)."' LIMIT 1;",__FILE__,__LINE__);

$member_id = '';

// Wenn kunde noch nicht existiert
if (p4c_num_rows($rs_member) == 0) {
    
    if (p4c_query("INSERT INTO `members` SET 
        `remote_member_id`  = '".p4c_escape_string($get_remote_member_id)."',
        `p4c_shop_id`       = '".p4c_escape_string($get_p4c_shop_id)."',
        `username`          = '".p4c_escape_string($get_username)."',
        `amount_coins`      = '".abs($get_amount_coins)."',
        `email`             = '".p4c_escape_string($get_email)."',
        `birthday`          = '".p4c_escape_string($get_birthday)."',
        `lastonline`        = '".p4c_escape_string($get_lastonline)."',
        `online_device`     = '".p4c_escape_string($get_online_device)."',
        `locked`            = '".abs($get_locked)."',
        `avatar_url`        = '".p4c_escape_string($get_avatar_url)."';",__FILE__,__LINE__)
    ) {
        $member_id = p4c_insert_id();
    }
    
// Aktualisieren    
} else {

    if (p4c_query("UPDATE `members` SET 
        `amount_coins`  = '".abs($get_amount_coins)."',
        `username`      = '".p4c_escape_string($get_username)."',
        `email`         = '".p4c_escape_string($get_email)."',
        `birthday`      = '".p4c_escape_string($get_birthday)."',
        `lastonline`    = '".p4c_escape_string($get_lastonline)."',
        `online_device` = '".p4c_escape_string($get_online_device)."',
        `locked`        = '".abs($get_locked)."',
        `avatar_url`    = '".p4c_escape_string($get_avatar_url)."'
        WHERE `remote_member_id`='". p4c_escape_string($get_remote_member_id)."' AND  `p4c_shop_id`='".abs($get_p4c_shop_id)."' LIMIT 1;",__FILE__,__LINE__)
    ) {
        $member_obj = p4c_fetch_object($rs_member);
        $member_id = $member_obj->id;
    }
}

if (isset($member_id) AND $member_id != '') {

    // Falls der User beim senden einer Nachricht noch nicht existiert hat, korrigiere jetzt id member_id
    $rs_actor_member_info = p4c_query("SELECT * FROM `actor_member_info` WHERE
        `member_id`='0' AND
        `erocms_member_id`='".abs($erocms_member_id)."' AND 
        `p4c_shop_id`='".abs($get_p4c_shop_id)."'
    LIMIT 1;",__FILE__,__LINE__);

    if (p4c_num_rows($rs_actor_member_info)) {
        $rs_actor_member_info = p4c_query("UPDATE `actor_member_info` SET
            `member_id`='".abs($member_id)."'
        WHERE
            `member_id`='0' AND
            `erocms_member_id`='".abs($erocms_member_id)."' AND 
            `p4c_shop_id`='".abs($get_p4c_shop_id)."';",__FILE__,__LINE__);        
    }    

    $rs_chat_messages = p4c_query("SELECT * FROM `chat_messages` WHERE
        `erocms_von_id` = '".abs($erocms_member_id)."' AND 
        `von_id`        = '0' AND
        `p4c_shop_id`   = '".abs($get_p4c_shop_id)."'
    ",__FILE__,__LINE__);

    if (p4c_num_rows($rs_chat_messages)) {
        p4c_query("UPDATE `chat_messages` SET
            `von_id`        = '".abs($member_id)."'
        WHERE
            `erocms_von_id` = '".abs($erocms_member_id)."' AND 
            `von_id`        = '0' AND
            `p4c_shop_id`   = '".abs($get_p4c_shop_id)."'
        ",__FILE__,__LINE__);
    }
    
    $rs_chat_messages_history = p4c_query("SELECT * FROM `chat_messages_history` WHERE
        `erocms_von_id` = '".abs($erocms_member_id)."' AND 
        `von_id`        = '0' AND
        `p4c_shop_id`   = '".abs($get_p4c_shop_id)."'
    ",__FILE__,__LINE__);

    if (p4c_num_rows($rs_chat_messages_history)) {
        p4c_query("UPDATE `chat_messages_history` SET
            `von_id`        = '".abs($member_id)."'
        WHERE
            `erocms_von_id` = '".abs($erocms_member_id)."' AND 
            `von_id`        = '0' AND
            `p4c_shop_id`   = '".abs($get_p4c_shop_id)."'
        ",__FILE__,__LINE__);
    }

    if (isset($_GET['user_cam_beendet'])) {
        $rs_cam_members = p4c_query("DELETE FROM `member_cams` WHERE `member_id`='".abs($member_id)."' AND `remote_member_id`='".$get_remote_member_id."';",__FILE__,__LINE__);   
    }
    
    // Wenn User seine Cam sendet
    if ($get_user_cam_timestamp > 0 AND isset($_GET['user_cam_ice_candidates']) AND isset($_GET['user_cam_sdp_description'])) {
        $actor_id = $get_cam_visible_for_actor;

        $rs_groups = p4c_query("SELECT * FROM `group_actors` WHERE `actor_id`='".abs($actor_id)."' LIMIT 1;",__FILE__,__LINE__);
        
        if (p4c_num_rows($rs_groups) > 0) {
            $group_obj = p4c_fetch_object($rs_groups);
            
            $rs_cam_members = p4c_query("SELECT * FROM `member_cams` WHERE `member_id`='".abs($member_id)."' AND `remote_member_id`='".$get_remote_member_id."' LIMIT 1;");

            $ice_candidates = $_GET['user_cam_ice_candidates'];
            $sdp_description = $_GET['user_cam_sdp_description'];
            
            if (trim($sdp_description) != '') {
                $sdp_description = gzinflate(gzinflate($sdp_description));
            }
            
            if (trim($ice_candidates) != '') {
                $ice_candidates = gzinflate(gzinflate($ice_candidates));
            }
            
            if (p4c_num_rows($rs_cam_members) == 0) {

                p4c_query("INSERT INTO `member_cams` SET
                    `member_id`         = '".abs($member_id)."',
                    `merchant_id`       = '".abs($group_obj->merchant_id)."',
                    `group_id`          = '".abs($group_obj->group_id)."',
                    `remote_member_id`  = '".$get_remote_member_id."',
                    `visible_for_actor` = '".$get_cam_visible_for_actor."',
                    `stream_id`         = '".$get_user_cam_stream_id."',
                    `streamserver_url`  = '".$get_user_cam_streamserver_url."',
                    `ice_candidates_transmitter` = '".p4c_escape_string($ice_candidates)."',
                    `ice_transmitter_checksum`   = '".md5($ice_candidates)."',
                    `sdp_description_transmitter`= '". p4c_escape_string($sdp_description)."',
                    `sdp_transmitter_checksum`   = '". md5($sdp_description)."',
                    `datetime`          = '".date("Y-m-d H:i:s")."';", __FILE__, __LINE__);

            } else {

                // sdp_description an remote peer senden. (Antworten)
                $cam_members_obj = p4c_fetch_object($rs_cam_members);
                
                // Wenn Datum in Datenbank älter als eine Minute, war Verbindung abgebrochen und muss neu aufgebaut werden.
                // Alten eintrag löschen
                if ($cam_members_obj->datetime <= date("Y-m-d H:i:s", strtotime("-10 Seconds"))) {
                    p4c_query("UPDATE `member_cams` SET
                        `ice_candidates_transmitter`  = '',
                        `ice_transmitter_checksum` = '',
                        `sdp_description_transmitter` = '',
                        `sdp_transmitter_checksum` = '',
                        `ice_candidates_receiver` = '',
                        `ice_receiver_checksum` = '',
                        `sdp_description_receiver` = '',
                        `sdp_receiver_checksum` = ''
                    WHERE 
                        `datetime` <= '".date("Y-m-d H:i:s", strtotime("-10 Seconds"))."'
                    ;", __FILE__, __LINE__);
                }
                
                p4c_query("UPDATE `member_cams` SET
                    `merchant_id`       = '".abs($group_obj->merchant_id)."',
                    `group_id`          = '".abs($group_obj->group_id)."',
                    `visible_for_actor` = '".$get_cam_visible_for_actor."',
                    `stream_id`         = '".$get_user_cam_stream_id."',
                    `streamserver_url`  = '".$get_user_cam_streamserver_url."',
                    `ice_candidates_transmitter` = '".p4c_escape_string($ice_candidates)."',
                    `ice_transmitter_checksum`   = '".md5($ice_candidates)."',
                    `sdp_description_transmitter`= '". p4c_escape_string($sdp_description)."',
                    `sdp_transmitter_checksum`   = '". md5($sdp_description)."',
                    `datetime`          = '".date("Y-m-d H:i:s")."'
                WHERE 
                    `member_id`         = '".abs($member_id)."' AND
                    `remote_member_id`  = '".$get_remote_member_id."'
                LIMIT 1;", __FILE__, __LINE__);
                
                if (!empty($cam_members_obj->sdp_description_receiver)) {
                    $api['sdp_description'] = $cam_members_obj->sdp_description_receiver;
                }
                
                if (!empty($cam_members_obj->ice_candidates_receiver)) {
                    $api['ice_candidates'] = $cam_members_obj->ice_candidates_receiver;
                }
                
            }
            
            $rs_cam_members = p4c_query("SELECT * FROM `member_cams` WHERE `member_id`='".abs($member_id)."' AND `remote_member_id`='".$get_remote_member_id."' LIMIT 1;");
        }
    }

    $api['response'] = 'ok';
    print_xml($api);
}


$api['error'] = 'update member infos get false';
$class_errorlog->log($api['error']."\n".print_r(filter_input_array(INPUT_GET), true),__FILE__,__LINE__);
print_xml($api);

// Garbage Collection
p4c_close(DB_HOST);
	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>