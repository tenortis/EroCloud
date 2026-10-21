<?php

define('SAFE_INC', 1);

include_once("../../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

if (is_logged_in('mcp') !== true) {exit;}

if (isset($_SESSION['merchant_id'])) {
    $merchant = new Merchant($mysql,$_SESSION['merchant_id']);
}
   

$set_my_chatgroup = filter_input(INPUT_POST, 'set_my_chatgroup', FILTER_SANITIZE_NUMBER_INT);
if ($set_my_chatgroup > 0) {
    $_SESSION['my_chatgroup'] = $set_my_chatgroup;
    echo $_SESSION['my_chatgroup'];
}


// Set Messenger-Sound
if (isset($_POST['set_messenger_sound'])) {
    $status = abs((int)$_POST['set_messenger_sound']);

    if ($status === 1) {
        $status = 0;
    } else {
        $status = 1;
    }

    p4c_query("UPDATE `groups` SET
        `messneger_sound`='".$status."'
    WHERE
        `merchant_id`='".abs((int)$_SESSION['merchant_id'])."' AND
        `id`='".abs((int)$_SESSION['my_chatgroup'])."'
    LIMIT 1;",__FILE__,__LINE__);
    
    echo $status;
}

// Set Messenger-Sound
if (isset($_POST['get_messenger_sound']) AND isset($_SESSION['merchant_id'])) {
    if (isset($_SESSION['my_chatgroup'])) {
        $rs_sound = p4c_query("SELECT `messneger_sound` FROM `groups` WHERE
            `merchant_id`='".abs((int)$_SESSION['merchant_id'])."' AND
            `id`='".abs((int)$_SESSION['my_chatgroup'])."'
        LIMIT 1;",__FILE__,__LINE__);
    
        echo p4c_result($rs_sound,0);
    } else {
        echo 1; 
    }
}



// Empfänger mitteilen, dass ich tippe
if (isset($_POST['actor_is_typing']) AND isset($_SESSION['merchant_id'])) {
    
    $get_chat_id = filter_input(INPUT_POST, 'chat_id', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $get_chat_id = preg_replace( '/[^0-9a-z_]/i', '', $get_chat_id);

    $merchant_id= abs((int)$_SESSION['merchant_id']);
    
    p4c_query("UPDATE `actor_member_info` SET
        `actor_is_typing`='".abs((int)$_POST['actor_is_typing'])."'
    WHERE
        `chat_id`    = '". p4c_escape_string($get_chat_id)."' AND
        `merchant_id`= '".abs((int)$merchant_id)."' LIMIT 1;", __FILE__, __LINE__);
    
    $rs_actor_member_info = p4c_query("SELECT * FROM `actor_member_info` WHERE `chat_id`='". p4c_escape_string($get_chat_id)."' AND `merchant_id`='".abs((int)$merchant_id)."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_actor_member_info,__FILE__,__LINE__) === 1) {
       
        $member_info_obj = p4c_fetch_object($rs_actor_member_info);
        $merchant = new Merchant($mysql,$merchant_id);
        
        $domain     = $member_info_obj->domain;
        $shop_id    = $member_info_obj->p4c_shop_id;
        $actor_id   = $member_info_obj->actor_id;
    
        #$rs_messenger_sync = p4c_query("SELECT AES_DECRYPT(`apikey`, '".AES_KEY."') FROM `messenger_sync` WHERE `p4c_shop_id`='".abs($shop_id)."' LIMIT 1;",__FILE__,__LINE__);
        $rs_messenger_sync = p4c_query("SELECT AES_DECRYPT(`apikey`, '".AES_KEY."') FROM `messenger_sync` WHERE `actor_id`='".abs((int)$actor_id)."' AND `p4c_shop_id`='".abs((int)$shop_id)."' LIMIT 1;",__FILE__,__LINE__);
        
        $param = array(
            "api_key"           => p4c_result($rs_messenger_sync, 0),
            "erocms_amateur_id" => $member_info_obj->erocms_amateur_id,
            "erocms_member_id"  => $member_info_obj->erocms_member_id,
            "actor_is_typing"   => abs((int)$_POST['actor_is_typing'])
        );

        $param = array_filter($param, "strlen"); // leere Einträge Entfernen
        ksort($param); // Alphabetische Sortierung
        $query = http_build_query($param, '&amp;');
        
        $date = p4c_curl('https://'.$domain.'/erocloud_api/update_actor_member_infos.php?'.$query);
    }
}

// Einstellungen zum User speichern
if (isset($_POST['save_user_settings'])) {
    
    $get_chat_id = filter_input(INPUT_POST, 'save_user_settings', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $get_chat_id = preg_replace( '/[^0-9a-z_]/i', '', $get_chat_id);
    
    $get_pn_amount  = filter_input(INPUT_POST, 'pn_amount', FILTER_SANITIZE_NUMBER_INT);
    $get_cam_amount = filter_input(INPUT_POST, 'cam_amount', FILTER_SANITIZE_NUMBER_INT);
    $get_cam2cam_amount = filter_input(INPUT_POST, 'cam2cam_amount', FILTER_SANITIZE_NUMBER_INT);
    
    $merchant_id= abs((int)$_SESSION['merchant_id']);
    
    $rs_actor_member_info = p4c_query("SELECT * FROM `actor_member_info` WHERE `chat_id`='". p4c_escape_string($get_chat_id)."' AND `merchant_id`='".abs((int)$merchant_id)."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_actor_member_info,__FILE__,__LINE__) === 1) {
       
        $member_info_obj = p4c_fetch_object($rs_actor_member_info);
        $merchant = new Merchant($mysql,$merchant_id);
        
        $domain     = $member_info_obj->domain;
        $shop_id    = $member_info_obj->p4c_shop_id;
        $actor_id   = $member_info_obj->actor_id;
        
        $rs_messenger_sync = p4c_query("SELECT AES_DECRYPT(`apikey`, '".AES_KEY."') FROM `messenger_sync` WHERE `actor_id`='".abs((int)$actor_id)."' AND `p4c_shop_id`='".abs((int)$shop_id)."' LIMIT 1;",__FILE__,__LINE__);
        
        $param = array(
            "api_key"           => p4c_result($rs_messenger_sync, 0),
            "erocms_amateur_id" => $member_info_obj->erocms_amateur_id,
            "erocms_member_id"  => $member_info_obj->erocms_member_id,
            "pn_amount"         => abs((int)$get_pn_amount),
            "cam_amount"        => abs((int)$get_cam_amount),
            "cam2cam_amount"    => abs((int)$get_cam2cam_amount)
        );

        $param = array_filter($param, "strlen"); // leere Einträge Entfernen
        ksort($param); // Alphabetische Sortierung
        $query = http_build_query($param, '&amp;');
        
        $data = p4c_curl('https://'.$domain.'/erocloud_api/update_actor_member_infos.php?'.$query);
        
        if ($data !== 'ok') {
            $class_errorlog->log('URL: https://'.$domain.'/erocloud_api/update_actor_member_infos.php?'.$query."\n".$data."\n".print_r($_POST,true),__FILE__,__LINE__);   
        } else {
            p4c_query("UPDATE `actor_member_info` SET
                `pn_amount`='".abs((int)$get_pn_amount)."',
                `cam_amount`='".abs((int)$get_cam_amount)."',
                `cam2cam_amount`='".abs((int)$get_cam2cam_amount)."'
            WHERE
                `chat_id`    = '". p4c_escape_string($get_chat_id)."' AND
                `merchant_id`= '".abs((int)$merchant_id)."' LIMIT 1;", __FILE__, __LINE__);
            
            echo $data;
        }
    }
}

// Set Onlinestatus
if (isset($_POST['set_online']) AND $_POST['set_online'] === 'true') {
   
    if (!isset($_SESSION['my_chatgroup']) OR $_SESSION['my_chatgroup'] === '') {
        exit;
    }

    $rs_actors = p4c_query("SELECT * FROM `group_actors` WHERE
        `group_id`='".abs((int)$_SESSION['my_chatgroup'])."' AND
        `merchant_id`='".abs((int)$_SESSION['merchant_id'])."';",__FILE__,__LINE__);
    
    if (p4c_num_rows($rs_actors,__FILE__,__LINE__) === 0) {
        exit;
    }

    while ($actor_obj = p4c_fetch_object($rs_actors)) {
        $onlinetime = time()+30;

        p4c_query("UPDATE `actors` SET `lastonline` = '".abs((int)$onlinetime)."' WHERE 
            `status`='active' AND
            `id` = '".abs((int)$actor_obj->actor_id)."' LIMIT 1;",__FILE__,__LINE__);
    }
}

if (isset($_POST['pause']) AND isset($_POST['actor_id']) AND $_POST['actor_id'] > 0) {
    $actor_id = $_POST['actor_id'];
    
    if ($_POST['pause'] == '0') {
        p4c_query("UPDATE `actors` SET `messenger_takes_a_break`='1' WHERE `id`='".abs((int)$actor_id)."' AND `merchant_id`='".abs((int)$_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);
        echo '1';
    } else {
        p4c_query("UPDATE `actors` SET `messenger_takes_a_break`='0' WHERE `id`='".abs((int)$actor_id)."' AND `merchant_id`='".abs((int)$_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);
        echo '0';
    }
}

if (isset($_POST['online_status']) AND isset($_POST['actor_id']) AND $_POST['actor_id'] > 0) {
    $actor_id = $_POST['actor_id'];
    
    if ($_POST['online_status'] == '0') {
        p4c_query("UPDATE `actors` SET `messenger_online_status`='1' WHERE `id`='".abs((int)$actor_id)."' AND `merchant_id`='".abs((int)$_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);
        echo '1';
    } else {
        p4c_query("UPDATE `actors` SET `messenger_online_status`='0' WHERE `id`='".abs((int)$actor_id)."' AND `merchant_id`='".abs((int)$_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);
        echo '0';
    }
}


// Nachricht als un-/beantwortet markieren
if (isset($_POST['marked_as_unanswered']) AND isset($_SESSION['merchant_id'])) {
    $get_marked_as_unanswered = abs((int)$_POST['marked_as_unanswered']);
    
    $get_remote_member_id = filter_input(INPUT_POST, 'remote_member_id', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $get_remote_member_id = preg_replace( '/[^0-9a-z_]/i', '', $get_remote_member_id);

    p4c_query("UPDATE `actor_member_info` SET 
        `marked_as_unanswered`='".abs((int)$get_marked_as_unanswered)."'
    WHERE
        `chat_id` = '".p4c_escape_string($get_remote_member_id)."' AND
        `actor_id`= '".abs((int)$_SESSION['actor_id'])."' AND
        `merchant_id`='". abs((int)$_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);
   
}

// Notizen zum User speichern
if (isset($_POST['save_user_notes'])) {
    $get_chat_id = filter_input(INPUT_POST, 'chat_id', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $get_chat_id = preg_replace( '/[^0-9a-z_]/i', '', $get_chat_id);
    
    $text = trim($_POST['save_user_notes']);
    
    p4c_query("UPDATE `actor_member_info` SET 
        `user_notes`='".p4c_escape_string($text)."'
    WHERE
        `chat_id` = '".p4c_escape_string($get_chat_id)."' AND
        `merchant_id`='". abs((int)$_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);
    
    echo 'ok';   

}


// Nachricht als gelesen markieren
if (isset($_POST['message_read'])) {

    $get_chat_id = filter_input(INPUT_POST, 'chat_id', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $get_chat_id = preg_replace( '/[^0-9a-z_]/i', '', $get_chat_id);
    
    $rs_actor_member_info = p4c_query("SELECT * FROM `actor_member_info` WHERE `merchant_id`='".abs((int)$_SESSION['merchant_id'])."' AND `chat_id`='". p4c_escape_string($get_chat_id)."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_actor_member_info,__FILE__,__LINE__) === 0) {
        exit;
    }

    $member_info_obj = p4c_fetch_object($rs_actor_member_info);
    
    $shop_id    = $member_info_obj->p4c_shop_id;
    $domain     = $member_info_obj->domain;
    $actor_id   = $member_info_obj->actor_id;
    
    #$rs_messenger_sync = p4c_query("SELECT AES_DECRYPT(`apikey`, '".AES_KEY."') FROM `messenger_sync` WHERE `p4c_shop_id`='".abs($shop_id)."' LIMIT 1;",__FILE__,__LINE__);
    $rs_messenger_sync = p4c_query("SELECT AES_DECRYPT(`apikey`, '".AES_KEY."') FROM `messenger_sync` WHERE `actor_id`='".abs((int)$actor_id)."' AND `p4c_shop_id`='".abs((int)$shop_id)."' LIMIT 1;",__FILE__,__LINE__);
    
    $param = array(
        "api_key"       => p4c_result($rs_messenger_sync, 0),
        "message_read"  => 1,
        "chat_id"       => $member_info_obj->erocms_chat_id    
    );

    $param = array_filter($param, "strlen"); // leere Einträge Entfernen
    ksort($param); // Alphabetische Sortierung
    $query = http_build_query($param, '&amp;');

    $data = p4c_curl('https://'.$domain.'/erocloud_api/update_message.php?'.$query);
    
    if ($data === 'ok') {
        #echo $data."\n";
        p4c_query("UPDATE `chat_messages` SET `gelesen`='1' WHERE `von`='member' AND `merchant_id`='".abs((int)$_SESSION['merchant_id'])."' AND `chat_id`='". p4c_escape_string($get_chat_id)."';",__FILE__,__LINE__);
        p4c_query("UPDATE `chat_messages_history` SET `gelesen`='1' WHERE `von`='member' AND `merchant_id`='".abs((int)$_SESSION['merchant_id'])."' AND `chat_id`='". p4c_escape_string($get_chat_id)."';",__FILE__,__LINE__);
    }
    
}

// GET all_usercams
if (isset($_POST['get_all_usercams']) AND isset($_SESSION['merchant_id']) AND isset($_SESSION['my_chatgroup'])) {
    
    $rs_member_cams = p4c_query("SELECT * FROM `member_cams` LEFT JOIN `members` ON
        `member_cams`.`member_id`=`members`.`id` WHERE
        `member_cams`.`group_id`='".abs((int)$_SESSION['my_chatgroup'])."' AND
        `member_cams`.`merchant_id`='".abs((int)$_SESSION['merchant_id'])."' AND
        `datetime` >= '".date("Y-m-d H-i-s", strtotime("-80 seconds"))."';",__FILE__,__LINE__);
        
    $send_jason = array();
    $count_streams = 0;
    
    if (p4c_num_rows($rs_member_cams) > 0) {
        while($cams_obj = p4c_fetch_object($rs_member_cams)) {
            
            $file = $cams_obj->streamserver_url.$cams_obj->stream_id.'.m3u8';
            $file_headers = @get_headers($file);
            if($file_headers AND $file_headers[0] !== 'HTTP/1.1 404 Not Found') {
                
                $count_streams++;
                
                $send_jason['streams'][] = array(
                    'ice_candidates'  => $cams_obj->streamserver_url,
                    'stream_id'         => $cams_obj->stream_id,
                    'member_id'         => $cams_obj->member_id,
                    'username'          => $cams_obj->username
                );
            }
        }
    }

    $send_jason['count'] = $count_streams;
    
    $send_jason['stream_checksum'] = md5(json_encode($send_jason));
    
    echo json_encode($send_jason);
}


if (isset($_POST['check_url_connect'])) {
    header('Content-Type: text/html; charset=utf-8');
    p4c_query("SET NAMES 'utf8'");
    
    $domain = trim($_POST['check_url_connect']);
    $group_id = abs((int)$_POST['group_id']);
    
    $error = 'Es konnte keine Verbindung hergestellt werden.';

    if (empty($domain)) {
        echo '<div class="ui-state-error" style="padding:5px 10px;">Du musst die Webseite angeben auf der du als Gastamateur registriert bist.</div>';
        exit;
    }
    
    $search = array('http://', 'https://', 'www.');
    $domain = str_replace($search, '', $domain);

    $api_key = $merchant->api_key('aes_decrypt');
    
    $param = array(
        "username"=>$_POST['username'],
        "password"=>$_POST['password'],
        "partner_id" => $merchant->partner_id(),
        "api_key" => $api_key
    );

    $param = array_filter($param, "strlen"); // leere Einträge Entfernen
    ksort($param); // Alphabetische Sortierung
    $query = http_build_query($param, '&amp;');
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://'.$domain.'/erocloud_api/check_ama_api.php?'.$query);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1); 
    curl_setopt($ch, CURLOPT_REFERER, URL);
    curl_setopt($ch, CURLOPT_USERAGENT, PROJECTNAME." - ".COMPANYNAME." - ".URL);
    $data = curl_exec($ch);
    if($data != false) {

        curl_close($ch);
        
        $obj = json_decode($data);
        
        if (isset($obj->p4c_shop_id) AND isset($obj->profil)) {
            
            $profil_obj = $obj->profil;
           
            // Prüfen ob Amateur schon syncronisiert wurde
            $rs_check_sync = p4c_query("SELECT * FROM `messenger_sync` WHERE
                `merchant_id`='".abs((int)$_SESSION['merchant_id'])."' AND
                `p4c_shop_id`='".abs((int)$obj->p4c_shop_id)."' AND
                `remote_actor_id`='".abs((int)$obj->sender_id)."'
            LIMIT 1;",__FILE__,__LINE__);
            
            if (p4c_num_rows($rs_check_sync,__FILE__,__LINE__) === 0) {
                
                // prüfe ob Profil (Username) schon beim Merchant existiert
                $rs_check_actor_profiles = p4c_query("SELECT * FROM `actors` WHERE
                    `merchant_id`='".abs((int)$_SESSION['merchant_id'])."' AND
                    `username`='".p4c_escape_string($profil_obj->username)."'
                LIMIT 1;",__FILE__,__LINE__);
            
                if ($profil_obj->familienstand == 0) {
                    $familienstand = 'ledig';
                } else {
                    $familienstand = $profil_obj->familienstand;
                }
                
                // Wenn Profil nicht existiert
                if (p4c_num_rows($rs_check_actor_profiles,__FILE__,__LINE__) === 0) {
                    p4c_query("INSERT INTO `actors` SET
                        `merchant_id`           ='".abs((int)$_SESSION['merchant_id'])."',
                        `username`              ='".p4c_escape_string($profil_obj->username)."',
                        `lastonline`            ='".abs((int)$profil_obj->lastonline)."',
                        `cam_user_auto_on`      ='".abs((int)$profil_obj->cam_user_auto_on)."',
                        `messenger_sound`       ='".abs((int)$profil_obj->messenger_sound)."',
                        `messenger_takes_a_break`='".abs((int)$profil_obj->messenger_pause_status)."',
                        `messenger_online_status`='".abs((int)$profil_obj->messenger_online_status)."',
                        `cam_amount`            ='".abs((int)$profil_obj->cam_preis)."',
                        `cam_new_user_sound`    ='".p4c_escape_string($profil_obj->cam_new_user_sound)."',
                        `pn_amount`             ='".abs((int)$profil_obj->pn_preis)."',
                        `pn_free_if_webcam`     ='".abs((int)$profil_obj->pn_kostenlos_bei_webcam)."',
                        `cam2cam_amount`        ='".abs((int)$profil_obj->cam2cam_preis)."',
                        `check_gender`          ='".abs((int)$profil_obj->check_geschlecht)."',
                        `gender`                ='".p4c_escape_string($profil_obj->geschlecht)."',
                        `check_age`             ='".abs((int)$profil_obj->check_alter)."',
                        `age`                   ='".abs((int)$profil_obj->alter)."',
                        `check_star_sign`       ='".abs((int)$profil_obj->check_sternzeichen)."',
                        `star_sign`             ='".p4c_escape_string($profil_obj->sternzeichen)."',
                        `check_body_height`     ='".abs((int)$profil_obj->check_groesse)."',
                        `body_height`           ='".abs((int)$profil_obj->groesse)."',
                        `check_eye_color`       ='".abs((int)$profil_obj->check_augenfarbe)."',
                        `eye_color`             ='".p4c_escape_string($profil_obj->augenfarbe)."',
                        `check_hair_color`      ='".abs((int)$profil_obj->check_haarfarbe)."',
                        `hair_color`            ='".p4c_escape_string($profil_obj->haarfarbe)."',
                        `check_body_weight`     ='".abs((int)$profil_obj->check_gewicht)."',
                        `body_weight`           ='".abs((int)$profil_obj->gewicht)."',
                        `check_cup_size`        ='".abs((int)$profil_obj->check_koerbchen_groesse)."',
                        `cup_size`              ='".p4c_escape_string($profil_obj->koerbchen_groesse)."',
                        `check_shaven`          ='".abs((int)$profil_obj->check_intimrasur)."',
                        `shaven`                ='".abs((int)$profil_obj->intimrasur)."',
                        `check_look`            ='".abs((int)$profil_obj->check_aussehen)."',
                        `look`                  ='".p4c_escape_string($profil_obj->aussehen)."',
                        `check_marital_status`  ='".abs((int)$profil_obj->check_familienstand)."',
                        `marital_status`        ='".p4c_escape_string($familienstand)."',
                        `check_sexual_orientation`='".abs((int)$profil_obj->check_sexuelle_orientierung)."',
                        `sexual_orientation`    ='".p4c_escape_string($profil_obj->sexuelle_orientierung)."',
                        `check_looking_for`     ='".abs((int)$profil_obj->check_ich_suche)."',
                        `looking_for`           ='".p4c_escape_string($profil_obj->ich_suche)."',
                        `check_interests`       ='".abs((int)$profil_obj->check_interessen)."',
                        `interests`             ='".p4c_escape_string($profil_obj->interessen)."',
                        `check_sexual_preferences`='".abs((int)$profil_obj->check_sexuelle_vorlieben)."',
                        `sexual_preferences`    ='".p4c_escape_string($profil_obj->sexuelle_vorlieben)."',
                        `check_about_me`        ='".abs((int)$profil_obj->check_ueber_mich)."',
                        `about_me`              ='".p4c_escape_string($profil_obj->ueber_mich)."'
                    ;",__FILE__,__LINE__);
                    
                    // Profil anlegen
                    $new_actor_id = p4c_insert_id();
                    
                    $rs_check_actor_profiles = p4c_query("SELECT * FROM `actors` WHERE
                        `id` = '".abs((int)$new_actor_id)."' AND
                        `merchant_id`='".abs((int)$_SESSION['merchant_id'])."' AND
                        `username`='".p4c_escape_string($profil_obj->username)."'
                    LIMIT 1;",__FILE__,__LINE__);
                }
                
                // Wenn Profil existiert
                $actor_obj = p4c_fetch_object($rs_check_actor_profiles);
                
                $actor_id = $actor_obj->id;

                $rs_check_group = p4c_query("SELECT * FROM `group_actors` WHERE `actor_id`='".abs((int)$actor_id)."' LIMIT 1;",__FILE__,__LINE__);
                if (p4c_num_rows($rs_check_group,__FILE__,__LINE__) == 0) {
                    p4c_query("INSERT INTO `group_actors` SET
                        `merchant_id`   = '".abs((int)$_SESSION['merchant_id'])."',
                        `group_id`      = '".abs((int)$group_id)."',
                        `actor_id`      = '".abs((int)$actor_id)."';",__FILE__,__LINE__);
                    
                    $_SESSION['my_chatgroup'] = $group_id;
                }
                
                // Profil mit Cloud verbinden
                p4c_query("INSERT INTO `messenger_sync` SET
                    `merchant_id`   = '".abs((int)$_SESSION['merchant_id'])."',
                    `actor_id`      = '".abs((int)$actor_id)."',
                    `remote_actor_id`='".abs((int)$obj->sender_id)."',
                    `apikey`        = AES_ENCRYPT('".p4c_escape_string($api_key)."','".AES_KEY."'),
                    `domain`        = '". p4c_escape_string($domain)."',
                    `p4c_shop_id`   = '".abs((int)$obj->p4c_shop_id)."';
                ",__FILE__,__LINE__);
                
                // EroCloud-Actor-ID übertragen
                $param = array(
                    "username"=>$_POST['username'],
                    "password"=>$_POST['password'],
                    "erocloud_actor_id"=>$actor_id,
                    "partner_id" => $merchant->partner_id(),
                    "api_key" => $api_key
                );

                $param = array_filter($param, "strlen"); // leere Einträge Entfernen
                ksort($param); // Alphabetische Sortierung
                $query = http_build_query($param, '&amp;');
                
                $ch2 = curl_init();
                curl_setopt($ch2, CURLOPT_URL, 'https://'.$domain.'/erocloud_api/check_ama_api.php?'.$query);
                curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_FRESH_CONNECT, 1); 
                curl_setopt($ch2, CURLOPT_REFERER, URL);
                curl_setopt($ch2, CURLOPT_USERAGENT, PROJECTNAME." - ".COMPANYNAME." - ".URL);
                $data2 = curl_exec($ch2);
                curl_close($ch2);
            }

            $error = '';
            
            include(MCP_DIR.'/messenger/includes/crawl_erocms_chats.php');
            
            if (!isset($error) OR trim($error) == '') {
                echo '<script>setTimeout(function(){location.reload();}, 5000);</script><div class="ui-state-highlight" style="padding:5px 10px;">Verbindung hergestellt.<br />Die Daten werden im Hintergrund syncronisiert. Dies kann einige Minuten dauern.</div>';
                exit;
            }
        } else {
            if (isset($obj->error)) {
                if ($obj->error === 'password false') {
                    $error = 'Das Passwort ist falsch.';
                } else if ($obj->error === 'username false') {
                    $error = 'Der Username ist nicht korrekt.';
                } else {
                    $error = __LINE__.': '.$data;    
                }
                
            } else {
                $error = __LINE__.': '.$data;
            }
        }
    } else {
        $error = __LINE__.': '.curl_error($ch);
        curl_close($ch);
    }
    
    echo '<div class="ui-state-error" style="padding:5px 10px;">'.$error.'</div>';
}

if (isset($_POST['search']) AND isset($_SESSION['my_chatgroup'])) {
    $get_search = trim(strip_tags(filter_input(INPUT_POST, 'search', FILTER_SANITIZE_STRING,FILTER_FLAG_STRIP_LOW)));
    if ($get_search != '') {
        
        // LIMIT 1000, weil browser sonst zu lange lädt
        $rs_members = p4c_query("SELECT *, `members`.`id` AS `member_id` FROM `group_actors`, `members` LEFT JOIN `messenger_sync` ON `messenger_sync`.`p4c_shop_id`=`members`.`p4c_shop_id` WHERE
            `members`.`username` LIKE '%". p4c_escape_string($get_search)."%' AND
            `messenger_sync`.`actor_id`=`group_actors`.`actor_id` AND
            `group_actors`.`group_id`='".abs((int)$_SESSION['my_chatgroup'])."' AND
            `messenger_sync`.`sync_time`!='0000-00-00 00:00:00'
        ORDER BY `lastonline` DESC LIMIT 1000;",__FILE__,__LINE__);
               
        $send_jason = array();
        $count_search_results = 0;
        if (p4c_num_rows($rs_members,__FILE__,__LINE__) > 0) {
            while($member_obj = p4c_fetch_object($rs_members)) {
                
                $count_search_results++;
                if ($member_obj->amount_coins > 0) {
                    $amount_coins = $member_obj->amount_coins;
                } else {
                    $amount_coins = 0;
                }

                $member_avatar_url = trim($member_obj->avatar_url);
                if(empty($member_avatar_url)) {
                    $member_avatar_url = MCP_URL.'/images/movie_poster_nopic.jpg';
                } 
                
                if ($member_obj->lastonline >= date("Y-m-d H:i:s",strtotime("-10 Minutes"))) {
                    $online = 1;
                } else {
                    $online = 0;
                }    

                $send_jason['search_users'][] = [
                    'order_id'      => $amount_coins,
                    'chat_id'       => $member_obj->actor_id.'_'.$member_obj->remote_member_id,
                    'username'      => utf8_encode($member_obj->username),
                    'amount_coins'  => $amount_coins,
                    'avatar_url'    => $member_avatar_url,
                    'domain'        => $member_obj->domain,
                    'online_status' => $online
                ];
            }
        }
        $send_jason['count_user_search'] = $count_search_results;

        echo json_encode($send_jason);
    }
}

// Aktuellen Umsatz ausgeben
if (isset($_POST['get_commision'])) {
    
    $sql_movies_group = '';
    $sql_photo_alben_group = '';
    $sql_messenger_group = '';  
    $sql_webcam_group = '';

    
    if ($_SESSION['logged_in_as'] == 'group' AND isset($_SESSION['my_chatgroup'])) {
        $sql_movies_group = "AND `movies`.`actor_id` IN (SELECT `actor_id` FROM `group_actors` WHERE `group_id` = '".abs((int)$_SESSION['my_chatgroup'])."' AND `merchant_id` = '".abs((int)$_SESSION['merchant_id'])."')";
        $sql_photo_alben_group = "AND `photo_albums`.`actor_id` IN (SELECT `actor_id` FROM `group_actors` WHERE `group_id` = '".abs((int)$_SESSION['my_chatgroup'])."' AND `merchant_id` = '".abs((int)$_SESSION['merchant_id'])."')";
        $sql_messenger_group = "AND `chat_messages_history`.`an_id` IN (SELECT `actor_id` FROM `group_actors` WHERE `group_id` = '".abs((int)$_SESSION['my_chatgroup'])."' AND `merchant_id` = '".abs((int)$_SESSION['merchant_id'])."')";
        $sql_webcam_group = "AND `revenue_webcam`.`actor_id` IN (SELECT `actor_id` FROM `group_actors` WHERE `group_id` = '".abs((int)$_SESSION['my_chatgroup'])."' AND `merchant_id` = '".abs((int)$_SESSION['merchant_id'])."')";
    }
    
    // Fotoalben
    $rs_count_sold_photo_albums_day_all = p4c_query("SELECT COUNT(*), SUM(`actor_commision`) FROM `photo_albums_access` LEFT JOIN `photo_albums` ON `photo_albums_access`.`album_id`=`photo_albums`.`album_id` 
        WHERE  `photo_albums`.`merchant_id`='".abs((int)$_SESSION['merchant_id'])."' AND
            `buy_timestamp` LIKE '".date("Y-m")."-%' 
            ".$sql_photo_alben_group."
        ;",__FILE__,__LINE__);
    
    // Movies
    $rs_count_sold_movies_day_all = p4c_query("SELECT SUM(`actor_commision`) FROM `movies_access` LEFT JOIN `movies` ON `movies_access`.`movie_id`=`movies`.`file_id` 
        WHERE  `movies`.`merchant_id`='".abs((int)$_SESSION['merchant_id'])."' AND
            `buy_timestamp` LIKE '".date("Y-m")."-%' 
            ".$sql_movies_group."
        ;",__FILE__,__LINE__);
  
    // Messenger
    $rs_count_receive_messages = p4c_query("SELECT SUM(`actor_commision`) FROM `chat_messages_history` WHERE
        `merchant_id`='".abs((int)$_SESSION['merchant_id'])."' AND
        `von`='member' AND
        `datetime` LIKE '".date("Y-m")."-%'
        ".$sql_messenger_group."
    ;",__FILE__,__LINE__);
    
    // Webcam
    $rs_count_webcam = p4c_query("SELECT SUM(`commision`) AS `commision` FROM `revenue_webcam` WHERE
        `merchant_id`='".abs((int)$_SESSION['merchant_id'])."' AND
        `date` LIKE '".date("Y-m")."-%'
        ".$sql_webcam_group."
    ;",__FILE__,__LINE__);    
    
    $total_amaount = p4c_result($rs_count_receive_messages,0) + p4c_result($rs_count_sold_photo_albums_day_all,0) + p4c_result($rs_count_sold_movies_day_all,0) + p4c_result($rs_count_webcam,0);
    $total_eur = number_format(($total_amaount/100),'2',',','.');
    
    echo $total_eur;    
}


p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());


?>