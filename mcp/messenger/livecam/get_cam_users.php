<?php

define('SAFE_INC', 1);

$sourcedir = dirname(dirname(dirname(dirname(__FILE__))));

include_once($sourcedir."/config.inc.php");
include_once(MCP_DIR."/common.inc.php");

if (!isset($_SESSION['merchant_id'])) {
    die('Access not allowed!');
}

$post_actor_id = abs(filter_input(INPUT_POST, 'actor_id', FILTER_SANITIZE_NUMBER_INT));

p4c_query("DELETE FROM `members_seeing_actor_cam` WHERE `cam_time`<='".time()."';", __FILE__, __LINE__);
p4c_query("UPDATE `actor_cams` SET `datetime`='".date("Y-m-d H:i:s")."' WHERE `actor_id`='".abs($post_actor_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);

$rs_members_seeing_actor_cam = p4c_query("SELECT * FROM `members_seeing_actor_cam` WHERE `actor_id`='".abs($post_actor_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);

$site = '<div class="ui-widget-header">Folgende User wollen deine LiveCam sehen</div>';

if (p4c_num_rows($rs_members_seeing_actor_cam) == 0) {
    $site .= '<center>- keiner -</center>';    
    
} else {
    
    $rs_cam_settings = p4c_query("SELECT `cam_user_auto_on`, `cam_new_user_sound` FROM `actors` WHERE `id`='".abs($post_actor_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);
    $cam_settings_obj = p4c_fetch_object($rs_cam_settings);

    while($cam_member_obj = p4c_fetch_object($rs_members_seeing_actor_cam)) {
        #$db_member_id       = $cam_member_obj->member_id;
        $db_cam_watching    = $cam_member_obj->cam_watching;
        $db_cam_sound       = $cam_member_obj->cam_sound;

        $explode = explode('_', $cam_member_obj->chat_id);
        $remote_member_id = $explode[1].'_'.$explode[2];

        $rs_member = p4c_query("SELECT `id`, `username` FROM `members` WHERE `remote_member_id`='".p4c_escape_string($remote_member_id)."' LIMIT 1;", __FILE__, __LINE__);
        if (p4c_num_rows($rs_member) == 1) {
            $member_obj = p4c_fetch_object($rs_member);

            $watching = '';
            if ($db_cam_watching == '1') {
                $watching = '<a href="javascript:jQuery(this).cam_activate(\''.$cam_member_obj->chat_id.'\', \'0\')">ausschalten</a>';
            } else {

                if ($cam_settings_obj->cam_user_auto_on == '1') {
                    p4c_query("UPDATE `members_seeing_actor_cam` SET
                        `cam_watching`='1'
                    WHERE 
                        `chat_id`='". p4c_escape_string($cam_member_obj->chat_id)."' AND
                        `actor_id`='".abs($post_actor_id)."' AND
                        `merchant_id`='".abs($_SESSION['merchant_id'])."'
                    LIMIT 1;", __FILE__, __LINE__);
                }

                $watching = '<a href="javascript:jQuery(this).cam_activate(\''.$cam_member_obj->chat_id.'\', \'1\')">einschalten</a>';
            }
            
            if ($db_cam_sound == '1') {
                $play_sound_new_cam_user = true;
                
                p4c_query("UPDATE `members_seeing_actor_cam` SET
                    `cam_sound`='0'
                WHERE
                    `chat_id`='".p4c_escape_string($cam_member_obj->chat_id)."' AND
                    `actor_id`='".abs($post_actor_id)."' AND
                    `merchant_id`='".abs($_SESSION['merchant_id'])."'
                LIMIT 1;", __FILE__, __LINE__);
            }

            $site .= '<div style="padding-left:5px;"><a href="javascript:jQuery(this).open_chat(\''.$cam_member_obj->chat_id.'\');">'.$member_obj->username.'</a> - '.$watching.' </div>';
        }
    }
    
    if (isset($play_sound_new_cam_user)) {
        $site .= '
        <script>
            jQuery(document).ready(function() {
                var file = "'.MCP_URL.'/Messenger/Sound/erocloud_female";
                jQuery("#playsound").html("<audio>"+
                    "<source src=\""+file+".mp3\" type=\"audio/mpeg\">"+
                "</audio>");

                jQuery("#playsound audio").trigger("play");
        
            })
        </script>
        <div id="playsound"></div>
        ';
        /*
        <audio autoplay="autoplay" >
            <source src="'.MCP_URL.'/Messenger/Sound/'.$cam_settings_obj->cam_new_user_sound.'.ogg" type="audio/ogg" />
            <source src="'.MCP_URL.'/Messenger/Sound/'.$cam_settings_obj->cam_new_user_sound.'.mp3" type="audio/mp3" />
        </audio>';
         */
    }
   
}

echo $site;

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());