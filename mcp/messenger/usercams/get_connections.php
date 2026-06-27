<?php

define('SAFE_INC', 1);

$sourcedir = dirname(dirname(dirname(dirname(__FILE__))));

include_once($sourcedir."/config.inc.php");
include_once(MCP_DIR."/common.inc.php");

if (!isset($_SESSION['merchant_id'])) {
    die('Access not allowed!');
}

#print_r($_POST);

if (isset($_POST['get_userinfos'])) {
        
    $rs_member = p4c_query("SELECT * FROM `members` WHERE `id`='".abs($_POST['get_userinfos'])."' LIMIT 1;",__FILE__,__LINE__);
    $member_obj = p4c_fetch_object($rs_member);
    
    $ary = array();
    $ary['username'] = utf8_decode($member_obj->username);
    $ary['id'] = $member_obj->id;
    
    echo json_encode($ary);

    p4c_close($db_server);
    p4c_errorlog(error_get_last());
    
    exit;
}

$merchant_id = abs($_SESSION['merchant_id']);

$rs_connections = p4c_query("SELECT *, count(*) AS `count_ice_sdp` FROM `member_cams` WHERE
    `merchant_id`='".$merchant_id."' AND
    `datetime`>='".date("Y-m-d H:i:s", strtotime("-10 seconds"))."'
GROUP BY `member_id`",__FILE__,__LINE__);

$ary = array();

if (p4c_num_rows($rs_connections) == 0) {
    $ary['connection'] = 0;
    echo json_encode($ary);
    p4c_close($db_server);
    p4c_errorlog(error_get_last());
    exit;
}

while($con_obj = p4c_fetch_object($rs_connections)) {
    $params['member_id'] = $con_obj->member_id;
    $params['iframe_url'] = MCP_URL.'/Messenger/usercams/userstream.php?member_id='.$con_obj->member_id;
    $params['checksum'] = md5(json_encode($params));
    $params['chat_id'] = $con_obj->visible_for_actor.'_'.$con_obj->remote_member_id;

    $ary['connection'][$con_obj->member_id] = $params;
}

// Wenn Datum in Datenbank älter als eine Sekunde, war Verbindung abgebrochen und muss neu aufgebaut werden.
// Dafür müssen die SPD und ICE Felde ller gemacht werden
p4c_query("UPDATE `member_cams` SET
    `ice_candidates_transmitter`  = '',
    `sdp_description_transmitter` = '',
    `ice_candidates_receiver` = '',
    `sdp_description_receiver` = ''
WHERE 
    `datetime` <= '".date("Y-m-d H:i:s", strtotime("-10 Seconds"))."'
;", __FILE__, __LINE__);


if (isset($_SESSION['user_webcam_connections'])) {
    foreach ($_SESSION['user_webcam_connections'] as $member_id => $value) {
        
        if ($_SESSION['user_webcam_connections'][$member_id] == 'connection_closed') {
             unset($_SESSION['user_webcam_connections'][$member_id]);           
        }        
        
        if (!array_key_exists($member_id, $ary['connection'])) {
            $ary['connection'][$member_id] = 'connection_closed';
        }        
    }
}

$_SESSION['user_webcam_connections'] = $ary['connection'];

$ary['checksum'] = md5(json_encode($ary));
echo json_encode($ary);


p4c_close($db_server);
p4c_errorlog(error_get_last());
