<?php
 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");
    
header('Content-Type: text/html; charset=utf-8');

$rs_sync = p4c_query("SELECT * FROM `messenger_sync` WHERE
    (`sync_time`='0000-00-00 00:00:00' OR `chats_synct`<`chats_total` OR `chats_total`='0') AND 
    `domain`    ='".p4c_escape_string($domain)."' AND 
    `actor_id`  = '".abs($actor_id)."' AND
    AES_DECRYPT(`apikey`, '".AES_KEY."') = '".p4c_escape_string($api_key)."'
LIMIT 1;",__FILE__,__LINE__);

if (p4c_num_rows($rs_sync) > 0) {
    
    $error = 'Es konnte keine Verbindung hergestellt werden.';
    
    $site_ary = p4c_fetch_object($rs_sync);
    
    $merchant = new Merchant($mysql,$site_ary->merchant_id);
    
    $merchant_id= $merchant->id();
    $actor_id   = $site_ary->actor_id;
    
    $search = array('http://', 'https://', 'www.');
    $domain = str_replace($search, '', $site_ary->domain);

    $param = array(
        "api_key" => $merchant->api_key('aes_decrypt'),
        "all_user"=> 'true',
        "actor_id"=> $site_ary->remote_actor_id
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
   
    if($data != false) {
        
        curl_close($ch);

        $error = '';
        $chats_obj = json_decode($data);
       
        if (isset($chats_obj->member)) {
            $member_obj = $chats_obj->member;
            foreach ($member_obj as $remote_member_id => $member_ary) {
                $rs_members = p4c_query("SELECT * FROM `members` WHERE `remote_member_id`='". p4c_escape_string($remote_member_id)."' LIMIT 1;",__FILE__,__LINE__);

                if (p4c_num_rows($rs_members) == 0) {
                    if(p4c_query("INSERT INTO `members` SET 
                        `remote_member_id`  = '".p4c_escape_string($remote_member_id)."',
                        `p4c_shop_id`       = '".p4c_escape_string($member_ary->p4c_shop_id)."',
                        `username`          = '".p4c_escape_string($member_ary->username)."',
                        `amount_coins`      = '".abs($member_ary->amount_coins)."',
                        `birthday`          = '".p4c_escape_string(date("Y-m-d", strtotime($member_ary->birthday)))."',
                        `email`             = '".p4c_escape_string($member_ary->email)."',
                        `lastonline`        = '".p4c_escape_string(date("Y-m-d H:i:s",strtotime($member_ary->lastonline)))."',
                        `avatar_url`        = '".p4c_escape_string($member_ary->avatar_url)."'                           
                    ",__FILE__,__LINE__));
                }
            }
        }
        
        if (isset($chats_obj->chat)) {
            $chat_obj = $chats_obj->chat; 

            $count_chats = count((array)$chat_obj);

            // Wenn alle Chats syncronisiert wurden
            if ($site_ary->chats_synct >= $count_chats) {
                p4c_query("UPDATE `messenger_sync` SET
                    `sync_time`='".date("Y-m-d H:i:s")."',
                    `chats_synct`='".abs($count_chats)."'
                WHERE `merchant_id`='".abs($merchant_id)."' AND `actor_id`='".abs($actor_id)."' AND `domain`='".p4c_escape_string($domain)."' LIMIT 1;",__FILE__,__LINE__);
            }
            // Ansonsten mache weiter und setze Zeitstempel auf 0 zurück
            else {
                p4c_query("UPDATE `messenger_sync` SET
                    `sync_time`='0000-00-00 00:00:00',
                    `chats_total`='".abs($count_chats)."'
                WHERE `merchant_id`='".abs($merchant_id)."' AND `actor_id`='".abs($actor_id)."' AND `domain`='".p4c_escape_string($domain)."' LIMIT 1;",__FILE__,__LINE__);

                include_once(SOURCEDIR.'/includes/klassen/member.inc.php');
                
                foreach ($chat_obj as $erocms_chat_id => $chat_ary) {
                    $rs_actor_member_info = p4c_query("SELECT * FROM `actor_member_info` WHERE `erocms_chat_id`='". p4c_escape_string($erocms_chat_id)."' AND `p4c_shop_id`='".abs($chat_ary->p4c_shop_id)."' LIMIT 1;",__FILE__,__LINE__);

                    if (p4c_num_rows($rs_actor_member_info) == 0) {

                        $chat_id = $actor_id."_".$chat_ary->p4c_shop_id."_".$chat_ary->member_id;

                        $remote_member_id = $chat_ary->p4c_shop_id."_".$chat_ary->member_id;
                        $member = new Member($remote_member_id);
                        
                        if(p4c_query("INSERT INTO `actor_member_info` SET 
                            `chat_id`           = '".p4c_escape_string($chat_id)."',
                            `erocms_amateur_id` = '".abs($chat_ary->amateur_id)."',
                            `erocms_member_id`  = '".abs($chat_ary->member_id)."',
                            `erocms_chat_id`    = '".p4c_escape_string($erocms_chat_id)."',
                            `p4c_shop_id`       = '".abs($chat_ary->p4c_shop_id)."',
                            `remote_member_id`  = '".abs($chat_ary->p4c_shop_id)."_".abs($chat_ary->member_id)."',
                            `domain`            = '".p4c_escape_string($chat_ary->domain)."',
                            `merchant_id`       = '".abs($merchant_id)."',
                            `actor_id`          = '".abs($actor_id)."',
                            `member_id`         = '".abs($member->id())."',
                            `pn_amount`         = '".abs($chat_ary->pn_preis)."',
                            `pn_free_when_webcam` = '".abs($chat_ary->pn_kostenlos_bei_webcam)."',
                            `cam2cam_amount`    = '".abs($chat_ary->cam2cam_preis)."',
                            `cam_amount`        = '".abs($chat_ary->cam_preis)."',
                            `is_cam_free`       = '".abs($chat_ary->cam_kostenlos)."',
                            `member_is_typing`  = '".abs($chat_ary->member_tippt)."',
                            `actor_is_typing`   = '".abs($chat_ary->ama_tippt)."',
                            `user_notes`        = '".p4c_escape_string($chat_ary->userinfo)."'
                        ",__FILE__,__LINE__));
                    }
                }
            }
            
        }
    } else {
        $error = __LINE__.': '.'https://'.$domain.'/erocloud_api/send_erocms_chats.php?'.$query;
        curl_close($ch);
    }
}

?>