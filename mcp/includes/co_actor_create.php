<?php
 
if (!defined('SAFE_INC')) {
    die ("Access denied!");
}

if ($count_actors >= $merchant->minimum_number_actor_profiles()) {
    header('Location: '.MCP_URL.'/Actors');    
    exit;
}

if (isset($_POST['new_actor'])) {

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
    
    $search = array('ä','ö','ü','ß');
    $replace = array('ae','oe','ue','ss');
    $post_username = str_replace($search,$replace,trim($_POST['username']));
    $post_username = preg_replace ( '/[^a-zA-Z0-9-_.]/i', '', $post_username);
   
    if (trim($post_username) == '') {
        $error = 'Bitte gib einen Usernamen an.';                
    } else {
        $rs_actor = p4c_query("SELECT `username` FROM `actors` WHERE `username`='".p4c_escape_string($post_username)."';",__FILE__,__LINE__);
        if (p4c_num_rows($rs_actor) == 1) {
            $error = 'Der Username ist leider schon vergeben.';
        }
    }

    if (!isset($error) OR empty($error)) {   
        
        p4c_query("INSERT `actors` SET
            `username`              ='".p4c_escape_string($post_username)."',
            `status`                ='inactive',
            `created_datetime`      ='".date("Y-m-d H:i:s")."',
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
            `about_me`              ='".p4c_escape_string(filter_input(INPUT_POST, 'about_me', FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_LOW))."',
            `merchant_id`='".abs($_SESSION['merchant_id'])."'
        ",__FILE__,__LINE__);

        $actor_id = p4c_insert_id();

        //Prüfen ob schon eine Gruppe für die Profile erstellt wurde. Wenn nicht, dann jetzt anlegen.
        $rs_groups = p4c_query("SELECT * FROM `groups` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' ORDER BY `id` DESC;",__FILE__,__LINE__);
        if (p4c_num_rows($rs_groups) > 0) {
            $group_obj = p4c_fetch_object($rs_groups);

            $rs_check_group = p4c_query("SELECT * FROM `group_actors` WHERE `actor_id`='".abs($actor_id)."' LIMIT 1;",__FILE__,__LINE__);
            if (p4c_num_rows($rs_check_group) === 0) {
                p4c_query("INSERT INTO `group_actors` SET
                    `merchant_id`   = '".abs($_SESSION['merchant_id'])."',
                    `group_id`      = '".abs($group_obj->id)."',
                    `actor_id`      = '".abs($actor_id)."';",__FILE__,__LINE__);
            }                
        }

        header('Location: '.MCP_URL.'/Actor/'.$actor_id.'?ok=edit_actor');
        exit;
    }
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
            value: 25,
            slide: function( event, ui ) {
                jQuery( "#age" ).val( ui.value );
            }
        });
        jQuery("#age").val(jQuery("#slider-age").slider("value"));
        
        jQuery( "#slider-body_height" ).slider({
            range: "max",
            min: 100,
            max: 230,
            value: 165,
            slide: function( event, ui ) {
                jQuery( "#body_height" ).val( ui.value );
            }
        });
        jQuery("#body_height").val(jQuery("#slider-body_height").slider("value"));

        jQuery( "#slider-body_weight" ).slider({
            range: "max",
            min: 45,
            max: 150,
            value: 65,
            slide: function( event, ui ) {
                jQuery( "#body_weight" ).val( ui.value );
            }
        });
        jQuery("#body_weight").val(jQuery("#slider-body_weight").slider("value"));


    })
   
// ]]>
</script>

<div id="site_actor" style="width:600px; margin-bottom:50px;">
    <center><h1 class="h4">Drehpartner anlegen</h1></center>

    ';
    if (isset($error) AND !empty($error)) {
        $site .= '<div class="ui-state-error" style="padding:10px; margin-bottom:20px;">'.$error.'</div>';
    }
    $site .= '
        
    <div id="add_profile_info" style="margin-bottom:30px;">

    </div>

    <form method="post">

        <h2>Nickname</h2>
        <div class="ui-widget-content profil-setting">
            <input style="padding:5px; width: -webkit-fill-available; width: -moz-available;" type="text" value="" id="username" name="username" /><br />
            <span style="font-size:11px;">Gib deinen Drehpartner einen Nicknamen um ihn sp&auml;ter einfacher zuzuordnen. Der Nickname ist nur f&uuml;r dich sichtbar.</span>
        </div>

        <h2 style="margin-top:20px;">Vorname</h2>
        <div class="ui-widget-content profil-setting">
            <input style="padding:5px; width: -webkit-fill-available; width: -moz-available;" type="text" value="" id="username" name="username" /><br />
        </div>

        <h2 style="margin-top:20px;">Nachname</h2>
        <div class="ui-widget-content profil-setting">
            <input style="padding:5px; width: -webkit-fill-available; width: -moz-available;" type="text" value="" id="username" name="username" /><br />
        </div>

        <h2 style="margin-top:20px;">Stra&szlig;e</h2>
        <div class="ui-widget-content profil-setting">
            <input style="padding:5px; width: -webkit-fill-available; width: -moz-available;" type="text" value="" id="username" name="username" /><br />
        </div>

        <h2 style="margin-top:20px;">Postzleitzahl</h2>
        <div class="ui-widget-content profil-setting">
            <input style="padding:5px; width: -webkit-fill-available; width: -moz-available;" type="text" value="" id="username" name="username" /><br />
        </div>

        <h2 style="margin-top:20px;">Wohnort</h2>
        <div class="ui-widget-content profil-setting">
            <input style="padding:5px; width: -webkit-fill-available; width: -moz-available;" type="text" value="" id="username" name="username" /><br />
        </div>

        <h2 style="margin-top:20px;">Land</h2>
        <div class="ui-widget-content profil-setting">
            <input style="padding:5px; width: -webkit-fill-available; width: -moz-available;" type="text" value="" id="username" name="username" /><br />
        </div>

        <h2 style="margin-top:20px;">sonstige Infos</h2>
        <div class="ui-widget-content profil-setting">    
            <textarea name="about_me" style="padding:5px;"></textarea>
            <span style="font-size:11px;">Hier kannst du weitere Informationen zu diesem Drehpartner hinzuf&uuml;gen. Diese Informationen sind nur f&uuml;r dich sichtbar.</span>
        </div>

        <div style="text-align:right; margin-top:20px;">
            <input class="button" type="submit" name="new_actor" value="Profil erstellen" />
        </div>
    </form>
</div>';


?>
