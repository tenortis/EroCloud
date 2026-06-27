<?php

define('SAFE_INC', 1);

$sourcedir = dirname(dirname(dirname(dirname(__FILE__))));

include_once($sourcedir."/config.inc.php");
include_once(MCP_DIR."/common.inc.php");

if (!isset($_SESSION['merchant_id'])) {
    die('Access not allowed!');
}

// Dise Funktion muss identisch mit der in /messenger/livecam/submit.php sein
function stream_key() { 
    global $mysql;
    
    $merchant = new Merchant($mysql,$_SESSION['merchant_id']);

    $string = $merchant->partner_id().hash('crc32b', $merchant->api_key('aes_decrypt').$_SESSION['merchant_id'].'erocloud');
    $str_to_hash = str_replace(array('0','O'), 'W', $string);
    $str_to_hash = str_replace('I', 'L', $str_to_hash);
    $str_to_hash = strtoupper($str_to_hash);
    $str_to_hash = str_split($str_to_hash, 6);
    $str_to_hash = implode('-', $str_to_hash);
    
    return $str_to_hash;
}



// Webcam jetzt starten und Streamname zurückgeben
if (isset($_POST['cam_senden']) AND $_POST['cam_senden'] == 'true' AND isset($_POST['actor_id'])) {
    
    $actor_id = abs($_POST['actor_id']);
    $stream_id = stream_key();
     
    $rs_check = p4c_query("SELECT * FROM `actors` WHERE `id`='".$actor_id."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);
    
    if (p4c_num_rows($rs_check,__FILE__,__LINE__) > 0) {
    
        $rs_cam = p4c_query("SELECT * FROM `actor_cams` WHERE `actor_id`='".$actor_id."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);
        if (p4c_num_rows($rs_cam,__FILE__,__LINE__) > 0) {
            p4c_query("UPDATE `actor_cams` SET
                `datetime`='".time()."',
                `stream_id` = '".$stream_id."'
            WHERE `actor_id`='".$actor_id."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);	
            echo $stream_id;
        } else {
            
            #$streamserver_url = 'rtmp://91.184.46.55/live';
            #$streamserver_url = 'rtmp://stream.erocms.net/show';
            $streamserver_url = STREAMSERVER['server1']['http_url'];
	
            p4c_query("INSERT INTO `actor_cams` SET
                `actor_id`      = '".$actor_id."',
                `merchant_id`   = '".abs($_SESSION['merchant_id'])."',
                `stream_id`     = '".$stream_id."',
                `streamserver_url` = '".$streamserver_url."',
                `datetime`      = '".date("Y-m-d H:i:s")."';", __FILE__, __LINE__);

            echo $stream_id;
        }
    } else {
        echo 'actor_not_exist';
    }
    exit;
}

// Webcam beenden
if (isset($_POST['cam_beenden']) AND $_POST['cam_beenden'] == 'true' AND isset($_POST['actor_id'])) {
    
    $actor_id = abs($_POST['actor_id']);
    
    p4c_query("DELETE FROM `actor_cams` WHERE `actor_id`='".$actor_id."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);
    p4c_query("DELETE FROM `members_seeing_actor_cam` WHERE `actor_id`='".$actor_id."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);

}

// Cam de-/activate
if (isset($_POST['activate'])) {
    
    $post_chat_id = filter_input(INPUT_POST, 'chat_id', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $post_chat_id = preg_replace( '/[^0-9a-z_]/i', '', $post_chat_id);
    
    if ($_POST['activate'] == 0) {
        #echo "DELETE FROM `members_seeing_actor_cam` WHERE `chat_id`='". p4c_escape_string($post_chat_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;";
        p4c_query("DELETE FROM `members_seeing_actor_cam` WHERE `chat_id`='". p4c_escape_string($post_chat_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);
    } else {
        p4c_query("UPDATE `members_seeing_actor_cam` SET `cam_watching`='1' WHERE `chat_id`='".p4c_escape_string($post_chat_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);
        #echo "UPDATE `members_seeing_actor_cam` SET `cam_watching`='1' WHERE `chat_id`='".p4c_escape_string($post_chat_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;";
    }
}

// Sound on/off
if (isset($_POST['sound'])) {
    $post_chat_id = filter_input(INPUT_POST, 'chat_id', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $post_chat_id = preg_replace( '/[^0-9a-z_]/i', '', $post_chat_id);
    
    p4c_query("UPDATE `members_seeing_actor_cam` SET `cam_sound`='".abs($_POST['sound'])."' WHERE `chat_id`='". p4c_escape_string($post_chat_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);
}

// Profile für die Webcam laden
if (isset($_POST['group_actors']) AND $_POST['group_actors'] === 'get') {
    
    $send_jason = array();
    
    if (!isset($_SESSION['my_chatgroup']) OR $_SESSION['my_chatgroup'] === '') {
        $send_jason['error'] = 'no_group_selected';
    } else {

        $rs_group_actors = p4c_query("SELECT * FROM `group_actors` LEFT JOIN `actors` ON `group_actors`.`actor_id`=`actors`.`id` WHERE `group_actors`.`group_id`='".abs($_SESSION['my_chatgroup'])."';",__FILE__,__LINE__);

        if (p4c_num_rows($rs_group_actors,__FILE__,__LINE__) === 0) {
            $send_jason['error'] = 'actor_not_found';
        }

        while ($actor_obj = p4c_fetch_object($rs_group_actors)) {
            $send_jason['group_actors'][] = array(
                'actor_id'      => $actor_obj->actor_id,
                'actor_username'=> utf8_encode($actor_obj->username)
            );
        }
    }
    
    
    echo json_encode($send_jason);
}

if (isset($_POST['get_cam_user_auto_on']) AND isset($_POST['actor_id'])) {
    $actor_id = abs($_POST['actor_id']);
  
    $rs_cam_settings = p4c_query("SELECT `cam_user_auto_on` FROM `actors` WHERE `id`='".abs($actor_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);
    echo p4c_result($rs_cam_settings,0);

} else if (isset($_POST['set_cam_user_auto_on']) AND isset($_POST['actor_id'])) {
    $actor_id = abs($_POST['actor_id']);
    $cam_user_auto_on = abs($_POST['set_cam_user_auto_on']);
  
    p4c_query("UPDATE `actors` SET
        `cam_user_auto_on`='".abs($cam_user_auto_on)."'
    WHERE 
        `id`='".abs($actor_id)."' AND
        `merchant_id`='".abs($_SESSION['merchant_id'])."'
    LIMIT 1;", __FILE__, __LINE__);
    
    echo $_POST['set_cam_user_auto_on'];
}


p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());