<?php

define('SAFE_INC', 1);

$sourcedir = dirname(dirname(dirname(dirname(__FILE__))));

include_once($sourcedir."/config.inc.php");
include_once(MCP_DIR."/common.inc.php");

if (!isset($_SESSION['merchant_id'])) {
    die('Access not allowed!');
}


# SDP (Offer/localDescription) schreiben
################################################
// Prüfe ob der Parameter "RTCSessionDescription" gesendet wurde

if (isset($_POST['localDescription'])) {
    
    // receiver';
    $actor_id = $_SESSION['merchant_id'];
    $member_id = $_POST['member_id'];
    
    // Wenn kein SDP übertragen wurde, dann Datei leeren
    if (!empty($_POST['localDescription'])) {

        $rs_sdp = p4c_query("SELECT * FROM `member_cams` WHERE 
            `member_id` = '".abs($member_id)."' AND
            `merchant_id` = '".abs($actor_id)."'
        LIMIT 1;",__FILE__,__LINE__);

        $content = base64_decode($_POST['localDescription']);
        $content_checksum = md5($content);

        if (p4c_num_rows($rs_sdp) > 0) {
            p4c_query("UPDATE `member_cams` SET
                `sdp_description_receiver` = '".p4c_escape_string($content)."',
                `sdp_receiver_checksum` = '".p4c_escape_string($content_checksum)."'
            WHERE 
                `member_id` = '".abs($member_id)."' AND
                `merchant_id` = '".abs($actor_id)."'
            LIMIT 1;",__FILE__,__LINE__);
        }

        echo 'localDescription erfolgreich gesendet';

    }
# SDP (Offer/localDescription) schreiben lesen
################################################
// Prüfe ob der Parameter "RTCSessionDescription" gesendet wurde
} else if (isset($_POST['get_localDescription'])) {
        
    // transmitter
    $actor_id = $_SESSION['merchant_id'];
    $member_id = $_POST['sender_id'];
    
    $rs_sdp = p4c_query("SELECT `sdp_description_transmitter` FROM `member_cams` WHERE 
        `member_id` = '".abs($member_id)."' AND
        `merchant_id` = '".abs($actor_id)."' AND 
        `sdp_description_transmitter` != ''
    LIMIT 1;",__FILE__,__LINE__);

    if (p4c_num_rows($rs_sdp) > 0) {
        echo p4c_result($rs_sdp, 0);
        #echo stripcslashes($localDescription);
    }
    

# ICE-Candidate schreiben
################################################
// Pr�fe ob der Parameter "RTCSessionDescription" gesendet wurde
} else if (isset($_POST['iceCandidate'])) {

    // receiver';
    $actor_id = $_SESSION['merchant_id'];
    $member_id = $_POST['member_id'];
              
    // Wenn keine ICE-Kandidaten übertragen wurden, dann datei leeren
    if (!empty($_POST['iceCandidate'])) {
        $rs_ice_candidates = p4c_query("SELECT * FROM `member_cams` WHERE 
            `member_id` = '".abs($member_id)."' AND
            `merchant_id` = '".abs($actor_id)."';",__FILE__,__LINE__);
        
        if (p4c_num_rows($rs_ice_candidates) > 0) {
            
            $content = base64_decode($_POST['iceCandidate']);
            $content_checksum = md5($content);
            
            p4c_query("UPDATE `member_cams` SET
                `ice_candidates_receiver` = '".p4c_escape_string($content)."',
                `ice_receiver_checksum` = '".p4c_escape_string($content_checksum)."'
            WHERE 
                `member_id` = '".abs($member_id)."' AND
                `merchant_id` = '".abs($actor_id)."';",__FILE__,__LINE__);
        }
    } else {
        
        p4c_query("UPDATE `member_cams` SET
            `ice_candidates_receiver` = '',
            `sdp_description_receiver` = ''
        WHERE 
            `member_id` = '".abs($member_id)."' AND
            `merchant_id` = '".abs($actor_id)."';",__FILE__,__LINE__);
        
    }
    
    
    echo 'IceCandidate erfolgreich gesendet';
    
    
# SDP (Offer/localDescription) schreiben lesen
################################################
// Prüfe ob der Parameter "RTCSessionDescription" gesendet wurde
} else if (isset($_POST['get_IceCandidate'])) {
    
    // transmitter';
    
    $actor_id = $_SESSION['merchant_id'];
    $member_id = $_POST['actor_id'];
    
    $rs_ice_candidates = p4c_query("SELECT `ice_candidates_transmitter` FROM `member_cams` WHERE 
        `member_id` = '".abs($member_id)."' AND
        `merchant_id` = '".abs($actor_id)."' AND
        `ice_candidates_transmitter` != ''
    LIMIT 1;",__FILE__,__LINE__);
    
    if (p4c_num_rows($rs_ice_candidates) > 0) {
        echo p4c_result($rs_ice_candidates, 0);
        
    }


} else if (isset($_POST['get_connection_status'])) {
    
    $array = array();
    
    if (isset($_POST['transmitter'])) {
        $member_id = $_POST['transmitter'];
        $actor_id = $_SESSION['merchant_id'];
    
    } else {
        $array['transmitter'] = 'offline';
        $array['receiver'] = 'offline';
        echo json_encode($array);
        p4c_close($db_server);
        p4c_errorlog(error_get_last());
    }    
    
    // prüfen ob ICE-Kandidaten vom User (transmitter) existieren
    $rs_transmitter = p4c_query("SELECT * FROM `member_cams` WHERE 
        `member_id` = '".abs($member_id)."' AND
        `merchant_id` = '".abs($actor_id)."' AND
        `ice_candidates_transmitter` != ''
    LIMIT 1;",__FILE__,__LINE__);
 
    if (p4c_num_rows($rs_transmitter) > 0) {
        $array['transmitter'] = 'online';
    } else {
        $array['transmitter'] = 'offline';
    }
    
    // prüfen ob ICE-Kandidaten vom User (receiver) existieren
    $rs_receiver = p4c_query("SELECT * FROM `member_cams` WHERE 
        `member_id` = '".abs($member_id)."' AND
        `merchant_id` = '".abs($actor_id)."' AND
        `ice_candidates_receiver` != ''
    LIMIT 1;",__FILE__,__LINE__);
    
    if (p4c_num_rows($rs_receiver) > 0) {
        $array['receiver'] = 'online';
    } else {
        $array['receiver'] = 'offline';
    }
    
    echo json_encode($array);
    
} else if (isset($_POST['delete_connection'])) {
    
    // Nur der Sender darf die Verbindung löschen
    if (isset($_SESSION['sender_id'])) {
        $actor_id = $_SESSION['sender_id'];
        $member_id = $_POST['actor_id'];
    } else {
        $actor_id = $_POST['actor_id'];
        $member_id = $_SESSION['member_id'];
    }
    
    p4c_query("DELETE FROM `cam_stun_turn` WHERE 
        `member_id` = '".abs($member_id)."' AND
        `transmitter_type` = 'member';",__FILE__,__LINE__);
    
}




p4c_close($db_server);
p4c_errorlog(error_get_last());