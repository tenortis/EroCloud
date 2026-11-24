<?php

define('SAFE_INC', 1);

// Stateless Server-zu-Server-Endpunkt: keine PHP-Session / kein Datei-Lock (flock) auf sess_* starten
$no_session = true;
$_SESSION = array();

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

$api = array();

if (!isset($_SERVER['REMOTE_ADDR'])) {
    #$api['error'] = 'access denid - no remote address';
    #print_xml($api);
}

if (check_ip_whitelist($_SERVER['REMOTE_ADDR'] ?? '') === false) {
    #$api['error'] = 'access denid ip ('.$_SERVER['REMOTE_ADDR'].') not listet';
    #$class_errorlog->log($api['error']."\n".print_r(filter_input_array(INPUT_GET), true),__FILE__,__LINE__);
    #print_xml($api);
}

$get_p4c_shop_id = abs((int)filter_input(INPUT_GET, 'p4c_shop_id', FILTER_SANITIZE_NUMBER_INT));

$get_remote_member_id = abs((int)filter_input(INPUT_GET, 'remote_member_id', FILTER_SANITIZE_NUMBER_INT));
$erocms_member_id = $get_remote_member_id;
$get_remote_member_id = $get_p4c_shop_id.'_'.$get_remote_member_id;

$get_lastonline_raw = filter_input(INPUT_GET, 'lastonline', FILTER_SANITIZE_NUMBER_INT);
$get_lastonline_ts  = ($get_lastonline_raw === null)
    ? time()
    : (($get_lastonline_raw !== false && is_numeric($get_lastonline_raw)) ? (int)$get_lastonline_raw : 0);
$get_lastonline = date("Y-m-d H:i:s", $get_lastonline_ts);

$get_birthday_raw = filter_input(INPUT_GET, 'birthday', FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_HIGH);
$get_birthday_raw = is_string($get_birthday_raw) ? trim(strip_tags($get_birthday_raw)) : '';
$get_birthday_ts  = ($get_birthday_raw !== '' && $get_birthday_raw !== '0000-00-00') ? strtotime($get_birthday_raw) : false;
$get_birthday     = ($get_birthday_ts !== false) ? date("Y-m-d", $get_birthday_ts) : '0000-00-00';
if ($get_birthday < "0000-00-00") {$get_birthday = '0000-00-00';}


$get_amount_coins   = abs((int)filter_input(INPUT_GET, 'coins', FILTER_SANITIZE_NUMBER_INT));
$get_locked         = abs((int)filter_input(INPUT_GET, 'locked', FILTER_SANITIZE_NUMBER_INT));

$get_online_device = filter_input(INPUT_GET, 'online_device', FILTER_UNSAFE_RAW);
$get_online_device = (string)preg_replace('/[^a-z]/i', '', is_string($get_online_device) ? $get_online_device : '');

$get_avatar_url = filter_input(INPUT_GET, 'avatar_url', FILTER_VALIDATE_URL);
if (!is_string($get_avatar_url)) {$get_avatar_url = '';}

$get_email = filter_input(INPUT_GET, 'email', FILTER_VALIDATE_EMAIL);
if (!is_string($get_email)) {$get_email = '';}
$get_email = substr($get_email, 0, 250);

$get_username_raw = filter_input(INPUT_GET, 'username', FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_HIGH);
$get_username     = is_string($get_username_raw) ? strip_tags($get_username_raw) : '';

$get_user_cam_timestamp = abs((int)filter_input(INPUT_GET, 'user_cam_timestamp', FILTER_SANITIZE_NUMBER_INT));

$get_user_cam_stream_id = filter_input(INPUT_GET, 'user_cam_stream_id', FILTER_UNSAFE_RAW);
$get_user_cam_stream_id = (string)preg_replace('/[^a-z0-9]/i', '', is_string($get_user_cam_stream_id) ? $get_user_cam_stream_id : '');

$get_cam_visible_for_actor = abs((int)filter_input(INPUT_GET, 'user_cam_visible_for_actor', FILTER_SANITIZE_NUMBER_INT));

$get_user_cam_streamserver_url = filter_input(INPUT_GET, 'user_cam_streamserver_url', FILTER_VALIDATE_URL);
if (!is_string($get_user_cam_streamserver_url)) {$get_user_cam_streamserver_url = '';}

$member_id = '';

$esc_remote_member_id = p4c_escape_string($get_remote_member_id);
$esc_username         = p4c_escape_string($get_username);
$esc_email            = p4c_escape_string($get_email);
$esc_birthday         = p4c_escape_string($get_birthday);
$esc_lastonline       = p4c_escape_string($get_lastonline);
$esc_online_device    = p4c_escape_string($get_online_device);
$esc_avatar_url       = p4c_escape_string($get_avatar_url);

