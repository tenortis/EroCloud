<?php

if (!defined('SAFE_INC')) {
    die ("Access not allowed");
}

// Wenn keine actor_id angegeben wurde
if(!isset($_GET['erocloud_actor_id'])) {
    $api['error'] = 'erocloud_actor_id not exists';
    print_xml($api);
}

// Wenn keine remote_actor_id angegeben wurde
if(!isset($_GET['remote_actor_id'])) {
    $api['error'] = 'remote_actor_id not exists';
    print_xml($api);
}

if (!isset($p4c_shop_id) OR empty($p4c_shop_id)) {
    $api['error'] = 'p4c_shop_id not exists';
    print_xml($api);    
}

$get_erocloud_actor_id = abs(filter_input(INPUT_GET, 'erocloud_actor_id', FILTER_SANITIZE_NUMBER_INT));
$get_remote_actor_id = abs(filter_input(INPUT_GET, 'remote_actor_id', FILTER_SANITIZE_NUMBER_INT));

// Profil abfragen
$rs_actor = p4c_query("SELECT * FROM `actors` WHERE `id`='".abs($get_erocloud_actor_id)."' LIMIT 1;",__FILE__,__LINE__);

if(p4c_num_rows($rs_actor) == '0') {
    $api['error'] = 'actor not exists';
    print_xml($api);
} else {
    
    $actor_obj = p4c_fetch_object($rs_actor);
                   
    // Prüfen ob Darsteller schon syncronisiert wurde / mit Cloud verbunden ist
    $rs_check_sync = p4c_query("SELECT * FROM `messenger_sync` WHERE
        `merchant_id`   = '".abs($actor_obj->merchant_id)."' AND
        `actor_id`      = '".abs($get_erocloud_actor_id)."' AND
        `remote_actor_id`='".abs($get_remote_actor_id)."' AND
        `p4c_shop_id`   = '".abs($p4c_shop_id)."'
    LIMIT 1;",__FILE__,__LINE__);
    
    if (p4c_num_rows($rs_check_sync,__FILE__,__LINE__) === 0) {

        p4c_query("INSERT INTO `messenger_sync` SET
            `merchant_id`   = '".abs($actor_obj->merchant_id)."',
            `actor_id`      = '".abs($get_erocloud_actor_id)."',
            `remote_actor_id`='".abs($get_remote_actor_id)."',
            `apikey`        = AES_ENCRYPT('".p4c_escape_string($api_key)."','".AES_KEY."'),
            `domain`        = '". p4c_escape_string($domain)."',
            `p4c_shop_id`   = '".abs($p4c_shop_id)."';
        ",__FILE__,__LINE__);
    } else {
        p4c_query("UPDATE `messenger_sync` SET
            `apikey`        = AES_ENCRYPT('".p4c_escape_string($api_key)."','".AES_KEY."'),
            `domain`        = '". p4c_escape_string($domain)."'
        WHERE 
            `merchant_id`   = '".abs($actor_obj->merchant_id)."' AND
            `actor_id`      = '".abs($get_erocloud_actor_id)."' AND
            `remote_actor_id`='".abs($get_remote_actor_id)."' AND
            `p4c_shop_id`   = '".abs($p4c_shop_id)."'
        LIMIT 1;",__FILE__,__LINE__);
        
        /**
         *  Prüfe ob der es Bereits Verbindungen zw. dem Darsteller und Kunden der Seite gab
         *  Wenn die übergeben "remote_actor_id" nicht mit der hinterlegten in der Datenbank übersinstimmt,
         *  hat sich diese vermutlich auf der Webseite geändert und muss in der Datenbank aktualisiert werden.
         */
        
        $rs_check_actor_member_info = p4c_query("SELECT * FROM `actor_member_info` WHERE
            `merchant_id`   = '".abs($actor_obj->merchant_id)."' AND
            `actor_id`      = '".abs($get_erocloud_actor_id)."' AND
            `p4c_shop_id`   = '".abs($p4c_shop_id)."' AND
            `erocms_amateur_id`!='".abs($get_remote_actor_id)."';",__FILE__,__LINE__);
        
        if (p4c_num_rows($rs_check_actor_member_info) > 0) {
           
            while ($actor_member_obj = p4c_fetch_object($rs_check_actor_member_info)) {
                $class_errorlog->log("",__FILE__,__LINE__);
                $explode = explode('_',$actor_member_obj->erocms_chat_id);
                
                if (count($explode) == 2) {
                    $erocms_chat_id = $get_remote_actor_id.'_'.$explode[1];

                    p4c_query("UPDATE `actor_member_info` SET
                        `erocms_amateur_id` = '".abs($get_remote_actor_id)."',
                        `erocms_chat_id`    = '".abs($erocms_chat_id)."'   
                    WHERE
                        `id`            = '".abs($actor_member_obj->id)."' AND
                        `merchant_id`   = '".abs($actor_obj->merchant_id)."' AND
                        `actor_id`      = '".abs($get_erocloud_actor_id)."' AND
                        `p4c_shop_id`   = '".abs($p4c_shop_id)."'
                    LIMIT 1;",__FILE__,__LINE__);
                }                
            }            
        }
        
        
        $rs_check_chat_messages = p4c_query("SELECT * FROM `chat_messages` WHERE
            `merchant_id`   = '".abs($actor_obj->merchant_id)."' AND
            `p4c_shop_id`   = '".abs($p4c_shop_id)."' AND
            (
                (`von`='actor' AND `von_id` = '".abs($get_erocloud_actor_id)."' AND `erocms_von_id` != '".abs($get_remote_actor_id)."') OR 
                (`von`='member' AND `an_id` = '".abs($get_erocloud_actor_id)."' AND `erocms_an_id` != '".abs($get_remote_actor_id)."')
            )            
            ;",__FILE__,__LINE__);
        
        if (p4c_num_rows($rs_check_chat_messages) > 0) {
           
            while ($actor_member_obj = p4c_fetch_object($rs_check_chat_messages)) {
                
                if ($actor_member_obj->von == 'actor') {
                    $erocms_von_id = $get_remote_actor_id;
                    $erocms_an_id = $actor_member_obj->erocms_an_id;
                    
                } else {
                    $erocms_von_id = $actor_member_obj->erocms_von_id;
                    $erocms_an_id = $get_remote_actor_id;  
                }
               
                p4c_query("UPDATE `chat_messages` SET
                    `erocms_von_id` = '".abs($erocms_von_id)."',
                    `erocms_an_id` = '".abs($erocms_an_id)."'
                WHERE 
                    `id` = '".abs($actor_member_obj->id)."' AND
                    `merchant_id`   = '".abs($actor_obj->merchant_id)."' AND
                    `p4c_shop_id`   = '".abs($p4c_shop_id)."'                        
                LIMIT 1;",__FILE__,__LINE__);
                
            }            
        }    
        
        $rs_check_chat_messages_history = p4c_query("SELECT * FROM `chat_messages_history` WHERE
            `merchant_id`   = '".abs($actor_obj->merchant_id)."' AND
            `p4c_shop_id`   = '".abs($p4c_shop_id)."' AND
            (
                (`von`='actor' AND `von_id` = '".abs($get_erocloud_actor_id)."' AND `erocms_von_id` != '".abs($get_remote_actor_id)."') OR 
                (`von`='member' AND `an_id` = '".abs($get_erocloud_actor_id)."' AND `erocms_an_id` != '".abs($get_remote_actor_id)."')
            )
            ;",__FILE__,__LINE__);
        
        if (p4c_num_rows($rs_check_chat_messages_history) > 0) {
           
            while ($actor_member_obj = p4c_fetch_object($rs_check_chat_messages_history)) {
                
                if ($actor_member_obj->von == 'actor') {
                    $erocms_von_id = $get_remote_actor_id;
                    $erocms_an_id = $actor_member_obj->erocms_an_id;
                    
                } else {
                    $erocms_von_id = $actor_member_obj->erocms_von_id;
                    $erocms_an_id = $get_remote_actor_id;  
                }
               
                p4c_query("UPDATE `chat_messages_history` SET
                    `erocms_von_id` = '".abs($erocms_von_id)."',
                    `erocms_an_id` = '".abs($erocms_an_id)."'
                WHERE 
                    `id` = '".abs($actor_member_obj->id)."' AND
                    `merchant_id`   = '".abs($actor_obj->merchant_id)."' AND
                    `p4c_shop_id`   = '".abs($p4c_shop_id)."'                        
                LIMIT 1;",__FILE__,__LINE__);
                
            }            
        } 
        
    }
    
    
    $api['status'] = 'connect_ok';
    print_xml($api);
}