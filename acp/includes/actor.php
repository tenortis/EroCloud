<?php
 
if (!defined('SAFE_INC')) {
    die ("Access denied!");
}

if (!in_array('all', $_SESSION['employee_access_area']) AND !in_array('actors', $_SESSION['employee_access_area'])) {
    header('Location: '.ACP_URL);
    exit;        
}

$actor_id = abs(filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT));

include_once(SOURCEDIR.'/includes/klassen/actor.inc.php');
$actor = new Actor($actor_id);

if ($actor->get("id") == '') {
    header('Location: '.ACP_URL.'/Actors');
    exit;    
}

$merchant = new Merchant($mysql,$actor->get('merchant_id'));

if ($merchant->id() == '') {
    echo 'Dieser Merchant existiert nicht';
}

$is_displayed_as_ary = array(
    'only_chat_actor' => 'Chat & Webcam',
    'only_upload_actor' => 'Filme & Fotoalben',
    'chat_upload_actor' => 'Chat & Webcam + Filme & Fotoalben'
);



if (isset($_GET['del'])) {

    if ($_GET['del'] == 'FSK16' OR $_GET['del'] == 'FSK18') {
        $filename = PROFILE_IMAGE_PATH.'/'.MERCHANT_DEFAULT_DIR.'/'.$actor->get('merchant_id').'/'.$actor_id.'/'.$actor->get('profile_image_'. strtolower($_GET['del']));

        if(@unlink($filename)) {

            p4c_query("UPDATE `actors` SET `profile_image_".strtolower($_GET['del'])."`='' WHERE `id`='".abs($actor_id)."' LIMIT 1;",__FILE__,__LINE__);
            
            $m5d_checksum = actor_checksum($actor_id);

            p4c_query("UPDATE `actors` SET `md5_checksum`='".p4c_escape_string($m5d_checksum)."' WHERE
                `id`='".abs($actor_id)."' LIMIT 1;
            ",__FILE__,__LINE__);
            
            header('Location: '.ACP_URL.'/Actor/'.$actor_id.'?ok=edit_actor');
        }
    }
}

if (isset($_POST['new_merchant'])) {
    $new_mechant_id = abs($_POST['new_merchant_id']);
    
    if ($new_mechant_id > 0) {
    
        $old_merchant_id = $merchant->id();
        
        // Ein Cronjob übernimmt all 5 Minuten die Arbeit. (cronjob/actor_reassign.php)
        $rs_check_actor_reassign = p4c_query("SELECT * FROM `actor_reassign` WHERE `actor_id`='".abs($actor_id)."' LIMIT 1;",__FILE__,__LINE__);
        if (p4c_num_rows($rs_check_actor_reassign) == 0) {
            p4c_query("INSERT INTO `actor_reassign` SET
                `actor_id` = '".abs($actor_id)."',
                `old_merchant_id` = '".abs($old_merchant_id)."',
                `new_merchant_id` = '".abs($new_mechant_id)."',
                `order_from` = '".date("Y-m-d H:i:s")."'                    
            ",__FILE__,__LINE__);
        }
       
        header('Location: '.ACP_URL.'/Actor/'.$actor_id.'?ok=edit_actor');
        exit;
    }
    
} else if (isset($_POST['edit_actor'])) {

    $is_displayed_as = trim($_POST['is_displayed_as']);
            
    if (!array_key_exists($is_displayed_as, $is_displayed_as_ary)) {
        $is_displayed_as = 'only_upload_actor';
    }
    
    $amoredea_provision_obolus_default = '70';
    $amoredea_provision_obolus = $amoredea_provision_obolus_default;
    if (isset($_POST['amoredea_provision_obolus'])) {
        $amoredea_provision_obolus = abs($_POST['amoredea_provision_obolus']);
        if ($amoredea_provision_obolus == 0) {$amoredea_provision_obolus = $amoredea_provision_obolus_default;}            
    }

    $amoredea_provision_content_default = '50';
    $amoredea_provision_content = $amoredea_provision_content_default;
    if (isset($_POST['amoredea_provision_content'])) {
        $amoredea_provision_content = abs($_POST['amoredea_provision_content']);
        if ($amoredea_provision_content == 0) {$amoredea_provision_content = $amoredea_provision_content_default;}            
    }
    
    if (!isset($_POST['categorys']) OR !is_array($_POST['categorys'])) {
        $error = 'W&auml;hle alle Kategorien die zum Film passen.';
    } else {
        $categorys = trim(strip_tags(implode(',', $_POST['categorys'])));
    }
    
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
    
    $erocall_number_de = filter_input(INPUT_POST, 'erocall_number_de', FILTER_SANITIZE_NUMBER_INT);
    $erocall_number_de_ddi = filter_input(INPUT_POST, 'erocall_number_de_ddi', FILTER_SANITIZE_NUMBER_INT);
    $erocall_number_de_dest_landline = filter_input(INPUT_POST, 'erocall_number_de_dest_landline', FILTER_SANITIZE_NUMBER_INT);
    $erocall_number_de_dest_mobile = filter_input(INPUT_POST, 'erocall_number_de_dest_mobile', FILTER_SANITIZE_NUMBER_INT);    
    $erocall_number_de_status = filter_input(INPUT_POST, 'erocall_number_de_status', FILTER_SANITIZE_NUMBER_INT);    
    $erocall_number_de_rate = trim(preg_replace ( '/[^0-9,]/i', '', $_POST['erocall_number_de_rate']));
    
    $search = array('ä','ö','ü','ß');
    $replace = array('ae','oe','ue','ss');
    $post_username = str_replace($search,$replace,trim($_POST['username']));
    $post_username = trim(preg_replace ( '/[^a-zA-Z0-9-_.]/i', '', $post_username));
   
    $rs_check_username_exists = p4c_query("SELECT `username` FROM `actors` WHERE `username`='".p4c_escape_string($post_username)."' AND `id`!='".abs($actor_id)."' LIMIT 1;",__FILE__,__LINE__);
    // Speichern wenn Username nicht existiert
    if (p4c_num_rows($rs_check_username_exists) > 0) {
        $error = 'Der Username <b>'.$post_username.'</b> ist leider schon vergeben.';
    }
    
    $status = p4c_escape_string(filter_input(INPUT_POST, 'actor_status', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW));
    
    if (!isset($error) OR empty($error)) {
    
        p4c_query("UPDATE `actors` SET
            `status`                ='".$status."',
            `admin_infos`           ='".p4c_escape_string(filter_input(INPUT_POST, 'admin_infos', FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_LOW))."',
            `amoredea_provision_content`='".abs($amoredea_provision_content)."',
            `amoredea_provision_obolus`='".abs($amoredea_provision_obolus)."',
            `erocall_number_de`     ='".abs($erocall_number_de)."',
            `erocall_number_de_ddi` ='".p4c_escape_string($erocall_number_de_ddi)."',
            `erocall_number_de_dest_landline` ='".abs($erocall_number_de_dest_landline)."',
            `erocall_number_de_dest_mobile` ='".abs($erocall_number_de_dest_mobile)."',
            `erocall_number_de_rate`='".p4c_escape_string($erocall_number_de_rate)."',
            `erocall_number_de_status` ='".abs($erocall_number_de_status)."',
            `username`              ='".p4c_escape_string($post_username)."',
            `is_displayed_as`       ='". p4c_escape_string($is_displayed_as)."',
            `actor_categories`      ='".p4c_escape_string($categorys)."',
            `obolus_type`            ='".p4c_escape_string(filter_input(INPUT_POST, 'obolus_type', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW))."',
            `check_gender`          ='".abs(filter_input(INPUT_POST, 'check_gender', FILTER_SANITIZE_NUMBER_INT))."',
            `gender`                ='".p4c_escape_string(filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW))."',
            `check_age`             ='".abs(filter_input(INPUT_POST, 'check_age', FILTER_SANITIZE_NUMBER_INT))."',
            `age`                   ='".abs(filter_input(INPUT_POST, 'age', FILTER_SANITIZE_NUMBER_INT))."',
            `check_star_sign`       ='".abs(filter_input(INPUT_POST, 'check_star_sign', FILTER_SANITIZE_NUMBER_INT))."',
            `star_sign`             ='".p4c_escape_string(filter_input(INPUT_POST, 'star_sign', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW))."',
            `check_body_height`     ='".abs(filter_input(INPUT_POST, 'check_body_height', FILTER_SANITIZE_NUMBER_INT))."',
            `body_height`           ='".abs(filter_input(INPUT_POST, 'body_height', FILTER_SANITIZE_NUMBER_INT))."',
            `check_eye_color`       ='".abs(filter_input(INPUT_POST, 'check_eye_color', FILTER_SANITIZE_NUMBER_INT))."',
            `eye_color`             ='".p4c_escape_string(filter_input(INPUT_POST, 'eye_color', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW))."',
            `check_hair_color`      ='".abs(filter_input(INPUT_POST, 'check_hair_color', FILTER_SANITIZE_NUMBER_INT))."',
            `hair_color`            ='".p4c_escape_string(filter_input(INPUT_POST, 'hair_color', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW))."',
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
            `id`='".abs($actor_id)."' LIMIT 1;
        ",__FILE__,__LINE__);

        $m5d_checksum = actor_checksum($actor_id);

        p4c_query("UPDATE `actors` SET `md5_checksum`='".p4c_escape_string($m5d_checksum)."' WHERE
            `id`='".abs($actor_id)."' LIMIT 1;
        ",__FILE__,__LINE__);

        p4c_query("UPDATE `group_actors` SET `is_displayed_as`='".p4c_escape_string($is_displayed_as)."' WHERE `actor_id`='".abs($actor_id)."'",__FILE__,__LINE__);
        
        // Wenn das Profil gelöscht werden soll, auch die Filme und Fotoalben löschen
        if ($status == 'deleted') {
            p4c_query("UPDATE `movies` SET `status`='deleted', `deleted_datetime`='".date("Y-m-d H:i:s")."' WHERE `actor_id`='".abs($actor_id)."';",__FILE__,__LINE__);
            p4c_query("UPDATE `movies_online` SET `status`='deleted', `deleted_datetime`='".date("Y-m-d H:i:s")."' WHERE `actor_id`='".abs($actor_id)."';",__FILE__,__LINE__);
            
            p4c_query("UPDATE `photo_albums` SET `status`='deleted', `deleted_datetime`='".date("Y-m-d H:i:s")."' WHERE `actor_id`='".abs($actor_id)."';",__FILE__,__LINE__);
            p4c_query("UPDATE `photo_albums_online` SET `status`='deleted', `deleted_datetime`='".date("Y-m-d H:i:s")."' WHERE `actor_id`='".abs($actor_id)."';",__FILE__,__LINE__);
        }
        
        header('Location: '.ACP_URL.'/Actor/'.$actor_id.'?ok=edit_actor');
        exit;
    }
}

$site .= '
<link rel="stylesheet" type="text/css" href="'.MCP_URL.'/css/uploadfile.css" />
<script type="text/javascript" src="'.MCP_URL.'/js/jquery.form.js"></script>
<script type="text/javascript" src="'.MCP_URL.'/js/jquery.uploadfile.min.js"></script>

<link rel="stylesheet" href="'.MCP_URL.'/fw/colorbox/colorbox.css" />
<script type="text/javascript" src="'.MCP_URL.'/fw/colorbox/jquery.colorbox.js"></script>

<script type="text/javascript">
// <![CDATA[
    jQuery(document).ready(function() {
        
        jQuery(document).ready(function(){
            jQuery(".zoom_goup").colorbox({photo:true, rel:"zoom_goup", maxWidth:"100%", width:"auto", maxHeight:"80%"});
        })
    
        jQuery("#play_cam_sound").click(function() {
            var sound = jQuery("#cam_sound").val();

            jQuery("#new_webcam_user_sound_player").html(
                "<audio>"+
                    "<source src=\"'.MCP_URL.'/Messenger/Sound/"+sound+".ogg\" type=\"audio/ogg\">"+
                    "<source src=\"'.MCP_URL.'/Messenger/Sound/"+sound+".mp3\" type=\"audio/mpeg\">"+
                "</audio>"
            );

            jQuery("#new_webcam_user_sound_player audio").trigger("play");
        });

        jQuery(".radio_set fieldset").buttonset();

        jQuery("#site_actor input[type=radio], #site_actor input[type=checkbox]").checkboxradio({
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

        jQuery(".del_img").click(function(){
            var img = jQuery(this).attr("data-fsk");

            if (confirm(img+" Profilbild wirklich loeschen?")) {
                window.location.href="'.ACP_URL.'/Actor/'.$actor_id.'&del="+img;
            }
            return false;
        })
        
        jQuery("[name=actor_status]").change(function() {
            var actor_status = jQuery(this).val();
            jQuery("#alert_status").hide();
            if (actor_status == "deleted") {
                jQuery("#alert_status").show();
                jQuery("#alert_status_mess").html("ACHTUNG!<br />Auf den Seiten werden alle Filme und Alben gel&ouml;scht die noch nie gekauft wurden. Bereits gekaufte Alben oder Filme werden nicht gel&ouml;scht und stehen dem Kunden weiterhin zur Verf&uuml;gung.");
            }
        })

    })
   
// ]]>
</script>


<div id="site_actor" style="width:600px; margin-bottom:50px; display: inline-block;">
    <form action="'.ACP_URL.'/Actor/'.$actor_id.'" method="post">';
        if (isset($error) AND !empty($error)) {
            $site .= '<div class="ui-state-error" style="padding:10px; margin-bottom:10px;">'.$error.'</div>';
        }

        $site .= '
        <input id="username" type="text" value="'.$actor->get("username").'" name="username" />';

        $avatar_fsk16 = API_URL.'/ProfilePicture/'.$actor->get("profile_image_fsk16");
        $avatar_fsk18 = API_URL.'/ProfilePicture/'.$actor->get("profile_image_fsk18");

        $site .= '
        <ul id="actors">
            <li>
                <div class="avatar"><img class="zoom_goup" href="'.$avatar_fsk16.'" src="'.$avatar_fsk16.'" alt="" /></div>
                <div class="fsk">FSK16 (unter 18 Jahre)<br /><a class="del_img" data-fsk="FSK16" href="">l&ouml;schen</a></div>
            </li>
            <li>
                <div class="avatar"><img class="zoom_goup" href="'.$avatar_fsk18.'" src="'.$avatar_fsk18.'" alt="" /></div>
                <div class="fsk">FSK18 (ab 18 Jahre)<br /><a class="del_img" data-fsk="FSK18" href="">l&ouml;schen</a></div>
            </li>
        </ul>';

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

        if ($actor->get("erocall_number_de_status") == 1) {$checked1='checked="checked"'; $checked2='';} else {$checked1=''; $checked2='checked="checked"';}
        if ($actor->get("erocall_number_de_rate") == '2,99') {$select_rate='selected="selected"';} else {$select_rate='';}
        $site .= '
        <div class="ui-widget-header radio_set">
            <span>Rufnummer - DE</span>
            <fieldset>
                <input type="radio" id="erocall_number_de_status1" value="1" name="erocall_number_de_status" '.$checked1.' /><label for="erocall_number_de_status1">Aktiv</label>
                <input type="radio" id="erocall_number_de_status2" value="0" name="erocall_number_de_status" '.$checked2.' /><label for="erocall_number_de_status2">Inaktiv</label>
            </fieldset>
        </div>
        <div class="ui-widget-content" style="padding:10px; margin-bottom:15px;">
            <div class="edit_content" style="margin-bottom:8px; font-size:16px;">09005-<input style="width: 90px; text-align: center;" maxlength="10" size="10" type="text" name="erocall_number_de" value="'.$actor->get('erocall_number_de').'" />-<input style="width: 40px; text-align: center;" maxlength="4" size="4" type="text" name="erocall_number_de_ddi" value="'.$actor->get('erocall_number_de_ddi').'" /></div>
            <div style="display: inline-block; width:50%;">
                Zielrufnummer-Festnetz:<br />0049<input style="width: 80%;" type="text" name="erocall_number_de_dest_landline" value="'.$actor->get('erocall_number_de_dest_landline').'" />
            </div>
            <div style="display: inline-block; width:48%;">
                Zielrufnummer-Mobil:<br />0049<input style="width: 80%" type="text" name="erocall_number_de_dest_mobile" value="'.$actor->get('erocall_number_de_dest_mobile').'" />
            </div>

            <div style="display: inline-block; width:50%; margin-top:10px;">
                Anrufer-Tarif pro Minute: <select style="width: 120px;" name="erocall_number_de_rate">
                    <option value="1,99">1,99 EUR</option>
                    <option '.$select_rate.' value="2,99">2,99 EUR</option>
                </select>
            </div>
        </div>';


        if ($actor->get("pn_free_if_webcam") === '1') {$pn_free_if_webcam = 'Ja';} else {$pn_free_if_webcam = 'Nein';}
        if ($actor->get("usercam_if_amacam") === '1') {$usercam_if_amacam = 'Immer';} else {$usercam_if_amacam = 'Nur wenn er meine Webcam sieht';}
        if ($actor->get("cam_new_user_sound") === 'new_user2') {$selected_cam_new_user_sound = 'selected';} else {$selected_cam_new_user_sound = '';}

        $site .= '
        <div id="messenger_settings">
            <div class="ui-widget-header" style="padding:5px; 10px; border-bottom:none;">Messenger - Einstellungen</div>
            <div class="ui-widget-content" style="padding:10px;">
                <div>Nachrichtenpreis f&uuml;r neue User: <input type="text" name="newuser_pn_amount" readonly maxlength="3" value="'.$actor->get("pn_amount").'"> Coins.</div>
                <div>Nachrichten kostenlos wenn User meine Webcam sieht: <b>'.$pn_free_if_webcam.'</b></div>
            </div>
        </div>

        <div id="webcam_settings">
            <div class="ui-widget-header" style="padding:5px; 10px; border-bottom:none;">Webcam - Einstellungen</div>
            <div class="ui-widget-content" style="padding:10px;">
                <div style="padding-bottom:2px;">Webcam-Preis f&uuml;r neue User: <input type="text" name="newuser_cam_amount" maxlength="3" readonly value="'.$actor->get("cam_amount").'"> Coins pro Minute. </div>
                <div style="padding-bottom:2px;">Wann darf der User sein Webcam senden? - <b>'.$usercam_if_amacam.'</b></div>
                <div>Dieser Sound ert&ouml;hnt wenn der User deine Cam sehen will: <input type="hidden" value="'.$actor->get("cam_new_user_sound").'" id="cam_sound" />
                    <i id="play_cam_sound" class="material-symbols-outlined">play_circle</i>
                    <div id="new_webcam_user_sound_player"></div> 
                </div>
            </div>
        </div>
        ';
        
        $site .= '
        <div class="ui-widget-header" style="padding:5px; 10px; border-bottom:none;">Soll der User dir "Trinkgeld senden" (Porno/Erotik) oder "Tribut zollen" (Fetisch/Domina)</div>
        <div class="ui-widget-content profil-setting" style="margin-bottom:15px;">';
            $obolus_type_ary = array('tribute' => 'Tribut zollen', 'tip' => 'Trinkgeld senden');
            $site .= '<select name="obolus_type" style="width:100%;">';
                foreach ($obolus_type_ary as $type => $value) {
                    if ($actor->get("obolus_type") == $type) {$selected='selected="selected"';} else {$selected='';}								
                    $site .= '<option '.$selected.' value="'.$type.'">'.utf8_encode($value).'</option>';	
                }
            $site .= '
            </select>
        </div>';

        if ($actor->get("check_about_me") == 1) {$checked1='checked="checked"'; $checked2='';} else {$checked1=''; $checked2='checked="checked"';}
        $site .= '
        <div class="ui-widget-header radio_set">
            <span>&Uuml;ber mich</span>
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
            $hair_color_ary = array('weiß', 'blond', 'dunkelblond', 'rot', 'hellbraun', 'dunkelbraun', 'schwarz');
            $site .= '<select name="hair_color" style="width:100%;">';
                foreach ($hair_color_ary as $value) {
                    if ($actor->get("hair_color") == $value) {$selected='selected="selected"';} else {$selected='';}
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
            $eye_color_ary = array('Hellblau', 'Blaugrau', 'Dunkelblau', 'Hellbraun', 'Dunkelbraun', 'Hellgrün', 'Grüngrau', 'Dunkelgrün');
            $site .= '<select name="eye_color" style="width:100%;">';
                foreach ($eye_color_ary as $value) {
                    if ($actor->get("eye_color") == $value) {$selected='selected="selected"';} else {$selected='';}
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
    </div>

    <div style="width:600px; margin-left:20px; display: inline-block; vertical-align: top;">';
        $actor_categories_ary = explode(',',$actor->get('actor_categories'));
        $site .= '  

        <div class="ui-widget-header" style="padding:5px 10px;">Profil-Status</div>
        <div class="ui-widget-content radioset" style="font-size:12px; padding:10px; border-top:none; margin-bottom:10px;">';

            // inactiv  = Profil nicht sichtbar. Bereits gekaufter Content weiterhin online.
            // gesperrt = Profil nicht sichtbar. Bereits gekaufter Content weiterhin online.

            if ($actor->get("status") == 'active') {
                $staus1 = 'checked="checked"';
                $staus2 = '';
                $staus3 = '';
                $staus4 = '';
            } else if ($actor->get("status") == 'inactive') {
                $staus1 = '';
                $staus2 = 'checked="checked"';
                $staus3 = '';
                $staus4 = '';
            } else if ($actor->get("status") == 'blocked') {
                $staus1 = '';
                $staus2 = '';
                $staus3 = 'checked="checked"';
                $staus4 = '';
            } else if ($actor->get("status") == 'deleted') {
                $staus1 = '';
                $staus2 = '';
                $staus3 = '';
                $staus4 = 'checked="checked"';
            }
            $site .= '
            <label for="status1">Aktiv</label><input '.$staus1.' class="radio" type="radio" name="actor_status" value="active" id="status1" /> 
            
            <label for="status2">Inaktiv</label><input '.$staus2.' class="radio" type="radio" name="actor_status" value="inactive" id="status2" />
            <label for="status3">Gesperrt</label><input '.$staus3.' class="radio" type="radio" name="actor_status" value="blocked" id="status3" /> 
            <label for="status4">Gel&ouml;scht</label><input '.$staus4.' class="radio" type="radio" name="actor_status" value="deleted" id="status4" /> 
            
            
            <div class="ui-state-error" id="alert_status" style="display:none; padding:10px; margin-top:5px;">
                <span id="alert_status_mess"></span>
            </div>
        </div>

        <div class="ui-widget-header" style="padding:5px 10px;">Merchant-Infos</div>
        <div class="ui-widget-content radioset" style="font-size:12px; padding:10px; border-top:none; margin-bottom:10px;">
            Actor-ID: '.$actor_id.'<br />
            Merchant-ID: <a href="'.ACP_URL.'/Haendler/'.$merchant->id().'">'.$merchant->id().'</a><br />
            P4C-Partner-ID: <a href="'.ACP_URL.'/Haendler/'.$merchant->id().'">'.$merchant->partner_id().'</a><br />
            P4C-Username: <a href="'.ACP_URL.'/Haendler/'.$merchant->id().'">'.$merchant->username('aes_decrypt').'</a>
        </div>

        <div class="ui-widget-header" style="padding:5px 10px;">Admin-Infos zum Profil</div>
        <div class="ui-widget-content" style="padding:10px; border-top:none;">
            <textarea style="min-height:100px; width:100%; width: -webkit-fill-available; width: -moz-available;" name="admin_infos">'.$actor->get("admin_infos").'</textarea>
        </div>

        <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Einstellungen f&uuml;r amoredea.com</div>
        <div class="ui-widget-content edit_content" style="border-bottom:none; margin-bottom:8px; border-top:none;">
            <div style="padding:5px;">
                Provision f&uuml;r Content (Filme und Fotoalben): 
                <select name="amoredea_provision_content" style="width:150px; padding:0; font-size:12px;">';
                for($i=40;$i<=80;$i++) {
                    if ($actor->get('amoredea_provision_content') == $i) {$selected = 'selected="selected"';} else {$selected = '';}
                    $site .= '<option '.$selected.' value="'.$i.'" >'.$i.'%</option>';
                }
                $site .=
                '</select> (Standard: 50%)
            </div>
            <div style="padding:5px; border-bottom:1px solid;">
                Provision f&uuml;r Obolus: 
                <select name="amoredea_provision_obolus" style="width:150px; padding:0; font-size:12px;">';
                for($i=40;$i<=80;$i++) {
                    if ($actor->get('amoredea_provision_obolus') == $i) {$selected = 'selected="selected"';} else {$selected = '';}
                    $site .= '<option '.$selected.' value="'.$i.'" >'.$i.'%</option>';
                }
                $site .=
                '</select> (Standard: 70%)
            </div>
        </div>



        <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Profil einordnen</div>
        <div class="ui-widget-content edit_content" style="border-bottom:none; margin-bottom:8px; border-top:none;">
            <div style="padding:5px; border-bottom:1px solid;">
                F&uuml;r was soll das Profil genutzt werden?
                <select name="is_displayed_as" style="width:150px; padding:0; font-size:12px;">';
                foreach($is_displayed_as_ary as $key => $value) {
                    if ($actor->get("is_displayed_as") == $key) {$selected = 'selected';} else {$selected='';}
                    $site .= '<option '.$selected.' value="'.$key.'">'.$value.'</option>';
                }
                $site .= 
                '</select>
            </div>
        </div>

        <div class="ui-widget-header" style="padding:10px; font-size:20px; margin:20px 0px 10px 0px;">Darsteller kategorisieren</div>

        <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Hauptkategorie</div>
        <div class="ui-widget-content edit_content" style="border-bottom:none; margin-bottom:10px; border-top:none;">
            <div style="padding:5px; border-bottom:1px solid;">
                W&auml;hle die Kategorie, die auf den Darsteller zutrifft: 
                <select name="categorys[]" style="width:150px; padding:0; font-size:12px;">';
                $rs_actor_categories = p4c_query("SELECT * FROM `actor_categories` WHERE `category_group`='main' ORDER BY `name_id` DESC;",__FILE__,__LINE__);
                while($category_obj = p4c_fetch_object($rs_actor_categories)) {
                    if (in_array($category_obj->name_id, $actor_categories_ary)) {$selected = 'selected';} else {$selected='';}
                    $site .= '<option '.$selected.' value="'.$category_obj->name_id.'">'.$category_obj->de_name_value.'</option>';
                }
                $site .= 
                '</select>
            </div>
        </div>

        <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">W&auml;hle alle Kategorien die zum Darsteller passen</div>
        <div class="ui-widget-content edit_content" style="border-bottom:none; margin-bottom:8px; border-top:none;">
            <div style="padding:5px; border-bottom:1px solid; font-weight:bold;">
                <div style="width:100px; display:inline-block;">Gewicht</div>
                <select name="categorys[]" style="width:150px; padding:0; font-size:12px;">';
                $rs_actor_categories = p4c_query("SELECT * FROM `actor_categories` WHERE `category_group`='weight' ORDER BY `name_id` ASC;",__FILE__,__LINE__);
                while($category_obj = p4c_fetch_object($rs_actor_categories)) {
                    if (in_array($category_obj->name_id, $actor_categories_ary)) {$selected = 'selected';} else {$selected='';}
                    $site .= '<option '.$selected.' value="'.$category_obj->name_id.'">'.$category_obj->de_name_value.'</option>';
                }
                $site .= 
                '</select>
            </div>';

            $site .= '
            <div style="padding:5px; border-bottom:1px solid; font-weight:bold;">
                <div style="width:100px; display:inline-block;">Hauttyp</div>
                <select name="categorys[]" style="width:150px; padding:0; font-size:12px;">';
                $rs_actor_categories = p4c_query("SELECT * FROM `actor_categories` WHERE `category_group`='skin_color' ORDER BY `name_id` ASC;",__FILE__,__LINE__);
                while($category_obj = p4c_fetch_object($rs_actor_categories)) {
                    if (in_array($category_obj->name_id, $actor_categories_ary)) {$selected = 'selected';} else {$selected='';}
                    $site .= '<option '.$selected.' value="'.$category_obj->name_id.'">'.$category_obj->de_name_value.'</option>';
                }
                $site .= 
                '</select>
            </div>';

            $site .= '
            <div style="padding:5px; border-bottom:1px solid; font-weight:bold;">
                <div style="width:100px; display:inline-block;">Haarfarbe</div>
                <select name="categorys[]" style="width:150px; padding:0; font-size:12px;">';
                $rs_actor_categories = p4c_query("SELECT * FROM `actor_categories` WHERE `category_group`='hair_color' ORDER BY `name_id` ASC;",__FILE__,__LINE__);
                while($category_obj = p4c_fetch_object($rs_actor_categories)) {
                    if (in_array($category_obj->name_id, $actor_categories_ary)) {$selected = 'selected';} else {$selected='';}
                    $site .= '<option '.$selected.' value="'.$category_obj->name_id.'">'.$category_obj->de_name_value.'</option>';
                }
                $site .= 
                '</select>
            </div>';

            $site .= '
            <div style="padding:5px; border-bottom:1px solid; font-weight:bold;">
                <div style="width:100px; display:inline-block;">Geschlecht</div>
                <select name="categorys[]" style="width:150px; padding:0; font-size:12px;">';
                $rs_actor_categories = p4c_query("SELECT * FROM `actor_categories` WHERE `category_group`='gender' ORDER BY `name_id` ASC;",__FILE__,__LINE__);
                while($category_obj = p4c_fetch_object($rs_actor_categories)) {
                    if (in_array($category_obj->name_id, $actor_categories_ary)) {$selected = 'selected';} else {$selected='';}
                    $site .= '<option '.$selected.' value="'.$category_obj->name_id.'">'.$category_obj->de_name_value.'</option>';
                }
                $site .= 
                '</select>
            </div>';


            $site .= ' 
            <div style="padding:5px; border-bottom:1px solid; font-weight:bold;">Alter</div>';
            $rs_actor_categories = p4c_query("SELECT * FROM `actor_categories` WHERE `category_group`='age' ORDER BY `name_id` ASC;",__FILE__,__LINE__);
            while($category_obj = p4c_fetch_object($rs_actor_categories)) {
                if (in_array($category_obj->name_id, $actor_categories_ary)) {$checked_cat_slave = 'checked="checked"';} else {$checked_cat_slave='';}

                $site .= '
                <div style="padding:5px 5px 5px 20px; border-bottom:1px solid;">
                    <div style="font-weight:bold;">
                        <label for="'.$category_obj->name_id.'"><input type="checkbox" '.$checked_cat_slave.' id="'.$category_obj->name_id.'" name="categorys[]" value="'.$category_obj->name_id.'" /> '.$category_obj->de_name_value.'</label>
                    </div>
                    <div style="padding-left:20px;">
                        '.$category_obj->de_name_text.'
                    </div>
                </div>';
            }

            $site .= '
            <div style="padding:5px; border-bottom:1px solid; font-weight:bold;">besondere K&ouml;rpereigenschaften</div>';
            $rs_actor_categories = p4c_query("SELECT * FROM `actor_categories` WHERE `category_group`='body' ORDER BY `name_id` ASC;",__FILE__,__LINE__);
            while($category_obj = p4c_fetch_object($rs_actor_categories)) {
                if (in_array($category_obj->name_id, $actor_categories_ary)) {$checked_cat_slave = 'checked="checked"';} else {$checked_cat_slave='';}

                $site .= '
                <div style="padding:5px 5px 5px 20px; border-bottom:1px solid;">
                    <div style="font-weight:bold;">
                        <label for="'.$category_obj->name_id.'"><input type="checkbox" '.$checked_cat_slave.' id="'.$category_obj->name_id.'" name="categorys[]" value="'.$category_obj->name_id.'" /> '.$category_obj->de_name_value.'</label>
                    </div>
                    <div style="padding-left:20px;">
                        '.$category_obj->de_name_text.'
                    </div>
                </div>';
            }
            $site .= '

        </div>

        <div style="text-align:right; margin-top:20px;">
            <input class="button" type="submit" name="edit_actor" value="Profildaten speichern" />
        </div>
    </form>
    ';
            
    $rs_merchants = p4c_query("SELECT `id`, `partner_id`, AES_DECRYPT(`username`, '".AES_KEY."') AS `username` FROM `merchants` ORDER BY `username` ASC;",__FILE__,__LINE__);
    $site .= '
    <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Darsteller einem neuen Partner zuordnen</div>
    <div class="ui-widget-content edit_content" style="margin-bottom:8px; border-top:none; padding:10px;">
        ';
        $rs_check_actor_reassign = p4c_query("SELECT * FROM `actor_reassign` WHERE `actor_id`='".abs($actor_id)."' AND `files_mapping_completed`='0000-00-00 00:00:00' LIMIT 1;",__FILE__,__LINE__);
        if (p4c_num_rows($rs_check_actor_reassign) == 1) {
            $actor_reassign_obj = p4c_fetch_object($rs_check_actor_reassign);
            $site .= '<div class="ui-state-error" style="padding:5px; margin-bottom:10px;">Dieser Darsteller wird aktuell einem neuen Besitzer zugeordnet (an MID: '.$actor_reassign_obj->new_merchant_id.') .</div>';
        } else {
            $site .= '
            <form action="'.ACP_URL.'/Actor/'.$actor_id.'" method="post">
                <div class="info_box" style="margin-bottom:10px;">Wenn Sie hier eine &Auml;nderung vornhemen, wird der Beistzer dieses Profils ge&auml;ndert.</div>
                Username / Partner-ID<br />
                <select name="new_merchant_id" style="width:250px; padding:0; font-size:12px;">
                    <option>ACHTUNG! Sie &auml;ndern den Besitzer!</option>
                    ';
                    while($merchant_obj = p4c_fetch_object($rs_merchants)) {
                        $site .= '<option value="'.$merchant_obj->id.'">'.$merchant_obj->username.' - '.$merchant_obj->partner_id.'</option>';
                    }
                    $site .= ' 
                </select> <input type="submit" value="Speichern" name="new_merchant" />
            </form>
            ';
        }
        
        $rs_check_actors_reassign = p4c_query("SELECT * FROM `actor_reassign` WHERE `actor_id`='".abs($actor_id)."' AND `files_mapping_completed`!='0000-00-00 00:00:00';",__FILE__,__LINE__);
        if (p4c_num_rows($rs_check_actors_reassign) > 0) {
            $site.= '<div style="margin-top:15px;">';
                while($actor_reassign_obj = p4c_fetch_object($rs_check_actors_reassign)) {
                    $site .= '<div style="margin-bottom:3px;">'.$actor_reassign_obj->files_mapping_completed.' - verschoben von MID <a href="'.ACP_URL.'/Haendler/'.$actor_reassign_obj->old_merchant_id.'">'.$actor_reassign_obj->old_merchant_id.'</a> zu <a href="'.ACP_URL.'/Haendler/'.$actor_reassign_obj->new_merchant_id.'">'.$actor_reassign_obj->new_merchant_id.'</a></div>';
                }
            $site.= '</div>';
        }
        $site .= '
    </div>

</div>
';


?>
