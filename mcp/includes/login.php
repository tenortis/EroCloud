<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$recaptcha = new ReCaptcha(RECAPTCHA_SITEKEY);

$error = '';  
if (isset($_POST['login']) AND isset($_POST['pid']) AND isset($_POST['pass'])) {
    
    // Wenn reCaptcha aktiviert ist prüfe ob Mensch (true) oder Bot (false)
    if (!isset($_POST['g-recaptcha-response'])) {$_POST['g-recaptcha-response'] = 'x';}
    $ergebnis = $recaptcha->is_human($_POST['g-recaptcha-response'],RECAPTCHA_SECRETKEY);

    if ($ergebnis != true) {
        $error = 'reCAPTCHA nicht korrekt. Best&auml;tigen Sie, dass Sie kein Roboter sind.';
    }
    #G-AZSAM9U5
    if ($error == '') {
        $user = trim(preg_replace ( '/[^a-z0-9.-]/i', '', $_POST['pid']));
        $pass = trim(htmlspecialchars($_POST['pass'],ENT_QUOTES, 'ISO-8859-1'));

        if(!empty($user) AND !empty($pass)) {
            if (substr($user, 0, 2) == 'G-') {
                $rs_group = p4c_query("SELECT `groups`.`id` AS id, `groups`.`group_id`, `merchant_id`  FROM `groups` INNER JOIN `merchants` ON `groups`.`merchant_id`=`merchants`.`id` WHERE `groups`.`group_password`='".p4c_escape_string($pass)."' AND `groups`.`group_id`='". p4c_escape_string($user)."';",__FILE__,__LINE__);
                if (p4c_num_rows($rs_group) > 0) {
                    $user_ary = p4c_fetch_object($rs_group);
                    
                    $merchant_id = abs($user_ary->merchant_id);
                    if (isset($merchant_id) AND trim($merchant_id) != '') {
                         $merchant = new Merchant($mysql,$merchant_id);

                         $_SESSION['my_chatgroup'] = $user_ary->id;
                         $_SESSION['logged_in_as'] = 'group';
                         $_SESSION['logged_id'] = 'mcp';
                         $_SESSION['merchant_id'] = $merchant->id();
                         $_SESSION['merchant_username'] = $user_ary->group_id;
                         $_SESSION['last_activity'] = time();
                        
                         header('Location: '.MCP_URL.'/Startseite');
                         exit;
                    }
                }
                $error = 'Benutzername oder Passwort falsch.';
            }
        } else {
            $error = 'Benutzername oder Passwort falsch.';
        }
    }
}


$site .= '
<script type="text/javascript">
<!--
    jQuery.noConflict();

    jQuery(document).ready(function() {
        jQuery("#pid").focus();
    })
    
    grecaptcha.ready(function() {
        grecaptcha.execute("'.RECAPTCHA_SITEKEY.'", {action: "studio_login"})
        .then(function(token) {
            jQuery("#grecaptcha_token").val(token);
        });
    });
-->    
</script>

<div id="site_login">';

    if (isset($error) AND !empty($error)) {
        $site .= '<div class="ui-state-error" style="padding:5px; border-bottom:none;">'.$error.'</div>';
    }
    
    $site .= '
    <div class="ui-widget-content">
        <div id="login_text"> 
            <div style="font-size:20px; margin-bottom:5px;">Anmeldung</div>
            <div style="font-size:15px; margin-bottom:25px;">als Darsteller mit einem Studio-Login.</div>
            <div style="text-align:justify; margin-top:25px;">
                Dieser Loginbereich ist nur f&uuml;r Darsteller die einen "Studio-Login" bekommen haben.
            </div>
            <div style="text-align:justify; margin-top:10px;">
                Wenn du Partner von Pay4Coins bist und keinen StudioLogin hast, klicke bitte hier: <a href="'.LOGIN_URL.'">mein Pay4Coins-Account</a>
            </div>
        </div>

        <div id="login_form">
            <form action="" method="post">
                <div class="login_field">
                    <div>Benutzername</div>
                    <input type="text" id="pid" name="pid" placeholder="Benutzername" />
                </div>

                <div class="login_field">
                    <div>Passwort</div>
                    <input type="password" name="pass" placeholder="Passwort" />
                </div>

                <div style="margin-top:20px; text-align:right;">
                    <table style="width:100%">
                        <tr>
                            <td style="text-align:right;">
                                <input type="hidden" id="grecaptcha_token" name="g-recaptcha-response" value="" />
                                <input class="button" style="font-size:18px;" type="submit" name="login" value="Einloggen" />
                            </td>
                        </tr>
                    </table>
                </div>
            </form>
        </div>
    </div>
</div>';
    

?>