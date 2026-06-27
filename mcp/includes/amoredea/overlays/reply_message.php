<?php

/*
 * Diese Datei wird per Ajax geladen aus der /js/scripts.js
 * Dies ist der Inhalt des Kommentar-Antworten-Popups
 */
 
define('SAFE_INC', 1);

include_once("../../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

header('Content-Type: text/html; charset=utf-8');

if (is_logged_in('mcp') === false) {
    exit;
}

$merchant_id = abs($_SESSION['merchant_id']);

$site = '';

// Antwort speichern
if (isset($_POST['chat_id']) AND isset($_POST['send_reply'])) {

    $message = str_replace('<br>', "\n", $_POST['send_reply']);
    $message = str_replace('&nbsp;', ' ', $message);
    $message = trim(strip_tags($message));

    $chat_id = preg_replace ( '/[^a-z0-9_]/i', '', $_POST['chat_id']);
    
    // chat_id = ActorID_MemberID
    $explode = explode('_',$chat_id);

    if (count($explode) != 2) {
        exit;
    }
    
    $actor_id = abs($explode[0]);
    $member_id = abs($explode[1]);
    
    $rs_actor = p4c_query("SELECT * FROM `actors` WHERE `id`='".abs($actor_id)."' AND `merchant_id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__);

    if (!empty($message) AND p4c_num_rows($rs_actor) === 1) {
        $actor_obj = p4c_fetch_object($rs_actor);
             
        $actor_commision = 0;
        $actor_commision_percent = 50;
        $datetime = date("Y-m-d H:i:s");
        $is_mobile = 0;
                
        include_once(MCP_DIR.'/includes/amoredea/classes/ChatMessage.inc.php');

        $mess = new ChatMessage;
            
        $mess->chat_id      = $chat_id;
        $mess->p4c_shop_id  = AMOREDEA_P4C_SHOPID;
        $mess->merchant_id  = $actor_obj->merchant_id;
        $mess->amoredea_von_id= $actor_id;
        $mess->amoredea_an_id = $member_id;
        $mess->von          = 'actor';
        $mess->von_id       = $actor_id;
        $mess->an_id        = $member_id;
        $mess->message      = $message;
        $mess->datetime     = p4c_escape_string($datetime);
        
        if ($mess->send() === 'ok') {
            
            p4c_query("UPDATE `amoredea_messages` SET `gelesen` = '1' WHERE `chat_id`='".p4c_escape_string($chat_id)."' AND `von`='member';",__FILE__,__LINE__);
            p4c_query("UPDATE `amoredea_messages_history` SET `gelesen` = '1' WHERE `chat_id`='".p4c_escape_string($chat_id)."' AND `von`='member';",__FILE__,__LINE__);
            
            $text = '<div class=\"ui-state-highlight\" style=\"font-size:15px; padding:10px; margin:30px 0;\">Antwort gesendet.</div>';

            $site .= '
            <script>
                jQuery(document).ready(function() {

                    jQuery("#reply_message_popup_content").html("'.$text.'");

                    setTimeout(function(){
                        jQuery(".reply_message_popup").hide();
                        jQuery("#overlay").hide();
                    }, 2000);
                })
            </script>

            ';

            echo $site;
        }
    }
   

    exit;
}
    

// Antworten-Maske
if (isset($_POST['chat_id']) AND isset($_POST['member_id'])) {

    $chat_id = preg_replace ( '/[^a-z0-9_]/i', '', $_POST['chat_id']);
    
    // chat_id = ActorID_MemberID
    $explode = explode('_',$chat_id);

    if (count($explode) != 2) {
        exit;
    }
    
    $actor_id = $explode[0];
    $member_id = $explode[1];
    
    // Prüfen ob actor_id zum Merchant gehört.
    // Wenn nicht wurde die Übertragung der chat_id manipuliert.
    // Dann hier abbrechen.
    $rs_actor = p4c_query("SELECT * FROM `actors` WHERE `id`='".abs($actor_id)."' AND `merchant_id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_actor) == 0) {
        exit;
    }
    
    $actor_obj = p4c_fetch_object($rs_actor);
    
    $rs_chats = p4c_query("SELECT * FROM `amoredea_messages` WHERE
        `chat_id` = '".p4c_escape_string($chat_id)."' AND `merchant_id`='".abs($actor_obj->merchant_id)."' ORDER BY `datetime` ASC LIMIT 40;",__FILE__,__LINE__);

    $chat = '';
    $send_jason = array();
    
    if (p4c_num_rows($rs_chats) > 0) {
        while($chat_obj = p4c_fetch_object($rs_chats)) {
        
            $chat .= '';

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
                
            } else {

                /** Prüfen ob Nachricht ein gesendetes EroCLoud-Bild ist **/
                if (substr($chat_obj->message, 0, 17) == 'erocloud_image:::') {
                    
                }

                /** Prüfen ob Nachricht eine gesendete EroCLoud-PDF ist **/
                else if (substr($chat_obj->message, 0, 15) == 'erocloud_pdf:::') {
                    
                }

                /** Wenn Nachricht nur eine Nachricht ist **/
                else {
                    $message = $chat_obj->message;
                    
                    $mess_id     = $chat_obj->id;
                    $mess_from   = $chat_obj->von;
                    $mess_time   = date("H:i", strtotime($chat_obj->datetime));
                    $mess_timestamp = $chat_obj->datetime;
                    $status      = abs($chat_obj->gelesen);

                    if ($mess_from === 'member') {
                        $from_class = 'history_member';
                        $history_triangle = 'history_member_triangle';
                        $from_class_content = 'history_member_content ui-corner-left ui-corner-br';

                        if ($status === 0) {
                            $status = '<span class="history_status_unread material-symbols-outlined" title="ungelesen">done_all</span>';
                        } else if (status === 2) {
                            $status = '<span class="history_status_unread material-symbols-outlined" title="gesendet">done</span>';
                        } else {
                            $status = '<span class="history_status_read material-symbols-outlined" title="gelesen">done_all</span>';
                        }
                        
                    } else {
                        $from_class = 'history_actor';
                        $history_triangle = 'history_actor_triangle';
                        $from_class_content = 'history_actor_content ui-corner-right ui-corner-bl';

                        $status = '&nbsp;&nbsp;';

                    }

                    $chat .= '
                    <div class="'.$from_class.'" data-mess_timestamp="'.$mess_timestamp.'">
                        <div class="'.$history_triangle.'"><div class="'.$history_triangle.'2"></div></div>
                        <div class="ui-widget-content '.$from_class_content.'">
                            <div style="padding:3px 5px; text-align:left;">
                                <span style="line-height: 1.3;">'.$message.'</span>
                            </div>
                            <div class="history_status">'.$mess_time.' '.$status.'</div>
                        </div>
                    </div>';

                }
            }

        }


        
    }
    
    $mysql -> change_connect("amoredea");
    
    $username = '<i>unbekannt</i>';
    
    $rs_member = p4c_query("SELECT *, AES_DECRYPT(`username`, '".AMOREDEA_AES_KEY."') AS `username` FROM `members` WHERE `id`='".abs($member_id)."' AND `actor_id`='".$actor_id."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_member) == 1) {
        $member_obj = p4c_fetch_object($rs_member);
        
        $username = $member_obj->username;
        
        if (empty($username)) {
            $username = '<i>UID'.$member_obj->id.'</i>';
        }
    }
    
    $mysql -> change_connect("");
    
    $site .= '
    <link rel="stylesheet" type="text/css" href="'.URL.'/includes/amoredea/chat_history_box.css" />
    
    <div style="font-size:25px; margin-bottom:20px;">'.$username.' im Chat mit '.$actor_obj->username.'</div>
    <div style="margin-bottom:15px;" id="chat">
        <div id="chat_history">'.$chat.'</div>
    </div>
    <div style="position:relative;">
        <textarea id="reply_text" style="width:100%; height:100px; resize: none; box-sizing: border-box; padding:5px;" placeholder="Schreibe hier deine Antwort und klicke Senden."></textarea>
        <div id="send_reply" title="Antwort senden" class="material-symbols-outlined" style="cursor:pointer; position:absolute; color:#3399ff; font-size:30px; bottom:10px; right:5px;">send</div>
    </div> 
    <div>Zum Absenden der Nachricht, kannst du auch <i>Enter</i> dr&uuml;cken. Mit <i>Shift + Enter</i> f&uuml;gst du einen Zeilenumbruch hinzu.</div>
    

    <script>
        jQuery(document).ready(function() {
            
            jQuery.fn.send_reply = function() {
                var reply_text = jQuery("#reply_text").val();

                if (reply_text.trim() != "") {
                    jQuery.ajax({
                        url:"'.URL.'/includes/amoredea/overlays/reply_message.php",
                        dataType:"text",
                        type: "POST",
                        data: "chat_id='.$chat_id.'&send_reply="+reply_text,
                        success: function(data) {
                            jQuery("#reply_message_popup_content").html(data);
                        }
                    })                
                }
            }

            // Nachricht senden
            jQuery("#reply_text").keydown(function(e) {
                e = e || window.event;
                var key = e.keyCode || e.which || e.charCode, shift = e.modifiers ? e.modifiers & Event.SHIFT_MASK : e.shiftKey;
                if(key == 13 && !shift){
                    jQuery(this).send_reply();
                }
            })

            jQuery("#send_reply").click(function(){
                jQuery(this).send_reply();
            })
            

            // Chat erst scrollen wenn sich Inhalt verändert
            var iScrollHeight = jQuery("#chat_history").prop("scrollHeight");
            jQuery("#chat_history").animate({ scrollTop: iScrollHeight}, "fast");

            jQuery("#chat_history img").on("load",function () {
                var iScrollHeight = jQuery("#chat_history").prop("scrollHeight");
                jQuery("#chat_history").animate({ scrollTop: iScrollHeight}, "fast");
            });

        })
    </script>
    ';

    
    
    echo $site;
}



?>
