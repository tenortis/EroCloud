<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");
       
if (isset($_POST['login']) AND isset($_POST['user']) AND isset($_POST['pass'])) {
    $user = trim(preg_replace ( '/[^a-z0-9]/i', '', $_POST['user']));
    $pass = trim(htmlspecialchars(strip_tags($_POST['pass']),ENT_QUOTES, 'ISO-8859-1'));
    
    if(!empty($user) AND !empty($pass)) {

        $rs_user = p4c_query("SELECT `id`, `username`, `password`, `rule`, `access_area` FROM `employee` WHERE `username`='".p4c_escape_string($user)."' LIMIT 1;",__FILE__,__LINE__);
        
        if (p4c_num_rows($rs_user) > 0) {
            $user_ary = p4c_fetch_object($rs_user);
        	
            $explode = explode(':',$user_ary->password);
            $password = $explode[0];
            $salt = $explode[1];

            if ($password == saltPassword($pass, $salt)) {
                $_SESSION['logged_id'] = 'acp';
                $_SESSION['employee_id'] = $user_ary->id;
                $_SESSION['employee_rule'] = $user_ary->rule;
                $_SESSION['employee_access_area'] = explode(',',$user_ary->access_area);
                $_SESSION['employee_username'] = $user_ary->username;
                $_SESSION['last_activity'] = time();
                log_action('Am System angemeldet.');
                header('Location: '.ACP_URL.'/Startseite');
                exit;
            } else {
                log_action('Login Error - Passwort falsch! - User: '.$user);
            }
        } else {
            log_action('Login Error - User unbekannt! - User: '.$user);
        }
    }
}


$site .= '
<div class="ui-widget-content" style="margin-top:30px; margin-left:50px; padding:20px; width:280px;">
    <form action="" method="post">
        <table style="width:100%">
            <tr>
                <td style="padding-bottom:5px; text-align:right; width:60px;">User:</td>
                <td style="padding-bottom:5px; padding-left:20px;"><input style="width:180px;" type="text" name="user" /></td>
            </tr>
            <tr>
                <td style="padding-bottom:5px; text-align:right;">Pass:</td>
                <td style="padding-bottom:5px; padding-left:20px;"><input style="width:180px;" type="password" name="pass" /></td>
            </tr>
            <tr>
                <td></td>
                <td style="padding-left:20px;"><input class="button" type="submit" name="login" value="Einloggen" /></td>
            </tr>
        </table>
    </form>
</div>';
    

?>