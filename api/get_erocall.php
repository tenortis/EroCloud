<?php

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");


$apikey = 'EpuMVsVWL749RY2rfZ4aT2wFdvMGLxTw';

if (isset($_GET['hash']) && is_string($_GET['hash'])) {
    
    $_hash = trim($_GET["hash"]);
    unset($_GET['hash']);
    
    $_query = $_GET;
    //$_query = array_filter($_query, "strlen"); // leere Einträge Entfernen
    ksort($_query); // Alphabetische Sortierung
    $_query = http_build_query($_query);
    $_xhash = hash("sha512", "$_query&".$apikey);
    
    #echo $_xhash.'<br />';
    #exit;

    if ($_hash != $_xhash) {
        $class_errorlog->log('EroCall-Hash falsch!',__FILE__,__LINE__);
        exit;
    }
  
    $actor_id = abs((int)(is_scalar($_GET['erocloud_actor_id'] ?? null) ? $_GET['erocloud_actor_id'] : 0));
    $rufnummer = is_scalar($_GET['rufnummer'] ?? null) ? (string)$_GET['rufnummer'] : '';
    $ddi = is_scalar($_GET['ddi'] ?? null) ? (string)$_GET['ddi'] : '';
    $caller = is_scalar($_GET['caller'] ?? null) ? (string)$_GET['caller'] : '';
    $destination_number = is_scalar($_GET['destination_number'] ?? null) ? (string)$_GET['destination_number'] : '';
    $event = is_scalar($_GET['event'] ?? null) ? (string)$_GET['event'] : '';
    $event_info = is_scalar($_GET['event_info'] ?? null) ? (string)$_GET['event_info'] : '';
    $date_time = date("Y-m-d H:i:s", is_scalar($_GET['date_time'] ?? null) ? (int)$_GET['date_time'] : 0);
    $timestamp = abs((int)(is_scalar($_GET['timestamp'] ?? null) ? $_GET['timestamp'] : 0));
    $tarif = floatval(is_scalar($_GET['tarif'] ?? null) ? $_GET['tarif'] : 0);
    $duration_out = abs((int)(is_scalar($_GET['duration'] ?? null) ? $_GET['duration'] : 0));
    $payout = floatval(is_scalar($_GET['payout'] ?? null) ? $_GET['payout'] : 0);
    $provision_mobil = abs((float)(is_scalar($_GET['provision_mobil'] ?? null) ? $_GET['provision_mobil'] : 0));
    $provision_festnetz = abs((float)(is_scalar($_GET['provision_festnetz'] ?? null) ? $_GET['provision_festnetz'] : 0));
    
    $erocall_number_de = substr($rufnummer, 5);
    if ($ddi !== '') {
        $erocall_number_de = substr($erocall_number_de, 0, -strlen($ddi));
    }
    
    $rs_check_actor_exists = p4c_query("SELECT * FROM `actors` WHERE
        `id`='".abs((int)$actor_id)."' AND
        `erocall_number_de`='". p4c_escape_string($erocall_number_de)."' AND
        `erocall_number_de_ddi`='". p4c_escape_string($ddi)."'
    LIMIT 1;",__FILE__,__LINE__);

    if (p4c_num_rows($rs_check_actor_exists) == 0) {
        $class_errorlog->log("EroCall Error - Darsteller nicht gefunden:\n".print_r($_GET,true)."\n"."SELECT * FROM `actors` WHERE
        `id`='".abs((int)$actor_id)."' AND
        `erocall_number_de`='". p4c_escape_string($erocall_number_de)."' AND
        `erocall_number_de_ddi`='". p4c_escape_string($ddi)."'
    LIMIT 1;",__FILE__,__LINE__);
        exit;
    }

    include_once(SOURCEDIR.'/includes/klassen/actor.inc.php');
    $actor = new Actor($actor_id);
    
    $netz = 'festnetz';
    if (isset($_GET['caller_netz']) AND $_GET['caller_netz'] == 'TC80') {
        $netz = 'TC80';
    } else if (isset($_GET['caller_netz']) AND $_GET['caller_netz'] == 'mobil') {
        $netz = 'mobil';
    }
    
    $destination_netz = 'festnetz';
    if (isset($_GET['destination_netz']) AND $_GET['destination_netz'] == 'TC80') {
        $destination_netz = 'TC80';
    } else if (isset($_GET['destination_netz']) AND $_GET['destination_netz'] == 'mobil') {
        $destination_netz = 'mobil';
    }    
   
    // Prüfen ob Rufnummer aus Ziffern besteht // Rufnummer kann auch sein: 0049123456789XXX
    if (($event == 'connect' OR $event == 'hangup') AND ctype_digit(str_replace('x','', strtolower((string)$caller)))) {
        $rs_anrufe = p4c_query("SELECT * FROM `erocall` WHERE `rufnummer`='".p4c_escape_string($rufnummer)."' AND `date_time`='".p4c_escape_string($date_time)."' AND `timestamp`='".abs((int)$timestamp)."' AND `event`='".p4c_escape_string($event)."' LIMIT 1;",__FILE__,__LINE__);
        if (p4c_num_rows($rs_anrufe) == 0) {
            if(p4c_query("INSERT INTO `erocall` SET 
                `actor_id`='".abs((int)$actor->get('id'))."',
                `merchant_id`='".abs((int)$actor->get('merchant_id'))."',
                `rufnummer`='".p4c_escape_string($rufnummer)."',
                `caller`='".p4c_escape_string($caller)."',
                `caller_netz`='".p4c_escape_string($netz)."',
                `destination_number`='".p4c_escape_string($destination_number)."',
                `destination_netz`='".p4c_escape_string($destination_netz)."',
                `tarif`='".floatval($tarif)."',
                `duration_out`='".abs((int)$duration_out)."',
                `provision_mobil`='".abs((float)$provision_mobil)."',
                `provision_festnetz`='".abs((float)$provision_festnetz)."',
                `merchant_payout`='".floatval($payout)."',
                `date_time`='".p4c_escape_string($date_time)."',
                `timestamp`='".abs((int)$timestamp)."',
                `event`='".p4c_escape_string($event)."',
                `event_info`='".p4c_escape_string($event_info)."';
            ",__FILE__,__LINE__)) {
                echo 'inopla:ok';
            }
        }            
    }
}

// Garbage Collection
p4c_close(DB_HOST);
	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>