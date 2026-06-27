#!/usr/bin/php
<?php

define('SAFE_INC', 1);

$sourcedir = dirname(dirname(__FILE__));
include_once($sourcedir."/config.inc.php");
include_once(MCP_DIR."/common.inc.php");

header('Content-Type: text/html; charset=utf-8');

$args = str_replace('--', "&", $_SERVER['argv'][1]);

parse_str($args, $_GET);


if ((isset($_GET['actor_id']) OR isset($_GET['merchant_id']) OR isset($_GET['all'])) AND isset($_GET['type'])) {

    $type = 'webcam';
    
    if (isset($_GET['actor_id'])) {
        $sql = "`actor_id` = '".abs($_GET['actor_id'])."' AND ";
    } else if (isset($_GET['merchant_id'])) {
        $sql = "`merchant_id` = '".abs($_GET['merchant_id'])."' AND ";
    } else {
        $sql = "";
    }
    
    
    $rs_messenger_sync = p4c_query("SELECT *, AES_DECRYPT(`apikey`, '".AES_KEY."') AS `apikey` FROM `messenger_sync` WHERE
    ".$sql."
    `sync_time`!='0000-00-00 00:00:00';",__FILE__,__LINE__);

    if (p4c_num_rows($rs_messenger_sync) > 0) {
        while($site_obj = p4c_fetch_object($rs_messenger_sync)) {

            $actor_id = $site_obj->actor_id;
            $remote_actor_id = $site_obj->remote_actor_id;
            $domain = $site_obj->domain;

            // Prüfen ob Website aktiv ist. Wenn nicht, mache mit nächster website weiter
            $rs_sites = p4c_query("SELECT * FROM `sites` WHERE `domain`='". p4c_escape_string($domain)."' AND `status`='1';",__FILE__,__LINE__);
            if (p4c_num_rows($rs_sites) == 0) {
                continue;
            }
            
            $param = array(
                "apikey" => $site_obj->apikey,
                "actor_id"=> $remote_actor_id,
                "type" => $type
            );
            
            if (isset($_GET['month'])) {
                $param = array_merge($param, array('month'=>date("Y-m")));
            
            } else if (isset($_GET['date'])) {
                $param = array_merge($param, array('date'=> date("Y-m-d", strtotime($_GET['date']))));
            };

            $param = array_filter($param, "strlen"); // leere Einträge Entfernen
            ksort($param); // Alphabetische Sortierung
            $query = http_build_query($param, '&amp;');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://'.$domain.'/erocloud_api/send_erocms_revenue.php?'.$query);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1); 
            curl_setopt($ch, CURLOPT_REFERER, URL);
            curl_setopt($ch, CURLOPT_USERAGENT, PROJECTNAME." - ".COMPANYNAME." - ".URL);
            $data = curl_exec($ch);
            curl_close($ch);

            if($data != false) {
                
                $revenue_obj = json_decode($data);
                
                if (isset($revenue_obj->domain) AND $revenue_obj->domain == $domain) {
                
                    foreach($revenue_obj->date as $date => $amount) {
                        
                        $amount = abs($amount);
                        
                        $date = date("Y-m-d", strtotime($date));

                        if ($date != '1970-01') {                  

                            if ($amount > 0) {

                                $rs_revenue_webcam = p4c_query("SELECT * FROM `revenue_webcam` WHERE 
                                    `merchant_id` = '".abs($site_obj->merchant_id)."' AND
                                    `actor_id` = '".abs($actor_id)."' AND
                                    `date` = '".p4c_escape_string($date)."' AND
                                    `domain` = '".p4c_escape_string($domain)."' LIMIT 1;
                                ",__FILE__,__LINE__);

                                if (p4c_num_rows($rs_revenue_webcam) == 0) {
                                    p4c_query("INSERT INTO `revenue_webcam` SET 
                                        `merchant_id` = '".abs($site_obj->merchant_id)."',
                                        `actor_id` = '".abs($actor_id)."',
                                        `date` = '".p4c_escape_string($date)."',
                                        `domain` = '".p4c_escape_string($domain)."',
                                        `commision` = '".abs($amount)."'
                                    ",__FILE__,__LINE__);
                                } else {
                                    p4c_query("UPDATE `revenue_webcam` SET 
                                        `commision` = '".abs($amount)."'
                                    WHERE 
                                        `merchant_id` = '".abs($site_obj->merchant_id)."' AND
                                        `actor_id` = '".abs($actor_id)."' AND
                                        `date` = '".p4c_escape_string($date)."' AND
                                        `domain` = '".p4c_escape_string($domain)."'
                                    LIMIT 1;",__FILE__,__LINE__);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
         

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());


?>