// Atomares Upsert verhindert Race-Condition-Duplicate-Keys bei parallelen EroCMS-Requests
if (p4c_query("INSERT INTO `members` SET 
    `remote_member_id`  = '".$esc_remote_member_id."',
    `p4c_shop_id`       = '".abs((int)$get_p4c_shop_id)."',
    `username`          = '".$esc_username."',
    `amount_coins`      = '".abs((int)$get_amount_coins)."',
    `email`             = '".$esc_email."',
    `birthday`          = '".$esc_birthday."',
    `lastonline`        = '".$esc_lastonline."',
    `online_device`     = '".$esc_online_device."',
    `locked`            = '".abs((int)$get_locked)."',
    `avatar_url`        = '".$esc_avatar_url."'
    ON DUPLICATE KEY UPDATE
    `id`                = LAST_INSERT_ID(`id`),
    `amount_coins`      = '".abs((int)$get_amount_coins)."',
    `username`          = '".$esc_username."',
    `email`             = '".$esc_email."',
    `birthday`          = '".$esc_birthday."',
    `lastonline`        = '".$esc_lastonline."',
    `online_device`     = '".$esc_online_device."',
    `locked`            = '".abs((int)$get_locked)."',
    `avatar_url`        = '".$esc_avatar_url."';",__FILE__,__LINE__)
) {
    $insert_id = p4c_insert_id();
    if (!empty($insert_id)) {
        $member_id = $insert_id;
    } else {
        $rs_member_lookup = p4c_query("SELECT `id` FROM `members` WHERE `remote_member_id` = '".$esc_remote_member_id."' LIMIT 1;",__FILE__,__LINE__);
        $member_obj_lookup = $rs_member_lookup ? p4c_fetch_object($rs_member_lookup) : false;
        if ($member_obj_lookup) {
            $member_id = $member_obj_lookup->id;
        }
    }
}

if (isset($member_id) AND $member_id != '') {

    // Falls der User beim senden einer Nachricht noch nicht existiert hat, korrigiere jetzt id member_id
    $rs_actor_member_info = p4c_query("SELECT * FROM `actor_member_info` WHERE
        `member_id`='0' AND
        `erocms_member_id`='".abs((int)$erocms_member_id)."' AND 
        `p4c_shop_id`='".abs((int)$get_p4c_shop_id)."'
    LIMIT 1;",__FILE__,__LINE__);

    if (p4c_num_rows($rs_actor_member_info)) {
        $rs_actor_member_info = p4c_query("UPDATE `actor_member_info` SET
            `member_id`='".abs((int)$member_id)."'
        WHERE
            `member_id`='0' AND
            `erocms_member_id`='".abs((int)$erocms_member_id)."' AND 
            `p4c_shop_id`='".abs((int)$get_p4c_shop_id)."';",__FILE__,__LINE__);        
    }    

    $rs_chat_messages = p4c_query("SELECT * FROM `chat_messages` WHERE
        `erocms_von_id` = '".abs((int)$erocms_member_id)."' AND 
        `von_id`        = '0' AND
        `p4c_shop_id`   = '".abs((int)$get_p4c_shop_id)."'
    ",__FILE__,__LINE__);

    if (p4c_num_rows($rs_chat_messages)) {
        p4c_query("UPDATE `chat_messages` SET
            `von_id`        = '".abs((int)$member_id)."'
        WHERE
            `erocms_von_id` = '".abs((int)$erocms_member_id)."' AND 
            `von_id`        = '0' AND
            `p4c_shop_id`   = '".abs((int)$get_p4c_shop_id)."'
        ",__FILE__,__LINE__);
    }
    
    $rs_chat_messages_history = p4c_query("SELECT * FROM `chat_messages_history` WHERE
        `erocms_von_id` = '".abs((int)$erocms_member_id)."' AND 
        `von_id`        = '0' AND
        `p4c_shop_id`   = '".abs((int)$get_p4c_shop_id)."'
    ",__FILE__,__LINE__);

    if (p4c_num_rows($rs_chat_messages_history)) {
        p4c_query("UPDATE `chat_messages_history` SET
            `von_id`        = '".abs((int)$member_id)."'
        WHERE
            `erocms_von_id` = '".abs((int)$erocms_member_id)."' AND 
            `von_id`        = '0' AND
            `p4c_shop_id`   = '".abs((int)$get_p4c_shop_id)."'
        ",__FILE__,__LINE__);
    }

    if (isset($_GET['user_cam_beendet'])) {
        $rs_cam_members = p4c_query("DELETE FROM `member_cams` WHERE `member_id`='".abs((int)$member_id)."' AND `remote_member_id`='".p4c_escape_string($get_remote_member_id)."';",__FILE__,__LINE__);   
    }
    
    // Wenn User seine Cam sendet
    if ($get_user_cam_timestamp > 0 AND isset($_GET['user_cam_ice_candidates']) AND isset($_GET['user_cam_sdp_description'])) {
        $actor_id = $get_cam_visible_for_actor;

        $rs_groups = p4c_query("SELECT * FROM `group_actors` WHERE `actor_id`='".abs((int)$actor_id)."' LIMIT 1;",__FILE__,__LINE__);
        
        if (p4c_num_rows($rs_groups) > 0) {
            $group_obj = p4c_fetch_object($rs_groups);
            if ($group_obj) {
            $rs_cam_members = p4c_query("SELECT * FROM `member_cams` WHERE `member_id`='".abs((int)$member_id)."' AND `remote_member_id`='".p4c_escape_string($get_remote_member_id)."' LIMIT 1;", __FILE__, __LINE__);

            $ice_candidates = is_scalar($_GET['user_cam_ice_candidates']) ? (string)$_GET['user_cam_ice_candidates'] : '';
            $sdp_description = is_scalar($_GET['user_cam_sdp_description']) ? (string)$_GET['user_cam_sdp_description'] : '';
            
            if (trim($sdp_description) !== '') {
                $step1 = @gzinflate($sdp_description);
                $step2 = ($step1 !== false) ? @gzinflate($step1) : false;
                $sdp_description = ($step2 !== false) ? $step2 : '';
            }
            
            if (trim($ice_candidates) !== '') {
                $step1 = @gzinflate($ice_candidates);
                $step2 = ($step1 !== false) ? @gzinflate($step1) : false;
                $ice_candidates = ($step2 !== false) ? $step2 : '';
            }
            
            if (p4c_num_rows($rs_cam_members) == 0) {

                p4c_query("INSERT INTO `member_cams` SET
                    `member_id`         = '".abs((int)$member_id)."',
                    `merchant_id`       = '".abs((int)$group_obj->merchant_id)."',
                    `group_id`          = '".abs((int)$group_obj->group_id)."',
                    `remote_member_id`  = '".p4c_escape_string($get_remote_member_id)."',
                    `visible_for_actor` = '".abs((int)$get_cam_visible_for_actor)."',
                    `stream_id`         = '".p4c_escape_string($get_user_cam_stream_id)."',
                    `streamserver_url`  = '".p4c_escape_string($get_user_cam_streamserver_url)."',
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
                if ($cam_members_obj && (string)$cam_members_obj->datetime <= date("Y-m-d H:i:s", strtotime("-10 Seconds"))) {
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
                    `merchant_id`       = '".abs((int)$group_obj->merchant_id)."',
                    `group_id`          = '".abs((int)$group_obj->group_id)."',
                    `visible_for_actor` = '".abs((int)$get_cam_visible_for_actor)."',
                    `stream_id`         = '".p4c_escape_string($get_user_cam_stream_id)."',
                    `streamserver_url`  = '".p4c_escape_string($get_user_cam_streamserver_url)."',
                    `ice_candidates_transmitter` = '".p4c_escape_string($ice_candidates)."',
                    `ice_transmitter_checksum`   = '".md5($ice_candidates)."',
                    `sdp_description_transmitter`= '". p4c_escape_string($sdp_description)."',
                    `sdp_transmitter_checksum`   = '". md5($sdp_description)."',
                    `datetime`          = '".date("Y-m-d H:i:s")."'
                WHERE 
                    `member_id`         = '".abs((int)$member_id)."' AND
                    `remote_member_id`  = '".p4c_escape_string($get_remote_member_id)."'
                LIMIT 1;", __FILE__, __LINE__);
                
                if ($cam_members_obj && !empty($cam_members_obj->sdp_description_receiver)) {
                    $api['sdp_description'] = $cam_members_obj->sdp_description_receiver;
                }
                
                if ($cam_members_obj && !empty($cam_members_obj->ice_candidates_receiver)) {
                    $api['ice_candidates'] = $cam_members_obj->ice_candidates_receiver;
                }
                
            }
            
            $rs_cam_members = p4c_query("SELECT * FROM `member_cams` WHERE `member_id`='".abs((int)$member_id)."' AND `remote_member_id`='".p4c_escape_string($get_remote_member_id)."' LIMIT 1;", __FILE__, __LINE__);
            }
        }
    }

    $api['response'] = 'ok';
    p4c_close(DB_HOST);
    p4c_errorlog(error_get_last());
    print_xml($api);
}


$api['error'] = 'update member infos get false';
$class_errorlog->log($api['error']."\n".print_r(filter_input_array(INPUT_GET), true),__FILE__,__LINE__);
p4c_close(DB_HOST);
p4c_errorlog(error_get_last());
print_xml($api);

?>