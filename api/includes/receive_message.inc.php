<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

// Das hier war nur zum testen un kann gelöscht werden
// SELECT remote_member_id, erocms_chat_id, COUNT(*) c FROM `actor_member_info` GROUP BY erocms_chat_id, domain HAVING c > 1 ORDER BY `c` DESC


// gelesen - Status: 0 ungelesen, 1 gelesen, 2 zugestellt

$check_parameters = [
    'actor_commision',
    'actor_commision_percent',
    'beantwortet',
    'datetime',
    'domain',
    'gelesen',
    'geloescht',
    'is_mobile',
    'nachricht',
    'p4c_shop_id',
    'preis_coins',
    'systemnachricht',
    'von',
    'von_id'
];

foreach($check_parameters as $value) {
    if (!isset($_GET[$value]) OR trim($_GET[$value]) === '') {
        $api['error'] = '$_GET["'.$value.'"] empty';
        $class_errorlog->log($api['error']."\n".print_r(filter_input(INPUT_GET), true),__FILE__,__LINE__);
        print_xml($api);
    }
}

$von = trim($_GET['von']);
if ($von !== 'actor' AND $von !== 'member') {
    $class_errorlog->log($api['error']."\n".print_r(filter_input(INPUT_GET), true),__FILE__,__LINE__);
    $api['error'] = "'von' false";
    print_xml($api);
}

$erocms_an_id   = abs($_GET['an_id']);
$beantwortet    = abs($_GET['beantwortet']);
$datetime       = date("Y-m-d H:i:s", strtotime($_GET['datetime']));
$domain         = strip_tags($_GET['domain']);
$gelesen        = abs($_GET['gelesen']);
$geloescht      = abs($_GET['geloescht']);
$is_mobile      = abs($_GET['is_mobile']);
$message        = $_GET['nachricht'];
$p4c_shop_id    = abs($_GET['p4c_shop_id']);
$message_price  = abs($_GET['preis_coins']);
$actor_commision= abs($_GET['actor_commision']);
$actor_commision_percent = abs($_GET['actor_commision_percent']);
$systemnachricht= abs($_GET['systemnachricht']);
$erocms_von_id  = abs($_GET['von_id']);

$rs_actor_member_info = p4c_query("SELECT * FROM `actor_member_info` WHERE
    `erocms_amateur_id`='".abs($erocms_an_id)."' AND 
    `erocms_member_id`='".abs($erocms_von_id)."' AND 
    `domain`='".p4c_escape_string($domain)."' AND 
    `p4c_shop_id`='".abs($p4c_shop_id)."'
LIMIT 1;",__FILE__,__LINE__);

if (p4c_num_rows($rs_actor_member_info) == 0) {
 
    $erocms_chat_id = $erocms_an_id.'_'.$erocms_von_id;
    $remote_member_id = $p4c_shop_id.'_'.$erocms_von_id;
    
    // Prüfen ob Amateur schon syncronisiert wurde
    $rs_check_sync = p4c_query("SELECT * FROM `messenger_sync` WHERE
        `remote_actor_id`   = '".abs($erocms_an_id)."' AND 
        `p4c_shop_id`       = '".abs($p4c_shop_id)."'
    LIMIT 1;",__FILE__,__LINE__);

    if (p4c_num_rows($rs_check_sync) === 0) {
        $api['error'] = "sync false";
        $class_errorlog->log($api['error']."\n".print_r($_GET,true),__FILE__,__LINE__);
        print_xml($api);
    }
    
    $messenger_sync_obj = p4c_fetch_object($rs_check_sync);

    $chat_id = $messenger_sync_obj->actor_id."_".$p4c_shop_id."_".$erocms_von_id;
    
    include_once(SOURCEDIR.'/includes/klassen/member.inc.php');
    $member = new Member($remote_member_id);

    if ($member->id() == '') {
        p4c_query("INSERT INTO `members` SET 
            `remote_member_id`  = '".p4c_escape_string($remote_member_id)."',
            `p4c_shop_id`       = '".p4c_escape_string($p4c_shop_id)."',
            `username`          = '".p4c_escape_string($remote_member_id)."',
            `lastonline`        = '".date("Y-m-d H:i:s")."';",__FILE__,__LINE__);
    }
    
    include_once(SOURCEDIR.'/includes/klassen/actor.inc.php');
    $actor = new Actor($messenger_sync_obj->actor_id);
    
    if(p4c_query("INSERT INTO `actor_member_info` SET 
        `chat_id`           = '".p4c_escape_string($chat_id)."',
        `erocms_amateur_id` = '".abs($erocms_an_id)."', 
        `erocms_member_id`  = '".abs($erocms_von_id)."',
        `erocms_chat_id`    = '".p4c_escape_string($erocms_chat_id)."',
        `p4c_shop_id`       = '".abs($p4c_shop_id)."',
        `remote_member_id`  = '".p4c_escape_string($remote_member_id)."',
        `domain`            = '".p4c_escape_string($domain)."',
        `merchant_id`       = '".abs($messenger_sync_obj->merchant_id)."',
        `actor_id`          = '".abs($messenger_sync_obj->actor_id)."',
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

    ",__FILE__,__LINE__)) {
        $rs_actor_member_info = p4c_query("SELECT * FROM `actor_member_info` WHERE
            `erocms_amateur_id`='".abs($erocms_an_id)."' AND 
            `erocms_member_id`='".abs($erocms_von_id)."' AND 
            `domain`='".p4c_escape_string($domain)."' AND 
            `p4c_shop_id`='".abs($p4c_shop_id)."'
        LIMIT 1;",__FILE__,__LINE__);
    }
}
    
$member_info_obj = p4c_fetch_object($rs_actor_member_info);

if ($von === 'actor') {
    $von_id = $member_info_obj->actor_id;
    $an_id = $member_info_obj->member_id;
} elseif ($von === 'member') {
    $von_id = $member_info_obj->member_id;
    $an_id = $member_info_obj->actor_id;
}

include_once(SOURCEDIR.'/includes/klassen/ChatMessage.inc.php');

$mess = new ChatMessage;

$mess->chat_id      = p4c_escape_string($member_info_obj->chat_id);
$mess->p4c_shop_id  = abs($member_info_obj->p4c_shop_id);
$mess->merchant_id  = abs($member_info_obj->merchant_id);
$mess->erocms_von_id= abs($erocms_von_id);
$mess->erocms_an_id = abs($erocms_an_id);
$mess->domain       = p4c_escape_string($domain);
$mess->von          = p4c_escape_string($von);
$mess->von_id       = abs($von_id);
$mess->an_id        = abs($an_id);
$mess->message_price= abs($message_price);
$mess->actor_commision = abs($actor_commision);
$mess->actor_commision_percent = abs($actor_commision_percent);
$mess->message      = $message;
$mess->datetime     = p4c_escape_string($datetime);
$mess->is_mobile    = abs($is_mobile);
$mess->systemnachricht= abs($systemnachricht);
$mess->gelesen      = abs($gelesen);
$mess->beantwortet  = abs($beantwortet);
$mess->geloescht    = abs($geloescht);

$response = $mess->send();

$api['response'] = $response;
print_xml($api);