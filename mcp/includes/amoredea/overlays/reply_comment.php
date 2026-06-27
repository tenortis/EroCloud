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

// Antwort speíchern
if (isset($_POST['comment_id']) AND isset($_POST['month']) AND isset($_POST['send_reply'])) {

    $comment_id = abs($_POST['comment_id']);
    $month = abs($_POST['month']);
    $reply_text = trim(strip_tags($_POST['send_reply']));
    
    // DB von amoredea auswählen
    $mysql -> change_connect("amoredea");

    $rs_comments = p4c_query("SELECT * FROM `timeline_comments_".abs($month)."` WHERE `id`='".abs($comment_id)."' AND `merchant_id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__);

    if (p4c_num_rows($rs_comments) == 0) {
        $site .= '
        <div class="ui-state-error" style="font-size:15px; padding:10px; margin:30px 0;">Dieser Kommentar existiert nicht mehr.</div>';
        echo $site;
        exit;
    }

    $comment_obj = p4c_fetch_object($rs_comments);
    
    // DB von EroCloud auswählen
    $mysql -> change_connect("");    
    
    $rs_actor = p4c_query("SELECT * FROM `actors` WHERE `id`='".abs($comment_obj->actor_id)."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_actor) > 0) {
        $actor_obj = p4c_fetch_object($rs_actor);
        $actor_username = $actor_obj->username;
    } else {
        $actor_username = 'unbekannt';
    }

    // DB von amoredea auswählen
    $mysql -> change_connect("amoredea");
            
    p4c_query("INSERT INTO `timeline_comments_".$month."` SET
        `content_id` = '".$comment_obj->content_id."',
        `actor_id` = '".$comment_obj->actor_id."',
        `merchant_id` = '".$comment_obj->merchant_id."',
        `member_id` = '0',
        `username` = '". p4c_escape_string($actor_username)."',
        `date_time` = '".date("Y-m-d H:i:s")."',
        `checked` = '1',
        `checked_admin` = '1',
        `comment` = '". p4c_escape_string($reply_text)."'
    ",__FILE__,__LINE__);

    $rs_content_exists = p4c_query("SELECT * FROM `timeline_".$month."` WHERE `id`='".abs($comment_obj->content_id)."' AND `actor_id`='".abs($comment_obj->actor_id)."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_content_exists) > 0) {
        $content_obj = p4c_fetch_object($rs_content_exists);
        p4c_query("UPDATE `timeline_".$month."` SET `number_of_comments`=number_of_comments+1 WHERE `id`='".abs($comment_obj->content_id)."' LIMIT 1;",__FILE__,__LINE__);
    }

    // DB von EroCloud auswählen
    $mysql -> change_connect("");    

    
    $text = '<div class=\"ui-state-highlight\" style=\"font-size:15px; padding:10px; margin:30px 0;\">Antwort gesendet.</div>';
    
    $site .= '
    <script>
        jQuery(document).ready(function() {
        
            jQuery("#reply_comment_popup_content").html("'.$text.'");

            setTimeout(function(){
                jQuery(".reply_comment_popup").hide();
                jQuery("#overlay").hide();
                window.location.reload();
            }, 2000);
        })
    </script>
    
    ';
    
    echo $site;
}
    

// Antworten-Maske
if (isset($_POST['comment_id']) AND isset($_POST['month'])) {

    $comment_id = abs($_POST['comment_id']);
    $month = abs($_POST['month']);

    // DB von amoredea auswählen
    $mysql -> change_connect("amoredea");

    $rs_comments = p4c_query("SELECT * FROM `timeline_comments_".abs($month)."` WHERE `id`='".abs($comment_id)."' AND `merchant_id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__);

    if (p4c_num_rows($rs_comments) == 0) {
        $site .= '
        <div class="ui-state-error" style="font-size:15px; padding:10px; margin:20px 0;">Dieser Kommentar existiert nicht mehr.</div>';
        echo $site;
        exit;
    }
    
    $comment_obj = p4c_fetch_object($rs_comments);

    // DB von EroCloud auswählen
    $mysql -> change_connect("");
    
    $site .= '
    <script>
        jQuery(document).ready(function() {
            
            jQuery.fn.send_reply = function() {
                var reply_text = jQuery("#reply_text").val();

                if (reply_text.trim() != "") {
                    jQuery.ajax({
                        url:"'.URL.'/includes/amoredea/overlays/reply_comment.php",
                        dataType:"text",
                        type: "POST",
                        data: "comment_id='.$comment_id.'&month='.$month.'&send_reply="+reply_text,
                        success: function(data) {
                            jQuery("#reply_comment_popup_content").html(data);
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
        })
    </script>
    
    <div style="font-size:25px; margin-bottom:20px;">Antwort auf Kommentar</div>
    <div style="margin-bottom:15px;"><i>'.$comment_obj->comment.'</i></div>
    <div style="position:relative;">
        <textarea id="reply_text" style="width:100%; height:100px; resize: none; box-sizing: border-box; padding:5px;" placeholder="Schreibe hier deine Antwort und klicke Senden."></textarea>
        <div id="send_reply" title="Antwort senden" class="material-symbols-outlined" style="cursor:pointer; position:absolute; color:#3399ff; font-size:30px; bottom:10px; right:5px;">send</div>
    </div> 
    <div>Zum Absenden der Nachricht, kannst du auch <i>Enter</i> dr&uuml;cken. Mit <i>Shift + Enter</i> f&uuml;gst du einen Zeilenumbruch hinzu.</div>
    
    ';

    
    
    echo $site;
}



?>
