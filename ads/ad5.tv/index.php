<?php

define('SAFE_INC', 1);

include_once("../../config.inc.php");
include_once(API_DIR."/common.inc.php");

if (isset($_GET['set_webmaster_id'])) {
    
    $username = trim(strip_tags($_GET['set_webmaster_id']));

    function check_username($username) {
        if (!empty($username)) {
            $rs_profil = p4c_query("SELECT `merchants`.`partner_id` FROM `actors` INNER JOIN `merchants` ON `actors`.`merchant_id`=`merchants`.`id` WHERE `actors`.`username`='". p4c_escape_string($username)."' LIMIT 1;",__FILE__,__LINE__);
            if (p4c_num_rows($rs_profil) > 0) {
                $wmid = p4c_result($rs_profil,0);

                if (!empty($wmid)) {
                    return $wmid;
                }
            }
        }
        return false;
    }
       
    $check_username = check_username($username);
    
    if ($check_username !== false) {
        header('Location: '.Pay4Coins_MCP_URL.'/Partner-werden?wmid='.$check_username);    
    } else {
        echo 'Der Partnerlink scheint nicht korrekt zu sein.';
    }
    
    
    
}

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());