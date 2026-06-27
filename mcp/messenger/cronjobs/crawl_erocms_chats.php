<?php

define('SAFE_INC', 1);

$sourcedir = dirname(dirname(dirname(dirname(__FILE__))));

include_once($sourcedir."/config.inc.php");
include_once(MCP_DIR."/common.inc.php");

header('Content-Type: text/html; charset=utf-8');

$rs_actor_member_info = p4c_query("SELECT * FROM `messenger_sync`, `actor_member_info` WHERE `sync_time`='0000-00-00 00:00:00' AND `actor_member_info`.`is_synct`='0' AND `messenger_sync`.`actor_id`=`actor_member_info`.`actor_id` ORDER BY `messenger_sync`.`id` ASC LIMIT 50;",__FILE__,__LINE__);
#$rs_actor_member_info = p4c_query("SELECT * FROM `actor_member_info` WHERE `is_synct`='0' LIMIT 50;",__FILE__,__LINE__);

if (p4c_num_rows($rs_actor_member_info) > 0) {
    while($member_info_obj = p4c_fetch_object($rs_actor_member_info)) {
    
        $merchant = new Merchant($mysql,$member_info_obj->merchant_id);

        $merchant_id = $merchant->id();
        $actor_id    = $member_info_obj->actor_id;

        $search = array('http://', 'https://', 'www.');
        $domain = str_replace($search, '', $member_info_obj->domain);

        $param = array(
            "api_key" => $merchant->api_key('aes_decrypt'),
            "user_chat"=> $member_info_obj->erocms_chat_id,
            "actor_id"=> $member_info_obj->erocms_amateur_id
        );

        $param = array_filter($param, "strlen"); // leere Einträge Entfernen
        ksort($param); // Alphabetische Sortierung
        $query = http_build_query($param, '&amp;');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://'.$domain.'/erocloud_api/send_erocms_chats.php?'.$query);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1); 
        curl_setopt($ch, CURLOPT_REFERER, URL);
        curl_setopt($ch, CURLOPT_USERAGENT, PROJECTNAME." - ".COMPANYNAME." - ".URL);
        $data = curl_exec($ch);
        curl_close($ch);

        if($data != false) {
            $chats_obj = json_decode($data);
            if (!isset($chats_obj->chat)) {
                exit;
            }

            $chat_obj = $chats_obj->chat; 

            $count_chats = count((array)$chat_obj);

            foreach ($chat_obj as $erocms_chat_id => $chat_ary) {

                if (!isset($chat_ary->empty_chat)) {
                    
                    $explode_von = explode(':',$chat_ary->von);

                    $von = 'member';
                    if ($explode_von[0] == 'sender') {
                        $von = 'actor';
                    }

                    if ($von == 'actor') {
                        $von_id = $actor_id;
                        $an_id = $member_info_obj->member_id;
                    } else if ($von == 'member') {
                        $von_id = $member_info_obj->member_id;
                        $an_id = $actor_id;
                    }

                    include_once(SOURCEDIR.'/includes/klassen/ChatMessage.inc.php');
                    $mess = new ChatMessage;

                    $mess->chat_id      = p4c_escape_string($member_info_obj->chat_id);
                    $mess->p4c_shop_id  = abs($member_info_obj->p4c_shop_id);
                    $mess->merchant_id  = abs($merchant_id);
                    $mess->erocms_von_id= abs($chat_ary->von_id);
                    $mess->erocms_an_id = abs($chat_ary->an_id);
                    $mess->von          = p4c_escape_string($von);
                    $mess->von_id       = abs($von_id);
                    $mess->an_id        = abs($an_id);
                    $mess->message_price= abs($chat_ary->preis_coins);
                    $mess->message      = p4c_escape_string($chat_ary->nachricht);
                    $mess->datetime     = p4c_escape_string($chat_ary->datetime);
                    $mess->is_mobile    = abs($chat_ary->is_mobile);
                    $mess->systemnachricht= abs($chat_ary->systemnachricht);
                    $mess->gelesen      = abs($chat_ary->gelesen);
                    $mess->beantwortet  = abs($chat_ary->beantwortet);
                    $mess->geloescht    = abs($chat_ary->geloescht);

                    $mess->send();
                }
            }
            
            if (p4c_query("UPDATE `actor_member_info` SET `is_synct`='1' WHERE `chat_id`='".p4c_escape_string($member_info_obj->chat_id)."' LIMIT 1; ",__FILE__,__LINE__)) {
                p4c_query("UPDATE `messenger_sync` SET
                    `chats_synct`=chats_synct+1
                WHERE `merchant_id`='".abs($merchant_id)."' AND `actor_id`='".abs($actor_id)."' AND `domain`='".p4c_escape_string($domain)."' LIMIT 1;",__FILE__,__LINE__);
            }

        }
    }
} else {
    p4c_query("UPDATE `messenger_sync` SET `sync_time`='".date("Y-m-d H:i:s")."' WHERE `chats_total`=`chats_synct`",__FILE__,__LINE__);
}



p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());


?>