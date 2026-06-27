<?php
 
if (!defined('SAFE_INC')) {
    die ("Access denied!");
}

$actor_id = abs(filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT));

include_once(SOURCEDIR.'/includes/klassen/actor.inc.php');
$actor = new Actor($actor_id);

if ($actor->get("id") == '') {
    header('Location: '.MCP_URL.'/Actors');
    exit;    
}

if ($actor->get("merchant_id") != abs($_SESSION['merchant_id'])) {
    header('Location: '.MCP_URL.'/Actors');
    exit;            
}

if ($actor->get("status") == 'deleted') {
    header('Location: '.MCP_URL.'/Actors');
    exit;        
}

// Nachrichtenpreis für neue User ändern
if (isset($_POST['save_newuser_pn_amount'])) {
    $post_newuser_pn_amount = abs(filter_input(INPUT_POST, 'newuser_pn_amount', FILTER_SANITIZE_NUMBER_INT));
    
    p4c_query("UPDATE `actors` SET `pn_amount` ='".$post_newuser_pn_amount."' WHERE
        `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
        `id`='".abs($actor_id)."' LIMIT 1;
    ",__FILE__,__LINE__);
    
    $m5d_checksum = actor_checksum($actor_id);
        
    p4c_query("UPDATE `actors` SET `md5_checksum`='".p4c_escape_string($m5d_checksum)."' WHERE
        `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
        `id`='".abs($actor_id)."' LIMIT 1;
    ",__FILE__,__LINE__);

    header('Location: '.MCP_URL.'/Actor/'.$actor_id.'?ok=edit_actor');
    exit;
}

// Nachrichten nicht-/kostenlos wenn die Webcam gesendet wird
if (isset($_POST['save_pn_free_if_webcam'])) {    
    $post_pn_free_if_webcam = abs(filter_input(INPUT_POST, 'pn_free_if_webcam', FILTER_SANITIZE_NUMBER_INT));
    
    p4c_query("UPDATE `actors` SET `pn_free_if_webcam` ='".$post_pn_free_if_webcam."' WHERE
        `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
        `id`='".abs($actor_id)."' LIMIT 1;
    ",__FILE__,__LINE__);
    
    $m5d_checksum = actor_checksum($actor_id);
        
    p4c_query("UPDATE `actors` SET `md5_checksum`='".p4c_escape_string($m5d_checksum)."' WHERE
        `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
        `id`='".abs($actor_id)."' LIMIT 1;
    ",__FILE__,__LINE__);

    header('Location: '.MCP_URL.'/Actor/'.$actor_id.'?ok=edit_actor');
    exit;  
    
}

// Webcam-Preis für neue User ändern
if (isset($_POST['save_newuser_cam_amount'])) {
    $post_newuser_cam_amount = abs(filter_input(INPUT_POST, 'newuser_cam_amount', FILTER_SANITIZE_NUMBER_INT));
    
    p4c_query("UPDATE `actors` SET `cam_amount` ='".$post_newuser_cam_amount."' WHERE
        `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
        `id`='".abs($actor_id)."' LIMIT 1;
    ",__FILE__,__LINE__);
    
    $m5d_checksum = actor_checksum($actor_id);
        
    p4c_query("UPDATE `actors` SET `md5_checksum`='".p4c_escape_string($m5d_checksum)."' WHERE
        `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
        `id`='".abs($actor_id)."' LIMIT 1;
    ",__FILE__,__LINE__);

    header('Location: '.MCP_URL.'/Actor/'.$actor_id.'?ok=edit_actor');
    exit;
}

//Wann darf der User sein Webcam senden?  
if (isset($_POST['save_usercam_if_amacam'])) {
    $post_usercam_if_amacam = abs(filter_input(INPUT_POST, 'usercam_if_amacam', FILTER_SANITIZE_NUMBER_INT));
    
    p4c_query("UPDATE `actors` SET `usercam_if_amacam` ='".$post_usercam_if_amacam."' WHERE
        `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
        `id`='".abs($actor_id)."' LIMIT 1;
    ",__FILE__,__LINE__);
    
    $m5d_checksum = actor_checksum($actor_id);
        
    p4c_query("UPDATE `actors` SET `md5_checksum`='".p4c_escape_string($m5d_checksum)."' WHERE
        `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
        `id`='".abs($actor_id)."' LIMIT 1;
    ",__FILE__,__LINE__);

    header('Location: '.MCP_URL.'/Actor/'.$actor_id.'?ok=edit_actor');
    exit;
}

// Webcam-Sound wenn User die Cam sehen will  
if (isset($_POST['save_webcam_user_sound'])) {
    $post_sound = filter_input(INPUT_POST, 'cam_sound', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
    
    p4c_query("UPDATE `actors` SET `cam_new_user_sound` ='".$post_sound."' WHERE
        `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
        `id`='".abs($actor_id)."' LIMIT 1;
    ",__FILE__,__LINE__);

    header('Location: '.MCP_URL.'/Actor/'.$actor_id.'?ok=edit_actor');
    exit;
}


/*
// Nachrichtenpreis bei allen Usern ändern
if (isset($_POST['save_global_pn_amount'])) {    
    $post_global_pn_amount  = abs(filter_input(INPUT_POST, 'global_pn_amount', FILTER_SANITIZE_NUMBER_INT));
    
    p4c_query("UPDATE `actor_member_info` SET `pn_amount`='".$post_global_pn_amount."' WHERE
        `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
        `actor_id`='".abs($actor_id)."';
    ", __FILE__, __LINE__);
        
    p4c_query("UPDATE `actors` SET `pn_amount` ='".$post_global_pn_amount."' WHERE
        `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
        `id`='".abs($actor_id)."' LIMIT 1;
    ",__FILE__,__LINE__);
    
    $m5d_checksum = actor_checksum($actor_id);
        
    p4c_query("UPDATE `actors` SET `md5_checksum`='".p4c_escape_string($m5d_checksum)."' WHERE
        `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
        `id`='".abs($actor_id)."' LIMIT 1;
    ",__FILE__,__LINE__);

    header('Location: '.MCP_URL.'/Actor/'.$actor_id.'?ok=edit_actor');
    exit;    
}
*/


if (isset($_POST['edit_actor'])) {

    $interests = '';
    if (isset($_POST['interests']) AND is_array($_POST['interests'])) {
        foreach($_POST['interests'] as $key => $value) {
            $interests .= htmlentities($key).'|';
        }
        if (substr($interests, -1, 1) == '|') {$interests = substr($interests, 0, -1);}
    }
    
    $looking_for = '';
    if (isset($_POST['looking_for']) AND is_array($_POST['looking_for'])) {
        foreach($_POST['looking_for'] as $key => $value) {
            $looking_for .= htmlentities($key).'|';
        }
        if (substr($looking_for, -1, 1) == '|') {$looking_for = substr($looking_for, 0, -1);}
    }
    
    $sexual_preferences = '';
    if (isset($_POST['sexual_preferences']) AND is_array($_POST['sexual_preferences'])) {
        foreach($_POST['sexual_preferences'] as $key => $value) {
            $sexual_preferences .= htmlentities($key).'|';
        }
        if (substr($sexual_preferences, -1, 1) == '|') {$sexual_preferences = substr($sexual_preferences, 0, -1);}
    }
   
    $cup_siceA = filter_input(INPUT_POST, 'cup_size_a', FILTER_SANITIZE_NUMBER_INT);
    $cup_siceB = filter_input(INPUT_POST, 'cup_size_b', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
    $cup_sice = $cup_siceA.$cup_siceB;
    
    if ($actor->get("status") == 'inactive') {
        p4c_query("UPDATE `actors` SET `created_datetime`='0000-00-00 00:00:00'
        WHERE
            `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
            `id`='".abs($actor_id)."' LIMIT 1;
        ",__FILE__,__LINE__);
    }
    
    p4c_query("UPDATE `actors` SET
        `obolus_type`           ='".p4c_escape_string(filter_input(INPUT_POST, 'obolus_type', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW))."',
        `check_gender`          ='".abs(filter_input(INPUT_POST, 'check_gender', FILTER_SANITIZE_NUMBER_INT))."',
        `gender`                ='".p4c_escape_string(utf8_decode(filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW)))."',
        `check_age`             ='".abs(filter_input(INPUT_POST, 'check_age', FILTER_SANITIZE_NUMBER_INT))."',
        `age`                   ='".abs(filter_input(INPUT_POST, 'age', FILTER_SANITIZE_NUMBER_INT))."',
        `check_star_sign`       ='".abs(filter_input(INPUT_POST, 'check_star_sign', FILTER_SANITIZE_NUMBER_INT))."',
        `star_sign`             ='".p4c_escape_string(utf8_decode(filter_input(INPUT_POST, 'star_sign', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW)))."',
        `check_body_height`     ='".abs(filter_input(INPUT_POST, 'check_body_height', FILTER_SANITIZE_NUMBER_INT))."',
        `body_height`           ='".abs(filter_input(INPUT_POST, 'body_height', FILTER_SANITIZE_NUMBER_INT))."',
        `check_eye_color`       ='".abs(filter_input(INPUT_POST, 'check_eye_color', FILTER_SANITIZE_NUMBER_INT))."',
        `eye_color`             ='".p4c_escape_string(filter_input(INPUT_POST, 'eye_color', FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_LOW))."',
        `check_hair_color`      ='".abs(filter_input(INPUT_POST, 'check_hair_color', FILTER_SANITIZE_NUMBER_INT))."',
        `hair_color`            ='".p4c_escape_string(filter_input(INPUT_POST, 'hair_color', FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_LOW))."',
        `check_body_weight`     ='".abs(filter_input(INPUT_POST, 'check_body_weight', FILTER_SANITIZE_NUMBER_INT))."',
        `body_weight`           ='".abs(filter_input(INPUT_POST, 'body_weight', FILTER_SANITIZE_NUMBER_INT))."',
        `check_cup_size`        ='".abs(filter_input(INPUT_POST, 'check_cup_size', FILTER_SANITIZE_NUMBER_INT))."',
        `cup_size`              ='".p4c_escape_string($cup_sice)."',
        `check_shaven`          ='".abs(filter_input(INPUT_POST, 'check_shaven', FILTER_SANITIZE_NUMBER_INT))."',
        `shaven`                ='".abs(filter_input(INPUT_POST, 'shaven', FILTER_SANITIZE_NUMBER_INT))."',
        `check_look`            ='".abs(filter_input(INPUT_POST, 'check_look', FILTER_SANITIZE_NUMBER_INT))."',
        `look`                  ='".p4c_escape_string(filter_input(INPUT_POST, 'look', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW))."',
        `check_marital_status`  ='".abs(filter_input(INPUT_POST, 'check_marital_status', FILTER_SANITIZE_NUMBER_INT))."',
        `marital_status`        ='".p4c_escape_string(filter_input(INPUT_POST, 'marital_status', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW))."',
        `check_sexual_orientation`='".abs(filter_input(INPUT_POST, 'check_sexual_orientation', FILTER_SANITIZE_NUMBER_INT))."',
        `sexual_orientation`    ='".p4c_escape_string(filter_input(INPUT_POST, 'sexual_orientation', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW))."',
        `check_looking_for`     ='".abs(filter_input(INPUT_POST, 'check_looking_for', FILTER_SANITIZE_NUMBER_INT))."',
        `looking_for`           ='".p4c_escape_string($looking_for)."',
        `check_interests`       ='".abs(filter_input(INPUT_POST, 'check_interests', FILTER_SANITIZE_NUMBER_INT))."',
        `interests`             ='".p4c_escape_string($interests)."',
        `check_sexual_preferences`='".abs(filter_input(INPUT_POST, 'check_sexual_preferences', FILTER_SANITIZE_NUMBER_INT))."',
        `sexual_preferences`    ='".p4c_escape_string($sexual_preferences)."',
        `check_about_me`        ='".abs(filter_input(INPUT_POST, 'check_about_me', FILTER_SANITIZE_NUMBER_INT))."',
        `about_me`              ='".p4c_escape_string(filter_input(INPUT_POST, 'about_me', FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_LOW))."'
    WHERE
        `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
        `id`='".abs($actor_id)."' LIMIT 1;
    ",__FILE__,__LINE__);
    
    $m5d_checksum = actor_checksum($actor_id);
        
    p4c_query("UPDATE `actors` SET `md5_checksum`='".p4c_escape_string($m5d_checksum)."' WHERE
        `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
        `id`='".abs($actor_id)."' LIMIT 1;
    ",__FILE__,__LINE__);

    header('Location: '.MCP_URL.'/Actor/'.$actor_id.'?ok=edit_actor');
    exit;
    
}

$site .= '
<link rel="stylesheet" type="text/css" href="'.MCP_URL.'/css/uploadfile.css" />
<script type="text/javascript" src="'.MCP_URL.'/js/jquery.form.js"></script>
<script type="text/javascript" src="'.MCP_URL.'/js/jquery.uploadfile.min.js"></script>


<script type="text/javascript">
// <![CDATA[
    jQuery(document).ready(function() {
        
        jQuery(".radio_set fieldset").buttonset();

        jQuery("input[type=radio], input[type=checkbox]").checkboxradio({
            icon: false
        });
        
        jQuery(".radio_set").controlgroup();

        jQuery( "#slider-age" ).slider({
            range: "max",
            min: 18,
            max: 99,
            value: '.$actor->get("age").',
            slide: function( event, ui ) {
                jQuery( "#age" ).val( ui.value );
            }
        });
        jQuery("#age").val(jQuery("#slider-age").slider("value"));
        
        jQuery( "#slider-body_height" ).slider({
            range: "max",
            min: 100,
            max: 230,
            value: '.$actor->get("body_height").',
            slide: function( event, ui ) {
                jQuery( "#body_height" ).val( ui.value );
            }
        });
        jQuery("#body_height").val(jQuery("#slider-body_height").slider("value"));

        jQuery( "#slider-body_weight" ).slider({
            range: "max",
            min: 45,
            max: 150,
            value: '.$actor->get("body_weight").',
            slide: function( event, ui ) {
                jQuery( "#body_weight" ).val( ui.value );
            }
        });
        jQuery("#body_weight").val(jQuery("#slider-body_weight").slider("value"));

    })
   
// ]]>
</script>

<div id="site_actor" style="width:600px; margin-bottom:50px;">';
    if ($actor->get("status") == 'inactive') {
        $site .= '<div class="info_box" style="padding:10px; margin-bottom:10px;">'
                . '<b>Diese Profil wird aktuell gepr&uuml;ft.</b> Nach erfolgreicher Pr&uuml;fung, kann es bis zu 60 Minuten dauern bis du mit diesem Profil im Messenger chatten kannst.</div>';
    } else if ($actor->get("status") != 'active') {
        $site .= '<div class="ui-state-error" style="padding:10px; margin-bottom:10px;">Diese Profil ist gesperrt.</div>';
    }
    
    if (empty($actor->get("profile_image_fsk16")) OR empty($actor->get("profile_image_fsk18"))) {
        $site .= '<div class="ui-state-error" style="padding:10px; margin-bottom:10px;">Damit das Profil aktiviert werden kann, musst du noch Profilbilder hochladen.</div>';
    }

    $site .= ' 
    <center><h1 class="h4">'.$actor->get("username").'</h1></center>';

    $avatar_fsk16 = MCP_URL.'/ProfilePicture/'.$actor->get("profile_image_fsk16");
    $avatar_fsk18 = MCP_URL.'/ProfilePicture/'.$actor->get("profile_image_fsk18");

    $site .= '
    <script type="text/javascript">
        // <![CDATA[
            jQuery(document).ready(function() {
            ';
              // http://hayageek.com/docs/jquery-upload-file.php
            $site .= '
            jQuery("#uploader_fsk16_profile_image").uploadFile({
                url:"'.MCP_URL.'/includes/uploader/upload_profile_image.php?fsk=16&actor_id='.$actor_id.'",
                fileName:"profile_image",
                uploadStr:"FSK16 Profilbild hochladen",
                abortStr:"Abbrechen",
                maxFileSize:5120*1024, // 5 MB
                sizeErrorStr:"ist zu gro&szlig;. Erlaubte maximale Deteigr&ouml;&szlig;e: ",
                acceptFiles:"image/x-png,image/jpeg",
                allowedTypes:"jpg,jpeg,png",
                returnType:"json",
                showPreview:true,
                previewHeight: "100px",
                previewWidth: "100px",
                multiple:false,
                showProgress:true,
                onSuccess:function(files,data,xhr,pd) {
                    window.location.href="'.MCP_URL.'/Actor/'.$actor_id.'?ok=upload_profileimage";
                }
            });

            jQuery("#uploader_fsk18_profile_image").uploadFile({
                url:"'.MCP_URL.'/includes/uploader/upload_profile_image.php?fsk=18&actor_id='.$actor_id.'",
                fileName:"profile_image",
                uploadStr:"FSK18 Profilbild hochladen",
                abortStr:"Abbrechen",
                maxFileSize:5120*1024, // 5 MB
                sizeErrorStr:"ist zu gro&szlig;. Erlaubte maximale Deteigr&ouml;&szlig;e: ",
                acceptFiles:"image/x-png,image/jpeg",
                allowedTypes:"jpg,jpeg,png",
                returnType:"json",
                showPreview:true,
                previewHeight: "100px",
                previewWidth: "100px",
                multiple:false,
                showProgress:true,
                onSuccess:function(files,data,xhr,pd) {
                    window.location.href="'.MCP_URL.'/Actor/'.$actor_id.'?ok=upload_profileimage";
                }
            });
        })

    // ]]>
    </script>

    <ul id="actors">
        <li>
            <div class="avatar"><img src="'.$avatar_fsk16.'" alt="" /></div>
            <div class="fsk">FSK16 (unter 18 Jahre)</div>
        </li>
        <li>
            <div class="avatar"><img src="'.$avatar_fsk18.'" alt="" /></div>
            <div class="fsk">FSK18 (ab 18 Jahre)</div>
        </li>
    </ul>

    <div class="ui-widget-header" style="padding:5px 10px; border-bottom:none;">Profilbilder hochladen</div>
    <div class="ui-widget-content" style="padding:10px; margin-bottom:20px;">
        <div style="margin-bottom:15px;">
            Erlaubte Dateiformate: jpg & png<br />
            Maximale Dateigr&ouml;&szlig;e: 5 MB
        </div>
        <div id="uploader_fsk16_profile_image">FSK16 Profilbild akualisieren</div>
        <div id="uploader_fsk18_profile_image" style="margin-top:3px;">FSK18 Profilbild hochladen</div>
    </div>';

    if (isset($_GET['ok'])) {
        if ($_GET['ok'] === 'edit_actor') {
            $mess = 'Profil gespeichert.';
        }

        if (isset($mess) AND trim($mess) != '') {
            $site .= '
            <script>// <![CDATA[
                jQuery(document).ready(function() {
                    setTimeout(function() {
                        jQuery(".ok").fadeOut("slow");
                    },5000);
                })
            // ]]></script>
            <div class="ui-state-highlight ok" style="padding:10px; margin-bottom:20px">'.$mess.'</div>';
        }
    }

    if ($actor->get("pn_free_if_webcam") === '1') {$selected_pn_free_if_webcam = 'selected';} else {$selected_pn_free_if_webcam = '';}
    if ($actor->get("usercam_if_amacam") === '1') {$selected_usercam_if_amacam = 'selected';} else {$selected_usercam_if_amacam = '';}
    if ($actor->get("cam_new_user_sound") === 'new_user2') {$selected_cam_new_user_sound = 'selected';} else {$selected_cam_new_user_sound = '';}
   
    if ($actor->get("is_displayed_as") == 'only_upload_actor') { 
        $site .= '
        <div class="info_box" style="margin-bottom:10px">Mit diesem Profil k&ouml;nnen nur Filme und Fotoalben hochgeladen werden. Das Chatten mit Kunden im Messenger oder die Webcam senden, ist nicht m&ouml;glich.</div>';
    }
    $site .= '
    <form method="post">    
        <div id="messenger_settings">
            <div class="ui-widget-header" style="padding:5px; 10px; border-bottom:none;">Messenger - Einstellungen</div>
            <div class="ui-widget-content" style="padding:10px;">
                <div>Nachrichtenpreis f&uuml;r neue User: <input type="text" name="newuser_pn_amount" maxlength="3" value="'.$actor->get("pn_amount").'"> Coins. <input type="submit" name="save_newuser_pn_amount" value="Speichern" /></div>
                <!-- <div>Nachrichtenpreis bei allen Usern zur&uuml;ck setzen auf: <input type="text" name="global_pn_amount" maxlength="3" value="'.$actor->get("pn_amount").'"> Coins. <input type="submit" name="save_global_pn_amount" value="Speichern" /></div> //-->
                <div>Nachrichten kostenlos wenn User meine Webcam sieht: 
                    <select name="pn_free_if_webcam">
                        <option value="0">Nein</option>
                        <option value="1" '.$selected_pn_free_if_webcam.'>Ja</option> 
                    </select> <input type="submit" name="save_pn_free_if_webcam" value="Speichern" />
                </div>
            </div>
        </div>
        
        <div id="webcam_settings">
            <div class="ui-widget-header" style="padding:5px; 10px; border-bottom:none;">Webcam - Einstellungen</div>
            <div class="ui-widget-content" style="padding:10px;">
                <div style="padding-bottom:2px;">Webcam-Preis f&uuml;r neue User: <input type="text" name="newuser_cam_amount" maxlength="3" value="'.$actor->get("cam_amount").'"> Coins pro Minute. <input type="submit" name="save_newuser_cam_amount" value="Speichern" /></div>
                <div style="padding-bottom:2px;">Wann darf der User sein Webcam senden? 
                    <select name="usercam_if_amacam" style="width:150px;">
                        <option value="0">Immer</option>
                        <option value="1" '.$selected_usercam_if_amacam.'>Nur wenn er meine Webcam sieht</option> 
                    </select> <input type="submit" name="save_usercam_if_amacam" value="Speichern" />
                </div>
                <div>Dieser Sound ert&ouml;hnt wenn der User deine Cam sehen will:
                    <select style="style="width:100px" name="cam_sound" id="cam_sound">
                        <option value="new_user1">Sound 1</option>
                        <option '.$selected_cam_new_user_sound.' value="new_user2">Sound 2</option>
                    </select> <i id="play_cam_sound" class="material-symbols-outlined">play_circle</i> <input type="submit" name="save_webcam_user_sound" value="Speichern" />
                    <div id="new_webcam_user_sound_player"></div> 
                </div>
            </div>
            
        </div>

    </form>
    
    <form method="post">

        <div class="ui-widget-header" style="padding:5px; 10px; border-bottom:none;">Soll der User dir "Trinkgeld senden" (Porno/Erotik) oder "Tribut zollen" (Fetisch/Domina)</div>
        <div class="ui-widget-content profil-setting" style="margin-bottom:20px;">';
            $obolus_type_ary = array('tribute' => 'Tribut zollen', 'tip' => 'Trinkgeld senden');
            $site .= '<select name="obolus_type" style="width:100%;">';
                foreach ($obolus_type_ary as $type => $value) {
                    if ($actor->get("obolus_type") == $type) {$selected='selected="selected"';} else {$selected='';}								
                    $site .= '<option '.$selected.' value="'.$type.'">'.utf8_encode($value).'</option>';	
                }
            $site .= '
            </select>
        </div>

        <h2 >&Uuml;ber mich</h2>';
   
        if ($actor->get("check_about_me") == 1) {$checked1='checked="checked"'; $checked2='';} else {$checked1=''; $checked2='checked="checked"';}
        $site .= '
        <div class="ui-widget-header radio_set">
            <fieldset>
                <input type="radio" id="ueber_mich1" value="1" name="check_about_me" '.$checked1.' /><label for="ueber_mich1">Anzeigen</label>
                <input type="radio" id="ueber_mich2" value="0" name="check_about_me" '.$checked2.' /><label for="ueber_mich2">Aus</label>
            </fieldset>
        </div>
        <div class="ui-widget-content profil-setting">    
            <textarea name="about_me">'.$actor->get("about_me").'</textarea>
        </div>


        <h2 style="margin-top:20px;">K&ouml;rper und Aussehen</h2>';

        if ($actor->get("check_age") == 1) {$checked1='checked="checked"'; $checked2='';} else {$checked1=''; $checked2='checked="checked"';}
        $site .= '
        <div class="ui-widget-header radio_set">
            <span>Alter</span>
            <fieldset>
                <input type="radio" id="alter1" value="1" name="check_age" '.$checked1.' /><label for="alter1">Anzeigen</label>
                <input type="radio" id="alter2" value="0" name="check_age" '.$checked2.' /><label for="alter2">Aus</label>
            </fieldset>
        </div>
        <div class="ui-widget-content profil-setting">
            <table>
                <tr>
                    <td style="width:25px;">
                        <input type="text" id="age" name="age" />
                    </td>
                    <td style="width:auto;"> 
                        <div id="slider-age"></div>
                    </div>
                </tr>
            </table>
        </div>';

        if ($actor->get("check_star_sign") == 1) {$checked1='checked="checked"'; $checked2='';} else {$checked1=''; $checked2='checked="checked"';}
        $site .= '
        <div class="ui-widget-header radio_set">
            <span>Sternzeichen</span>
            <fieldset>
                <input type="radio" id="sternzeichen1" value="1" name="check_star_sign" '.$checked1.' /><label for="sternzeichen1">Anzeigen</label>
                <input type="radio" id="sternzeichen2" value="0" name="check_star_sign" '.$checked2.' /><label for="sternzeichen2">Aus</label>
            </fieldset>
        </div>
        <div class="ui-widget-content profil-setting">';
            $sternzeichen_ary = array('Steinbock', 'Wassermann', 'Fische', 'Widder', 'Stier', 'Zwillinge', 'Krebs', 'Löwe', 'Jungfrau', 'Waage', 'Skorpion', 'Schütze', );
            $site .= '<select name="star_sign" style="width:100%;">';
                foreach ($sternzeichen_ary as $value) {
                    if ($actor->get("star_sign") == $value) {$selected='selected="selected"';} else {$selected='';}								
                    $site .= '<option '.$selected.' value="'.$value.'">'.utf8_encode($value).'</option>';	
                }
            $site .= '
            </select>
        </div>';

        if ($actor->get("check_body_height") == 1) {$checked1='checked="checked"'; $checked2='';} else {$checked1=''; $checked2='checked="checked"';}
        $site .= '
        <div class="ui-widget-header radio_set">
            <span>K&ouml;rpergr&ouml;&szlig;e</span>
            <fieldset>
                <input type="radio" id="groesse1" value="1" name="check_body_height" '.$checked1.' /><label for="groesse1">Anzeigen</label>
                <input type="radio" id="groesse2" value="0" name="check_body_height" '.$checked2.' /><label for="groesse2">Aus</label>
            </fieldset>
        </div>
        <div class="ui-widget-content profil-setting">
            <table>
                <tr>
                    <td style="width:25px;">
                        <input type="text" id="body_height" name="body_height" />
                    </td>
                    <td style="width:auto;"> 
                        <div id="slider-body_height"></div>
                    </div>
                </tr>
            </table>    
        </div>';

        if ($actor->get("check_body_weight") == 1) {$checked1='checked="checked"'; $checked2='';} else {$checked1=''; $checked2='checked="checked"';}
        $site .= '
        <div class="ui-widget-header radio_set">
            <span>K&ouml;rpergewicht</span>
            <fieldset>
                <input type="radio" id="gewicht1" value="1" name="check_body_weight" '.$checked1.' /><label for="gewicht1">Anzeigen</label>
                <input type="radio" id="gewicht2" value="0" name="check_body_weight" '.$checked2.' /><label for="gewicht2">Aus</label>
            </fieldset>
        </div>
        <div class="ui-widget-content profil-setting">
            <table>
                <tr>
                    <td style="width:25px;">
                        <input type="text" id="body_weight" name="body_weight" />
                    </td>
                    <td style="width:auto;"> 
                        <div id="slider-body_weight"></div>
                    </div>
                </tr>
            </table>    
        </div>';

        if ($actor->get("check_gender") == 1) {$checked1='checked="checked"'; $checked2='';} else {$checked1=''; $checked2='checked="checked"';}
        $site .= '
        <div class="ui-widget-header radio_set">
            <span>Geschlecht</span>
            <fieldset>
                <input type="radio" id="geschlecht1" value="1" name="check_gender" '.$checked1.' /><label for="geschlecht1">Anzeigen</label>
                <input type="radio" id="geschlecht2" value="0" name="check_gender" '.$checked2.' /><label for="geschlecht2">Aus</label>
            </fieldset>
        </div>
        <div class="ui-widget-content profil-setting">';
            $gender_ary = array('f' => 'Weiblich', 'm'=> 'M&auml;nnlich', 't' => 'Transgender');
            $site .= '
            <select name="gender" style="width:100%;">';
                foreach ($gender_ary as $key => $value) {
                    if ($actor->get("gender") == $key) {$selected='selected="selected"';} else {$selected='';}								
                    $site .= '<option '.$selected.' value="'.$key.'">'.$value.'</option>';	
                }
                $site .= '
            </select>
        </div>';

        if ($actor->get("check_shaven") == 1) {$checked1='checked="checked"'; $checked2='';} else {$checked1=''; $checked2='checked="checked"';}
        $site .= '
        <div class="ui-widget-header radio_set">
            <span>Intimrasur</span>
            <fieldset>
                <input type="radio" id="intimrasur1" value="1" name="check_shaven" '.$checked1.' /><label for="intimrasur1">Anzeigen</label>
                <input type="radio" id="intimrasur2" value="0" name="check_shaven" '.$checked2.' /><label for="intimrasur2">Aus</label>
            </fieldset>
        </div>
        <div class="ui-widget-content profil-setting">';
            $shaven_ary = array(1 => 'Ja', 0 => 'Nein');
            $site .= '
            <select name="shaven" style="width:100%;">';
                foreach ($shaven_ary as $key => $value) {
                    if ($actor->get("shaven") == $key) {$selected='selected="selected"';} else {$selected='';}								
                    $site .= '<option '.$selected.' value="'.$key.'">'.$value.'</option>';	
                }
                $site .= '
            </select>
        </div>';

        if ($actor->get("check_hair_color") == 1) {$checked1='checked="checked"'; $checked2='';} else {$checked1=''; $checked2='checked="checked"';}
        $site .= '
        <div class="ui-widget-header radio_set">
            <span>Haarfarbe</span>
            <fieldset>
                <input type="radio" id="haarfarbe1" value="1" name="check_hair_color" '.$checked1.' /><label for="haarfarbe1">Anzeigen</label>
                <input type="radio" id="haarfarbe2" value="0" name="check_hair_color" '.$checked2.' /><label for="haarfarbe2">Aus</label>
            </fieldset>
        </div>
        <div class="ui-widget-content profil-setting">';
            $hair_color_ary = array('wei&szlig;', 'blond', 'dunkelblond', 'rot', 'hellbraun', 'dunkelbraun', 'schwarz');
            $site .= '<select name="hair_color" style="width:100%;">';
                foreach ($hair_color_ary as $value) {
                    if ($actor->get("hair_color") == html_entity_decode($value)) {$selected='selected="selected"';} else {$selected='';}
                    $site .= '<option '.$selected.' value="'.$value.'">'.utf8_encode($value).'</option>';	
                }
            $site .= '
            </select>
        </div>';

        if ($actor->get("check_eye_color") == 1) {$checked1='checked="checked"'; $checked2='';} else {$checked1=''; $checked2='checked="checked"';}
        $site .= '
        <div class="ui-widget-header radio_set">
            <span>Augenfarbe</span>
            <fieldset>
                <input type="radio" id="augenfarbe1" value="1" name="check_eye_color" '.$checked1.' /><label for="augenfarbe1">Anzeigen</label>
                <input type="radio" id="augenfarbe2" value="0" name="check_eye_color" '.$checked2.' /><label for="augenfarbe2">Aus</label>
            </fieldset>
        </div>
        <div class="ui-widget-content profil-setting">';
            $eye_color_ary = array('Hellblau', 'Blaugrau', 'Dunkelblau', 'Hellbraun', 'Dunkelbraun', 'Hellgr&uuml;n', 'Gr&uuml;ngrau', 'Dunkelgr&uuml;n');
            $site .= '<select name="eye_color" style="width:100%;">';
                foreach ($eye_color_ary as $value) {
                    if ($actor->get("eye_color") == html_entity_decode($value)) {$selected='selected="selected"';} else {$selected='';}
                    $site .= '<option '.$selected.' value="'.$value.'">'.utf8_encode($value).'</option>';	
                }
            $site .= '
            </select>
        </div>';

        if ($actor->get("check_cup_size") == 1) {$checked1='checked="checked"'; $checked2='';} else {$checked1=''; $checked2='checked="checked"';}
        $site .= '
        <div class="ui-widget-header radio_set">
            <span>K&ouml;rbchengr&ouml;&szlig;e</span>
            <fieldset>
                <input type="radio" id="koerbchen_groesse1" value="1" name="check_cup_size" '.$checked1.' /><label for="koerbchen_groesse1">Anzeigen</label>
                <input type="radio" id="koerbchen_groesse2" value="0" name="check_cup_size" '.$checked2.' /><label for="koerbchen_groesse2">Aus</label>
            </fieldset>
        </div>
        <div class="ui-widget-content profil-setting">';
            $cup_size_a = (int)$actor->get('cup_size');
            $cup_sizeA_ary = array('65', '70', '75', '80', '85', '90', '95', '100', '105', '110');
            $site .= '<select name="cup_size_a" style="width:45px; text-align:right;">';
                foreach ($cup_sizeA_ary as $value) {
                    if ($cup_size_a == $value) {$selected='selected="selected"';} else {$selected='';}
                    $site .= '<option '.$selected.' value="'.$value.'">'.utf8_encode($value).'</option>';	
                }
            $site .= '
            </select>';
            $cup_size_b = str_replace($cup_size_a, '', $actor->get('cup_size'));
            $cup_sizeB_ary = array('AA', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K');
            $site .= '<select name="cup_size_b" style="width:40px; text-align:left;">';
                foreach ($cup_sizeB_ary as $value) {
                    if ($cup_size_b == $value) {$selected='selected="selected"';} else {$selected='';}
                    $site .= '<option '.$selected.' value="'.$value.'">'.utf8_encode($value).'</option>';	
                }
            $site .= '
            </select>
        </div>';

        if ($actor->get("check_sexual_orientation") == 1) {$checked1='checked="checked"'; $checked2='';} else {$checked1=''; $checked2='checked="checked"';}
        $site .= '
        <div class="ui-widget-header radio_set">
            <span>Sexuelle Orientierung</span>
            <fieldset>
                <input type="radio" id="sexuelle_orientierung1" value="1" name="check_sexual_orientation" '.$checked1.' /><label for="sexuelle_orientierung1">Anzeigen</label>
                <input type="radio" id="sexuelle_orientierung2" value="0" name="check_sexual_orientation" '.$checked2.' /><label for="sexuelle_orientierung2">Aus</label>
            </fieldset>
        </div>
        <div class="ui-widget-content profil-setting">';
            $sexual_orientation_ary = array('Hetero', 'Homo', 'Bi');
            $site .= '
            <select name="sexual_orientation" style="width:100%;">';
                foreach ($sexual_orientation_ary as $value) {
                    if ($actor->get("sexual_orientation") == $value) {$selected='selected="selected"';} else {$selected='';}								
                    $site .= '<option '.$selected.' value="'.$value.'">'.$value.'</option>';	
                }
                $site .= '
            </select>
        </div>';

        if ($actor->get("check_marital_status") == 1) {$checked1='checked="checked"'; $checked2='';} else {$checked1=''; $checked2='checked="checked"';}
        $site .= '
        <div class="ui-widget-header radio_set">
            <span>Familienstand</span>
            <fieldset>
                <input type="radio" id="familienstand1" value="1" name="check_marital_status" '.$checked1.' /><label for="familienstand1">Anzeigen</label>
                <input type="radio" id="familienstand2" value="0" name="check_marital_status" '.$checked2.' /><label for="familienstand2">Aus</label>
            </fieldset>
        </div>
        <div class="ui-widget-content profil-setting">';
            $familienstand_ary = array('ledig', 'verheiratet', 'geschieden', 'verwitwet', 'in fester Beziehung', 'verlobt');
            $site .= '
            <select name="marital_status" style="width:100%;">';
                foreach ($familienstand_ary as $value) {
                    if ($actor->get("marital_status") == $value) {$selected='selected="selected"';} else {$selected='';}								
                    $site .= '<option '.$selected.' value="'.$value.'">'.$value.'</option>';	
                }
                $site .= '
            </select>
        </div>


        <h2 style="margin-top:20px;">Interessen</h2>';

        if ($actor->get("check_interests") == 1) {$checked1='checked="checked"'; $checked2='';} else {$checked1=''; $checked2='checked="checked"';}
        $site .= '
        <div class="ui-widget-header radio_set">
            <fieldset>
                <input type="radio" id="interessen1" value="1" name="check_interests" '.$checked1.' /><label for="interessen1">Anzeigen</label>
                <input type="radio" id="interessen2" value="0" name="check_interests" '.$checked2.' /><label for="interessen2">Aus</label>
            </fieldset>
        </div>
        <div class="ui-widget-content profil-setting">
            <div class="radio_set">';
                $interessen_ary = array('Feste Beziehung', 'One night stand', 'Seitensprung', 'regelm&auml;&szlig;ige Sextreffen', 'SM-Spiele', 'Sexuellen Austausch &uuml;bers Internet');
                if (preg_match('~\|~', $actor->get("interests"))) {
                    $explode = explode('|', $actor->get("interests"));
                } else {
                    $explode = str_replace(', ', ',', $actor->get("interests"));
                    $explode = explode(',', $explode);
                }
                foreach ($interessen_ary as $key => $value) {
                    if(in_array($value, $explode)) {$checked='checked="checked"';} else {$checked='';}								
                    $site .= '<input '.$checked.' type="checkbox" id="interests_'.$key.'" name="interests['.$value.']" value="1" /><label style="text-align:left; width:100%" for="interests_'.$key.'">'.$value.'</label>';	
                }
                $site .= '
            </div>
        </div>


        <h2 style="margin-top:20px;">Ich suche</h2>';

        if ($actor->get("check_looking_for") == 1) {$checked1='checked="checked"'; $checked2='';} else {$checked1=''; $checked2='checked="checked"';}
        $site .= '
        <div class="ui-widget-header radio_set">
            <fieldset>
                <input type="radio" id="ich_suche1" value="1" name="check_looking_for" '.$checked1.' /><label for="ich_suche1">Anzeigen</label>
                <input type="radio" id="ich_suche2" value="0" name="check_looking_for" '.$checked2.' /><label for="ich_suche2">Aus</label>
            </fieldset>
        </div>
        <div class="ui-widget-content profil-setting">
            <div class="radio_set">';
                $looking_for_ary = array('M&auml;nner', 'Frauen', 'Paare (m,w)', 'Paare (m,m)', 'Paare (w,w)');
                if (preg_match('~\|~', $actor->get("looking_for"))) {
                    $explode = explode('|', $actor->get("looking_for"));
                } else {
                    $explode[] = $actor->get("looking_for");
                }
                
                   
                foreach ($looking_for_ary as $key => $value) {
                    if(in_array($value, $explode)) {$checked='checked="checked"';} else {$checked='';}								
                    $site .= '<input '.$checked.' type="checkbox" id="looking_for_'.$key.'" name="looking_for['.$value.']" value="1" /><label style="text-align:left; width:100%" for="looking_for_'.$key.'">'.$value.'</label>';	
                }
                $site .= '
            </div>
        </div>


        <h2 style="margin-top:20px;">Sexuelle Vorlieben</h2>';

        if ($actor->get("check_sexual_preferences") == 1) {$checked1='checked="checked"'; $checked2='';} else {$checked1=''; $checked2='checked="checked"';}
        $site .= '
        <div class="ui-widget-header radio_set">
            <fieldset>
                <input type="radio" id="sexuelle_vorlieben1" value="1" name="check_sexual_preferences" '.$checked1.' /><label for="sexuelle_vorlieben1">Anzeigen</label>
                <input type="radio" id="sexuelle_vorlieben2" value="0" name="check_sexual_preferences" '.$checked2.' /><label for="sexuelle_vorlieben2">Aus</label>
            </fieldset>
        </div>
        <div class="ui-widget-content profil-setting">
            <div class="radio_set">';
                $sexual_preferences_ary = array(
                        'Bl&uuml;mchensex', 'Sexy W&auml;sche', 'Sex im Freien', 'Sex mit Toys', 'Dicke Titten', 'Oralsex', 'Deepthroating (Extremblasen)', 'Bukkake', 'Creampie', 'Analsex', 'Doppelte Penetrationen',
                        'Interracial', 'Gro&szlig;e Schw&auml;nze', 'Sex mit 2 Frauen', 'Sex mit 2 M&auml;nnern', 'Sex-Parties', 'Swinger-Clubs', 'Gruppensex', 'Face-Sitting', 'Rimming (Polecken)', 'Uniformen',
                        'Rollenspiele', 'Voyeur', 'Nylons', 'High-Heels', 'F&uuml;&szlig;e', 'Trampling', 'Lack und Leder', 'S/M', 'Bondage', 'Spanking', 'Bizarr',
                        'Wachs-Spiele', 'Dominas & Sklaven', 'Dominant', 'Devot', 'Doktor-Spiele', 'Natursekt', 'Fisting', 'Dicke', 'Omis', 'Gothic'
                );
                if (preg_match('~\|~', $actor->get("sexual_preferences"))) {
                    $explode = explode('|', $actor->get("sexual_preferences"));
                } else {
                    $explode = str_replace(', ', ',', $actor->get("sexual_preferences"));
                    $explode = explode(',', $explode);
                }
                foreach ($sexual_preferences_ary as $key => $value) {
                    if(in_array($value, $explode)) {$checked='checked="checked"';} else {$checked='';}								
                    $site .= '<input '.$checked.' type="checkbox" id="sexual_preferences_'.$key.'" name="sexual_preferences['.$value.']" value="1" /><label style="text-align:left; width:100%" for="sexual_preferences_'.$key.'">'.$value.'</label>';	
                }
                $site .= '
            </div>
        </div>

        <div style="text-align:right; margin-top:20px;">
            <input class="button" type="submit" name="edit_actor" value="Profildaten speichern" />
        </div>
    </form>
</div>';


?>
