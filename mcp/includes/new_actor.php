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
        

        jQuery("#username").keyup(function(e) {
            var username = jQuery(this).val(); 

            jQuery.ajax({
                url: "'.MCP_URL.'/Messenger/Ajax/check_username.php",
                data: "username="+username,
                method: "POST",
                dataType: "text",
                success: function(result) {
                    console.log(result);
                    if (result === "ok") {
                        jQuery("#username_ok").show();
                        jQuery("#username_error").hide();
                    } else {
                        jQuery("#username_ok").hide();
                        jQuery("#username_error").show();
                    }
                }
            });
        })
        
        jQuery("#add_profile_button").click(function(){
            jQuery(\'#add_profile\').show();
            jQuery(\'#add_profile_info\').hide();
        })


    })
   
// ]]>
</script>

<div id="site_actor" style="width:600px; margin-bottom:50px;">
    <center><h1 class="h4">Profil erstellen</h1></center>

    ';
    if (isset($error) AND !empty($error)) {
        $site .= '<div class="ui-state-error" style="padding:10px; margin-bottom:20px;">'.$error.'</div>';
    }
    $site .= '
        
    <div id="add_profile_info">
        Wenn du Filme und Fotoalben von dir verkaufen willst oder du mit Usern chatten und deine Webcam senden m&ouml;chtest
        um damit Geld zu verdienen, dann musst du dir hier als erstes ein Darsteller-Profil erstellen.<br />
        <br />
        Klicke auf: <a id="add_profile_button" href="javascript:;">Jetzt Profil anlegen</a>.
    </div>

    <div id="add_profile" style="display:none;">

        <form method="post">

            <h2>Dein Username</h2>

            <div class="ui-widget-content profil-setting">
                <input style="padding:5px; width: -webkit-fill-available; width: -moz-available;" type="text" value="" id="username" name="username" placeholder="z.B.: Sexy-Lady" /><br />
                <span style="font-size:11px;">Wie sollen die User dich nennen?</span>
                <div id="username_ok" style="display:none; padding:5px;" class="ui-state-highlight">Der Username ist noch frei.</div>
                <div id="username_error" style="display:none; padding:5px;" class="ui-state-error">Der Username ist bereits vergeben.</div>
            </div>

            <h2 style="margin-top:20px;">&Uuml;ber mich</h2>

            <div class="ui-widget-header radio_set">
                <fieldset>
                    <input type="radio" id="ueber_mich1" value="1" name="check_about_me" checked /><label for="ueber_mich1">Anzeigen</label>
                    <input type="radio" id="ueber_mich2" value="0" name="check_about_me" /><label for="ueber_mich2">Aus</label>
                </fieldset>
            </div>
            <div class="ui-widget-content profil-setting">    
                <textarea name="about_me" placeholder="Beschreibe dich hier so gut wie m&ouml;glich." style="padding:5px;"></textarea>
            </div>

            <h2 style="margin-top:20px;">K&ouml;rper und Aussehen</h2>

            <div class="ui-widget-header radio_set">
                <span>Alter</span>
                <fieldset>
                    <input type="radio" id="alter1" value="1" name="check_age" checked /><label for="alter1">Anzeigen</label>
                    <input type="radio" id="alter2" value="0" name="check_age" /><label for="alter2">Aus</label>
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
            </div>

            <div class="ui-widget-header radio_set">
                <span>Sternzeichen</span>
                <fieldset>
                    <input type="radio" id="sternzeichen1" value="1" name="check_star_sign" checked /><label for="sternzeichen1">Anzeigen</label>
                    <input type="radio" id="sternzeichen2" value="0" name="check_star_sign" /><label for="sternzeichen2">Aus</label>
                </fieldset>
            </div>
            <div class="ui-widget-content profil-setting">';
                $sternzeichen_ary = array('Steinbock', 'Wassermann', 'Fische', 'Widder', 'Stier', 'Zwillinge', 'Krebs', 'Löwe', 'Jungfrau', 'Waage', 'Skorpion', 'Schütze', );
                $site .= '<select name="star_sign" style="width:100%;">';
                    foreach ($sternzeichen_ary as $value) {
                        $site .= '<option value="'.$value.'">'.utf8_encode($value).'</option>';	
                    }
                $site .= '
                </select>
            </div>

            <div class="ui-widget-header radio_set">
                <span>K&ouml;rpergr&ouml;&szlig;e</span>
                <fieldset>
                    <input type="radio" id="groesse1" value="1" name="check_body_height" checked /><label for="groesse1">Anzeigen</label>
                    <input type="radio" id="groesse2" value="0" name="check_body_height" /><label for="groesse2">Aus</label>
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
            </div>

            <div class="ui-widget-header radio_set">
                <span>K&ouml;rpergewicht</span>
                <fieldset>
                    <input type="radio" id="gewicht1" value="1" name="check_body_weight" checked /><label for="gewicht1">Anzeigen</label>
                    <input type="radio" id="gewicht2" value="0" name="check_body_weight" /><label for="gewicht2">Aus</label>
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
            </div>

            <div class="ui-widget-header radio_set">
                <span>Geschlecht</span>
                <fieldset>
                    <input type="radio" id="geschlecht1" value="1" name="check_gender" checked /><label for="geschlecht1">Anzeigen</label>
                    <input type="radio" id="geschlecht2" value="0" name="check_gender" /><label for="geschlecht2">Aus</label>
                </fieldset>
            </div>
            <div class="ui-widget-content profil-setting">';
                $gender_ary = array('f' => 'Weiblich', 'm'=> 'M&auml;nnlich', 't' => 'Transgender');
                $site .= '
                <select name="gender" style="width:100%;">';
                    foreach ($gender_ary as $key => $value) {
                        $site .= '<option value="'.$key.'">'.$value.'</option>';	
                    }
                    $site .= '
                </select>
            </div>

            <div class="ui-widget-header radio_set">
                <span>Intimrasur</span>
                <fieldset>
                    <input type="radio" id="intimrasur1" value="1" name="check_shaven" checked /><label for="intimrasur1">Anzeigen</label>
                    <input type="radio" id="intimrasur2" value="0" name="check_shaven" /><label for="intimrasur2">Aus</label>
                </fieldset>
            </div>
            <div class="ui-widget-content profil-setting">';
                $shaven_ary = array(1 => 'Ja', 0 => 'Nein');
                $site .= '
                <select name="shaven" style="width:100%;">';
                    foreach ($shaven_ary as $key => $value) {
                        $site .= '<option value="'.$key.'">'.$value.'</option>';	
                    }
                    $site .= '
                </select>
            </div>

            <div class="ui-widget-header radio_set">
                <span>Haarfarbe</span>
                <fieldset>
                    <input type="radio" id="haarfarbe1" value="1" name="check_hair_color" checked /><label for="haarfarbe1">Anzeigen</label>
                    <input type="radio" id="haarfarbe2" value="0" name="check_hair_color" /><label for="haarfarbe2">Aus</label>
                </fieldset>
            </div>
            <div class="ui-widget-content profil-setting">';
                $hair_color_ary = array('wei&szlig;', 'blond', 'dunkelblond', 'rot', 'hellbraum', 'dunkelbraun', 'schwarz');
                $site .= '<select name="hair_color" style="width:100%;">';
                    foreach ($hair_color_ary as $value) {
                        $site .= '<option value="'.$value.'">'.utf8_encode($value).'</option>';	
                    }
                $site .= '
                </select>
            </div>

            <div class="ui-widget-header radio_set">
                <span>Augenfarbe</span>
                <fieldset>
                    <input type="radio" id="augenfarbe1" value="1" name="check_eye_color" checked /><label for="augenfarbe1">Anzeigen</label>
                    <input type="radio" id="augenfarbe2" value="0" name="check_eye_color" /><label for="augenfarbe2">Aus</label>
                </fieldset>
            </div>
            <div class="ui-widget-content profil-setting">';
                $eye_color_ary = array('Hellblau', 'Blaugrau', 'Dunkelblau', 'Hellbraun', 'Dunkelbraun', 'Hellgr&uuml;n', 'Gr&uuml;ngrau', 'Dunkelgr&uuml;n');
                $site .= '<select name="eye_color" style="width:100%;">';
                    foreach ($eye_color_ary as $value) {
                        $site .= '<option value="'.$value.'">'.utf8_encode($value).'</option>';	
                    }
                $site .= '
                </select>
            </div>

            <div class="ui-widget-header radio_set">
                <span>K&ouml;rbchengr&ouml;&szlig;e</span>
                <fieldset>
                    <input type="radio" id="koerbchen_groesse1" value="1" name="check_cup_size" checked /><label for="koerbchen_groesse1">Anzeigen</label>
                    <input type="radio" id="koerbchen_groesse2" value="0" name="check_cup_size" /><label for="koerbchen_groesse2">Aus</label>
                </fieldset>
            </div>
            <div class="ui-widget-content profil-setting">';
                $cup_sizeA_ary = array('65', '70', '75', '80', '85', '90', '95', '100', '105', '110');
                $site .= '<select name="cup_size_a" style="width:45px; text-align:right;">';
                    foreach ($cup_sizeA_ary as $value) {
                        $site .= '<option value="'.$value.'">'.utf8_encode($value).'</option>';	
                    }
                $site .= '
                </select>';
                $cup_sizeB_ary = array('AA', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K');
                $site .= '<select name="cup_size_b" style="width:40px; text-align:left;">';
                    foreach ($cup_sizeB_ary as $value) {
                        $site .= '<option value="'.$value.'">'.utf8_encode($value).'</option>';	
                    }
                $site .= '
                </select>
            </div>

            <div class="ui-widget-header radio_set">
                <span>Sexuelle Orientierung</span>
                <fieldset>
                    <input type="radio" id="sexuelle_orientierung1" value="1" name="check_sexual_orientation" checked /><label for="sexuelle_orientierung1">Anzeigen</label>
                    <input type="radio" id="sexuelle_orientierung2" value="0" name="check_sexual_orientation" /><label for="sexuelle_orientierung2">Aus</label>
                </fieldset>
            </div>
            <div class="ui-widget-content profil-setting">';
                $sexual_orientation_ary = array('Hetero', 'Homo', 'Bi');
                $site .= '
                <select name="sexual_orientation" style="width:100%;">';
                    foreach ($sexual_orientation_ary as $value) {
                        $site .= '<option value="'.$value.'">'.$value.'</option>';	
                    }
                    $site .= '
                </select>
            </div>

            <div class="ui-widget-header radio_set">
                <span>Familienstand</span>
                <fieldset>
                    <input type="radio" id="familienstand1" value="1" name="check_marital_status" checked /><label for="familienstand1">Anzeigen</label>
                    <input type="radio" id="familienstand2" value="0" name="check_marital_status" /><label for="familienstand2">Aus</label>
                </fieldset>
            </div>
            <div class="ui-widget-content profil-setting">';
                $familienstand_ary = array('ledig', 'verheiratet', 'geschieden', 'verwitwet', 'in fester Beziehung', 'verlobt');
                $site .= '
                <select name="marital_status" style="width:100%;">';
                    foreach ($familienstand_ary as $value) {
                        $site .= '<option value="'.$value.'">'.$value.'</option>';	
                    }
                    $site .= '
                </select>
            </div>


            <h2 style="margin-top:20px;">Interessen</h2>

            <div class="ui-widget-header radio_set">
                <fieldset>
                    <input type="radio" id="interessen1" value="1" name="check_interests" checked /><label for="interessen1">Anzeigen</label>
                    <input type="radio" id="interessen2" value="0" name="check_interests" /><label for="interessen2">Aus</label>
                </fieldset>
            </div>
            <div class="ui-widget-content profil-setting">
                <div class="radio_set">';
                    $interessen_ary = array('Feste Beziehung', 'One night stand', 'Seitensprung', 'regelm&auml;&szlig;ige Sextreffen', 'SM-Spiele', 'Sexuellen Austausch &uuml;bers Internet');
                    foreach ($interessen_ary as $key => $value) {
                        $site .= '<input type="checkbox" id="interests_'.$key.'" name="interests['.$value.']" value="1" /><label style="text-align:left; width:100%" for="interests_'.$key.'">'.$value.'</label>';	
                    }
                    $site .= '
                </div>
            </div>


            <h2 style="margin-top:20px;">Ich suche</h2>

            <div class="ui-widget-header radio_set">
                <fieldset>
                    <input type="radio" id="ich_suche1" value="1" name="check_looking_for" checked /><label for="ich_suche1">Anzeigen</label>
                    <input type="radio" id="ich_suche2" value="0" name="check_looking_for" /><label for="ich_suche2">Aus</label>
                </fieldset>
            </div>
            <div class="ui-widget-content profil-setting">
                <div class="radio_set">';
                    $looking_for_ary = array('M&auml;nner', 'Frauen', 'Paare (m,w)', 'Paare (m,m)', 'Paare (w,w)');
                    foreach ($looking_for_ary as $key => $value) {
                        $site .= '<input type="checkbox" id="looking_for_'.$key.'" name="looking_for['.$value.']" value="1" /><label style="text-align:left; width:100%" for="looking_for_'.$key.'">'.$value.'</label>';	
                    }
                    $site .= '
                </div>
            </div>


            <h2 style="margin-top:20px;">Sexuelle Vorlieben</h2>

            <div class="ui-widget-header radio_set">
                <fieldset>
                    <input type="radio" id="sexuelle_vorlieben1" value="1" name="check_sexual_preferences" checked /><label for="sexuelle_vorlieben1">Anzeigen</label>
                    <input type="radio" id="sexuelle_vorlieben2" value="0" name="check_sexual_preferences" /><label for="sexuelle_vorlieben2">Aus</label>
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

                    foreach ($sexual_preferences_ary as $key => $value) {
                        $site .= '<input type="checkbox" id="sexual_preferences_'.$key.'" name="sexual_preferences['.$value.']" value="1" /><label style="text-align:left; width:100%" for="sexual_preferences_'.$key.'">'.$value.'</label>';	
                    }
                    $site .= '
                </div>
            </div>

            <div style="text-align:right; margin-top:20px;">
                <input class="button" type="submit" name="new_actor" value="Profil erstellen" />
            </div>
        </form>
    </div>
</div>';


?>
