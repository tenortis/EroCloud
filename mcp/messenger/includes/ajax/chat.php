<?php

define('SAFE_INC', 1);

$session_write_close = true;

include_once("../../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

if (!isset($_SESSION['merchant_id'])) {
    exit;
}
// User online 
if(isset($_POST['remote_member_id'])) {
    
    $chat_id = preg_replace ( '/[^a-z0-9_]/i', '', $_POST['remote_member_id']);
    
    $send_jason = array();

    if (isset($_POST['mess_timestamp'])) {
        
        $datetime = date("Y-m-d H:i:s", strtotime($_POST['mess_timestamp']));
        
        $rs_chats = p4c_query("SELECT * FROM `chat_messages_history` WHERE
            `chat_id` = '".p4c_escape_string($chat_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `datetime`<'".$datetime."' ORDER BY `datetime` DESC LIMIT 40;",__FILE__,__LINE__);
        
    } else {        
        $rs_chats = p4c_query("SELECT * FROM `chat_messages` WHERE
            `chat_id` = '".p4c_escape_string($chat_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' ORDER BY `datetime` ASC LIMIT 40;",__FILE__,__LINE__);
    }
    
    // Wenn noch keine Nachricht geschrieben wurde
    if (p4c_num_rows($rs_chats) == 0 AND !isset($_POST['mess_timestamp'])) {
        $send_jason['message'][] = array(
            'mess_type' => 'system',
            'mess_id'   => 0,
            'message'   => 'Ihr hattet bisher noch keinen Kontakt.<br />Schreib eine Nachricht um auf dich aufmerksam zu machen.',
            'mess_from' => 'actor',
            'mess_time' => date("H:i"),
            'answered'  => 1,
            'status'    => 1
        );
        
    } else {
        
        // Lösche ältere nachrichten aus Chat (im Verlauf `chat_messages_history` bleiben Sie erhalten)
        p4c_query("DELETE FROM `chat_messages` WHERE
            `chat_id` = '".p4c_escape_string($chat_id)."' AND 
            `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
            `id` < (SELECT min(id) FROM (SELECT * FROM `chat_messages` WHERE
                `chat_id` = '".p4c_escape_string($chat_id)."' AND 
                `merchant_id`='".abs($_SESSION['merchant_id'])."'
                ORDER BY `id` DESC LIMIT 40) AS `last_ids`
            ) ORDER BY `id` DESC;",__FILE__,__LINE__);
        
        /* Lösung für Deadlock aus vorherigem DELETE
        //Suche die 40. `id`
        [40.ID] = SELECT min(id) FROM (SELECT * FROM `chat_messages` WHERE `chat_id` = '34_50050_1012' AND `merchant_id`='34' ORDER BY `chat_messages_history`.`id` DESC LIMIT 40) AS `last_ids`
        
        // Lösche alles was älter ist als die 40.ID 
        DELETE/SELECT * FROM `chat_messages` WHERE `chat_id` = '34_50050_1012' AND `merchant_id`='43' AND `id` < [40.ID]
         */
        /*
        $rs_last_id = p4c_query("SELECT min(id) FROM (SELECT * FROM `chat_messages` WHERE `chat_id` = '34_50050_1012' AND `merchant_id`='34' ORDER BY `chat_messages`.`id` DESC LIMIT 40) AS `last_ids`",__FILE__,__LINE__);
        if (p4c_num_rows($rs_last_id) > 0) {
            $last_id = p4c_result($rs_last_id,0);
            if ($last_id != '') {
                p4c_query("DELETE FROM `chat_messages` WHERE `chat_id` = '34_50050_1012' AND `merchant_id`='34' AND `id` < ".p4c_result($rs_last_id,0).";",__FILE__,__LINE__);
            }
        }
         */
    
        while($chat_obj = p4c_fetch_object($rs_chats)) {

            $monat = array(1=>"Januar", 2=>"Februar", 3=>"M&auml;rz", 4=>"April", 5=>"Mai", 6=>"Juni", 7=>"Juli", 8=>"August", 9=>"September", 10=>"Oktober", 11=>"November", 12=>"Dezember"); 
            $tag = array("Sun"=>"Sonntag","Mon"=>"Montag","Tue"=>"Dienstag","Wed"=>"Mittwoch","Thu"=>"Donnerstag","Fri"=>"Freitag","Sat"=>"Samstag"); 

            if (date("dmY",strtotime($chat_obj->datetime)) == date("dmy")) {
                $date = date("H:i",strtotime($chat_obj->datetime));
            } else {
                $monat = $monat[date("n", strtotime($chat_obj->datetime))];
                $tag = $tag[date("D",strtotime($chat_obj->datetime))];

                $date = $tag." ".date("d.",strtotime($chat_obj->datetime))." ".$monat." ".date("Y",strtotime($chat_obj->datetime));
            }

            if (!isset($tmp['massage_date'])) {$tmp['massage_date'] = '';}
            if ($tmp['massage_date'] != date("dmY",strtotime($chat_obj->datetime))) {

                $send_jason['message'][] = array(
                    'mess_type' => 'date',
                    'mess_id'   => 0,
                    'message'   => $date
                );
            }

            // Wenn diese Nachricht vom selben Absender wie vorherigere Nachricht        
            if (!isset($tmp['message_from'])) {$tmp['message_from'] = '';}
            if ($tmp['message_from'] == $chat_obj->von.'_'.$chat_obj->von_id) {
                $headline = '';			
            }
            $tmp['message_from'] = $chat_obj->von.'_'.$chat_obj->von_id;
            $tmp['massage_date'] = date("dmY", strtotime($chat_obj->datetime));

            $message_read = 0;
            if ($chat_obj->von === 'member' AND $chat_obj->gelesen !== '1') {
                $message_read = 1;
            }
            
            // Systemnachricht
            if ($chat_obj->systemnachricht == 1) {
                $send_jason['message'][] = array(
                    'mess_type' => 'system',
                    'mess_id'   => $chat_obj->id,
                    'message'   => nl2br($chat_obj->message),
                    'mess_from' => $chat_obj->von,
                    'mess_time' => date("H:i", strtotime($chat_obj->datetime)),
                    'mess_timestamp' => $chat_obj->datetime,
                    'status'    => $chat_obj->gelesen,
                    'message_read'=>$message_read
                );

            // 
            } else {

                /** Prüfen ob Nachricht ein gesendetes EroCMS-Bild ist **/
                if (substr($chat_obj->message, 0, 8) == 'image:::') {
                    $explode_mess = explode(":::",$chat_obj->message);
                    $pfad_thumb = $explode_mess[2];
                    $send_jason['message'][] = array(
                        'mess_type' => 'image',
                        'mess_id'   => $chat_obj->id,
                        'message'   => $pfad_thumb,
                        'mess_from' => $chat_obj->von,
                        'mess_time' => date("H:i", strtotime($chat_obj->datetime)),
                        'mess_timestamp' => $chat_obj->datetime,
                        'status'    => $chat_obj->gelesen,
                        'message_read'=>$message_read
                    );
                }
                
                /** Prüfen ob Nachricht ein gesendetes EroCLoud-Bild ist **/
                else if (substr($chat_obj->message, 0, 17) == 'erocloud_image:::') {
                    $explode = explode(":::",$chat_obj->message);
                    
                    if (isset($explode[3])) {
                        $merchant_id    = $explode[1];
                        $actor_id       = $explode[2];
                        $filename       = $explode[3];
                        
                        /**
                        $allowed_ext_ary = array('.png','.jpg','.gif','.jpeg');
                        $ext_explode = explode(".", $filename);
                        $ext = '.'.end($ext_explode); // Bsp: .jpg

                        if (!in_array($ext, $allowed_ext_ary)) {
                            foreach($allowed_ext_ary as $value) {
                                $name = substr($filename, 0, strpos($filename, $value));
                                if ($name != '') {
                                    $filename = $name.$value;
                                    break;
                                }
                            }
                        }
                        */
                        $file_path = PROFILE_IMAGE_PATH.'/'.MERCHANT_DEFAULT_DIR.'/'.$merchant_id.'/messenger/'.$actor_id.'/'.$filename;
                        if (is_file($file_path)) {
                            if ($merchant_id == $_SESSION['merchant_id']) {

                                $send_jason['message'][] = array(
                                    'mess_type' => 'erocloud_image',
                                    'mess_id'   => $chat_obj->id,
                                    'message'   => $merchant_id.'/'.$actor_id.'/'.$filename,
                                    'mess_from' => $chat_obj->von,
                                    'mess_time' => date("H:i", strtotime($chat_obj->datetime)),
                                    'mess_timestamp' => $chat_obj->datetime,
                                    'status'    => $chat_obj->gelesen,
                                    'message_read'=>$message_read
                                );

                            }
                        } else {
                            $send_jason['message'][] = array(
                                'mess_type' => 'message',
                                'mess_id'   => $chat_obj->id,
                                'message'   => nl2br(htmlentities($chat_obj->message)),
                                'mess_from' => $chat_obj->von,
                                'mess_time' => date("H:i", strtotime($chat_obj->datetime)),
                                'mess_timestamp' => $chat_obj->datetime,
                                'status'    => $chat_obj->gelesen,
                                'message_read'=>$message_read
                            );
                        }
                    }
                }
                
                /** Prüfen ob Nachricht ein gesendetes EroCLoud-Bild ist **/
                else if (substr($chat_obj->message, 0, 15) == 'erocloud_pdf:::') {
                    $explode = explode(":::",$chat_obj->message);
                    
                    $merchant_id    = $explode[1];
                    $actor_id       = $explode[2];
                    $filename       = $explode[3];
                    
                    if ($merchant_id == $_SESSION['merchant_id']) {
                    
                        $url = API_URL.'/MessengerPDF/'.$merchant_id.'/'.$actor_id.'/'.$filename;

                        $send_jason['message'][] = array(
                            'mess_type' => 'erocloud_pdf',
                            'mess_id'   => $chat_obj->id,
                            'message'   => $url,
                            'mess_from' => $chat_obj->von,
                            'mess_time' => date("H:i", strtotime($chat_obj->datetime)),
                            'mess_timestamp' => $chat_obj->datetime,
                            'status'    => $chat_obj->gelesen,
                            'message_read'=>$message_read
                        );
             
                    }
                }


                /** Wenn Nachricht nur eine Nachricht ist **/
                else {
                    
                    // EmojiOne / Smileys
                    include_once(MCP_DIR.'/messenger/includes/emoji/lib/php/autoload.php');
                    $emoji = new Client(new Ruleset());
                    
                    $message = $chat_obj->message;
                    #$message = $emoji->shortnameToImage($chat_obj->message);
                    
                    $send_jason['message'][] = array(
                        'mess_type' => 'message',
                        'mess_id'   => $chat_obj->id,
                        'message'   => nl2br(htmlentities($message)),
                        'mess_from' => $chat_obj->von,
                        'mess_time' => date("H:i", strtotime($chat_obj->datetime)),
                        'mess_timestamp' => $chat_obj->datetime,
                        'status'    => $chat_obj->gelesen,
                        'message_read'=>$message_read
                    );
                }
            }
        }
    }
   
    
    $send_jason['chat_checksum'] = md5(json_encode($send_jason));
    
    echo json_encode($send_jason);
}

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>