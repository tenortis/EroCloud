<?php

define('SAFE_INC', 1);

include_once("../../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");


if (isset($_POST['username'])) {

    $search = array('ä','ö','ü','ß');
    $replace = array('ae','oe','ue','ss');
    $post_username = str_replace($search,$replace,trim($_POST['username']));
    $post_username = preg_replace ( '/[^a-zA-Z0-9-_.]/i', '', $post_username);
    
    if (trim($post_username) == '') {
        echo 'exists';
    } else {
        $rs_actor = p4c_query("SELECT `username` FROM `actors` WHERE `username`='".p4c_escape_string($post_username)."';",__FILE__,__LINE__);    
        
        if (p4c_num_rows($rs_actor) == 0) {
            echo 'ok';
        } else {
            echo 'exists';
        }
    }
        
}

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());


?>