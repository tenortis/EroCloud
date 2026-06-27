<?php

if (!defined('SAFE_INC')) {
    die ("Access not allowed");
}

// Wenn keine movie_id angegeben wurde
if(!isset($_GET['actor_id'])) {
    $api['error'] = 'actor not set';
    print_xml($api);
}

$get_actor_id = abs(filter_input(INPUT_GET, 'actor_id', FILTER_SANITIZE_NUMBER_INT));
$get_erocms_member_id = abs(filter_input(INPUT_GET, 'erocms_member_id', FILTER_SANITIZE_NUMBER_INT));
$get_erocms_actor_id = abs(filter_input(INPUT_GET, 'erocms_actor_id', FILTER_SANITIZE_NUMBER_INT));

// Profil abfragen
$rs_actor_cams = p4c_query("SELECT * FROM `actor_cams` WHERE `actor_id`='".abs($get_actor_id)."' LIMIT 1;",__FILE__,__LINE__);
if(p4c_num_rows($rs_actor_cams) == '0') {
    $api['error'] = 'actor-cam not exists';
    print_xml($api);
}


// Wenn Actor nicht existiert
$rs_actor = p4c_query("SELECT * FROM `actors` WHERE `id`='".abs($get_actor_id)."' LIMIT 1;",__FILE__,__LINE__);        
if (p4c_num_rows($rs_actor) == 0) {
    $api['error'] = 'actor not exists';
    print_xml($api);
}

$actor_obj = p4c_fetch_object($rs_actor);

$actor_id = $actor_obj->id;
$merchant_id = $actor_obj->merchant_id;
$cam_user_auto_on = $actor_obj->cam_user_auto_on;


$chat_id = $get_actor_id.'_'.$p4c_shop_id.'_'.$get_erocms_member_id;

$rs_actor_member_info = p4c_query("SELECT * FROM `actor_member_info` WHERE
    `erocms_amateur_id`='".abs($get_erocms_actor_id)."' AND 
    `erocms_member_id`='".abs($get_erocms_member_id)."' AND 
    `domain`='".p4c_escape_string($domain)."' AND 
    `p4c_shop_id`='".abs($p4c_shop_id)."'
LIMIT 1;",__FILE__,__LINE__);

if (p4c_num_rows($rs_actor_member_info) == 0) {

    include_once(SOURCEDIR.'/includes/klassen/actor.inc.php');
    $actor = new Actor($get_actor_id);
    
    $erocms_chat_id = $get_erocms_actor_id.'_'.$get_erocms_member_id;
    $remote_member_id = $p4c_shop_id.'_'.$get_erocms_member_id;
    
    include_once(SOURCEDIR.'/includes/klassen/member.inc.php');
    $member = new Member($remote_member_id);
    
    p4c_query("INSERT INTO `actor_member_info` SET 
        `chat_id`           = '".p4c_escape_string($chat_id)."',
        `erocms_amateur_id` = '".abs($get_erocms_actor_id)."', 
        `erocms_member_id`  = '".abs($get_erocms_member_id)."',
        `erocms_chat_id`    = '".p4c_escape_string($erocms_chat_id)."',
        `p4c_shop_id`       = '".abs($p4c_shop_id)."',
        `remote_member_id`  = '".p4c_escape_string($remote_member_id)."',
        `domain`            = '".p4c_escape_string($domain)."',
        `merchant_id`       = '".abs($merchant_id)."',
        `actor_id`          = '".abs($actor_id)."',
        `member_id`         = '".abs($member->id())."',
        `pn_amount`         = '".abs($actor->get('pn_amount'))."',
        `pn_free_when_webcam` = '".abs($actor->get('pn_free_if_webcam'))."',
        `cam2cam_amount`    = '".abs($actor->get('cam2cam_amount'))."',
        `cam_amount`        = '".abs($actor->get('cam_amount'))."',
        `is_cam_free`       = '0',
        `member_is_typing`  = '0',
        `actor_is_typing`   = '0',
        `user_notes`        = '',
        `is_synct`          = '1'
    ",__FILE__,__LINE__);
    
}

$rs_actor_member_info = p4c_query("SELECT * FROM `actor_member_info` WHERE `chat_id`='". p4c_escape_string($chat_id)."' LIMIT 1;",__FILE__,__LINE__);

// Wenn es noch keine Infos zum User gibt
if (p4c_num_rows($rs_actor_member_info) == 0) {
    $api['error'] = 'chatID ('.$chat_id.') not exists';
    print_xml($api);
}


$actor_cam_obj = p4c_fetch_object($rs_actor_cams);

$rs_actor_cam_members = p4c_query("SELECT * FROM `members_seeing_actor_cam` WHERE `chat_id` = '".p4c_escape_string($chat_id)."' LIMIT 1;",__FILE__,__LINE__);

$cam_watching   = 0;

if ($cam_user_auto_on === 1) {
    $cam_watching = 1;
}

// INSERT - User will Cam sehen
if (p4c_num_rows($rs_actor_cam_members) == 0) {
    
    $cam_amount     = $actor_obj->cam_amount;
    
    p4c_query("INSERT INTO `members_seeing_actor_cam` SET
        `chat_id`       = '".p4c_escape_string($chat_id)."', 
        `merchant_id`   = '".abs($merchant_id)."',
        `actor_id`      = '".abs($actor_id)."',
        `remote_member_id` = '".abs($get_erocms_member_id)."',
        `cam_time`      = '".(time()+10)."',
        `cam_watching`  = '".$cam_watching."',
        `cam_amount`    = '".abs($cam_amount)."',
        `cam_sound`     = '1'
    ;",__FILE__,__LINE__);


// UPDATE - User will cam sehen
} else {
    
    p4c_query("UPDATE `members_seeing_actor_cam` SET
        `cam_time`      = '".(time()+10)."'
    WHERE 
        `chat_id`       = '".p4c_escape_string($chat_id)."' AND
        `merchant_id`   = '".abs($merchant_id)."' AND
        `actor_id`      = '".abs($actor_id)."' AND
        `remote_member_id` = '".abs($get_erocms_member_id)."'
    LIMIT 1;",__FILE__,__LINE__);

    $actor_cam_members_obj = p4c_fetch_object($rs_actor_cam_members);
   
    $cam_amount         = $actor_cam_members_obj->cam_amount;
    $cam_watching       = $actor_cam_members_obj->cam_watching;
}



$api['cam']['actor_id']         = $actor_id;
$api['cam']['user_auto_on']     = $cam_user_auto_on;
$api['cam']['cam_amount']       = $cam_amount;
$api['cam']['watching']         = $cam_watching;
$api['cam']['streamserver_url'] = $actor_cam_obj->streamserver_url;
