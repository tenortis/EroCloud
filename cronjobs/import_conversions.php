#!/usr/bin/php
<?php
 
define('SAFE_INC', 1);

$sourcedir = dirname(dirname(__FILE__));
include_once($sourcedir."/config.inc.php");
include_once(MCP_DIR."/common.inc.php");

$args = str_replace('--', "&", $_SERVER['argv'][1]);
parse_str($args, $_GET);

function getConversionsData($url, $site_obj) {
    global $class_errorlog;
  
    
    $options = array(
        CURLOPT_UNRESTRICTED_AUTH  => 1,
        CURLOPT_AUTOREFERER  => 1,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_URL  => $url,
        CURLOPT_HEADER  => 0,
        CURLOPT_USERAGENT  => "EroCloud-Bot ".date("Y")." / https://erocloud.net",
        CURLOPT_FRESH_CONNECT => true,
        CURLOPT_NOSIGNAL => true,
        CURLOPT_RETURNTRANSFER => true, // Antwort aus cURL-Aufruf zurückgeben
        #CURLOPT_FOLLOWLOCATION => true, // Weiterleitungen automatisch folgen
        #CURLOPT_MAXREDIRS => 3, // Maximale Anzahl von Weiterleitungen festlegen
    );
    
    /*
    $options = array(
        CURLOPT_VERBOSE => true, // Detaillierte Informationen protokollieren
        CURLOPT_UNRESTRICTED_AUTH => 1,
        CURLOPT_AUTOREFERER => 1,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_URL => $url,
        CURLOPT_HEADER => 0,
        CURLOPT_USERAGENT => "EroCloud-Bot ".date("Y")." / https://erocloud.net",
        CURLOPT_FRESH_CONNECT => true,
        CURLOPT_NOSIGNAL => true,
        CURLOPT_RETURNTRANSFER => true, // Antwort aus cURL-Aufruf zurückgeben
        //CURLOPT_FOLLOWLOCATION => true, // Weiterleitungen automatisch folgen
        //CURLOPT_MAXREDIRS => 3, // Maximale Anzahl von Weiterleitungen festlegen
    );
    */
    
    // cURL-Session starten
    $ch = curl_init();

    // Optionen setzen
    curl_setopt_array($ch, $options);

    // Setzen Sie die Zeitstempel und Schleifenvariablen
    $start_time = microtime(true);
    $max_wait_time = 2; // Maximale Wartezeit in Sekunden
    $file_get_contents = null;
    
    // Führen Sie die Schleife solange aus, bis eine Antwort empfangen wurde oder die maximale Wartezeit überschritten wurde
    while (!$file_get_contents && microtime(true) - $start_time < $max_wait_time) {
        // Führen Sie cURL aus und speichern Sie die Antwort
        $file_get_contents = curl_exec($ch);
    }
    
   
    // Wenn beim aufrufen der URL ein Fehler aufgetreten ist
    if(!$file_get_contents) {
            
        #$class_errorlog->log($file_get_contents,__FILE__,__LINE__);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);	
        
        mail(TECHSUPPORT_EMAIL, 'EroADS - Conversion-Error', "Bitte Domain prüfen!
Folgende URL ist zum angegebenen Zeitpunkt nicht erreichbar gewesen.

Domain: ".$url."

Date-Time: ".date("Y-m-d H:i:s")."
Datei: ".__FILE__."
Zeile: ".__LINE__."

Curl error: ".$error."
Curl errno: ".$errno."
", 'From: EroAds-Bot <no-replay@erocloud.net>');
            // Wenn alles ok
    } else {
        
        #$class_errorlog->log($file_get_contents,__FILE__,__LINE__);

        curl_close($ch);
        
        // Prüfen ob Logs als XML gesendet werden    
        preg_match_all('/<log>(.*)<\/log>/smU',$file_get_contents, $xml_logs);
        if (isset($xml_logs[1]) AND !empty($xml_logs[1])) {
                   
            $xml = simplexml_load_string($file_get_contents);

            foreach($xml->log as $array) {
                
                /** $array 
                    [id] => 23
                    [member_id] => 34
                    [campaign_id] => zTApAT3zYp
                    [wm_id] => T5DBS4Q7NU
                    [user_hash] => 3607b3ac7ec4d5d68cb1b7ff19eda418
                    [sign_form] => 1
                    [is_registered] => 0
                    [first_payment] => 0.00
                    [follow_payment] => 0.00
                    [gutschrift_payment] => 0.00
                    [storno_payment] => 0.00
                    [timestamp] => 2018-09-19 13:39:14
                    [username] => webmaster3Ã¶ÃŸ
                 **/
                
                if (count($array) > 5) {
                    #$class_errorlog->log(print_r($array, true),__FILE__,__LINE__);
                    
                    $checksum = sha1(json_encode($array));
                    
                    // Aus array variablen machen
                    foreach ($array as $key => $value) {
                        $$key = $value;
                    }
                    
                    $wm_id = trim(preg_replace ( '/[^a-z0-9.-]/i', '', $wm_id));
                    $timestamp = date("Y-m-d H:i:s", strtotime($timestamp));
                    
                    if(!isset($member_id)) {
                        mail(TECHSUPPORT_EMAIL, 'EroADS - Conversion Error', "Bitte Domain prüfen!
Es wurden keine Daten gefunden. Eventuell existiert die Domain nicht mehr.
Domain: ".$site_obj->domain."

Date-Time: ".date("Y-m-d H:i:s")."
Host: erocloud.net
Datei: ".__FILE__."
Zeile: ".__LINE__, 'From: EroADS-Bot <no-replay@erocloud.net>'); 
                        
                        p4c_close(DB_HOST);
                        p4c_errorlog(error_get_last());
                        exit;
                    }
                    
                    if (!empty($member_id) OR $member_id != '0') {
                        p4c_query("UPDATE `ads_conversions` SET
                            `username`  = '".p4c_escape_string($username)."',
                            `member_id` = '".abs($member_id)."'
                        WHERE
                            `user_hash` = '".p4c_escape_string($user_hash)."' AND
                            `member_id` = '0' AND
                            `site_id`   = '".abs($site_obj->id)."' AND
                            `wmid`      = '".p4c_escape_string($wm_id)."'
                        LIMIT 1;",__FILE__,__LINE__);
                    }
                    
                    
                    $rs_check_exists = p4c_query("SELECT * FROM `ads_conversions` WHERE
                        `cheksum`   = '".$checksum."' AND
                        `date_time` = '".$timestamp."'
                    LIMIT 1;",__FILE__,__LINE__);
                    
                    if (p4c_num_rows($rs_check_exists) == 0) {
                    
                        if (!isset($first_payment)) {$first_payment = 0;}
                        if (!isset($follow_payment)) {$follow_payment = 0;}
                        if (!isset($gutschrift_payment)) {$gutschrift_payment = 0;}
                        if (!isset($storno_payment)) {$storno_payment = 0;}

                        p4c_query("INSERT INTO `ads_conversions` SET
                            `cheksum`       = '".p4c_escape_string($checksum)."',
                            `date_time`     = '".$timestamp."',
                            `site_id`       = '".abs($site_obj->id)."', 
                            `p4c_partner_id`= '".p4c_escape_string($site_obj->partner_id)."', 
                            `user_hash`     = '".p4c_escape_string($user_hash)."', 
                            `member_id`     = '".abs($member_id)."',
                            `username`      = '".p4c_escape_string($username)."',
                            `wmid`          = '".p4c_escape_string($wm_id)."',
                            `campaign_id`   = '".p4c_escape_string($campaign_id)."',
                            `join`          = '".abs($sign_form)."',
                            `reg`           = '".abs($is_registered)."',
                            `first_payment` = '".p4c_escape_string($first_payment)."',
                            `follow_payment`= '".p4c_escape_string($follow_payment) ."',
                            `again_credited_payment`= '".p4c_escape_string($gutschrift_payment) ."',
                            `canceled_payment`      = '".p4c_escape_string($storno_payment)."';
                        ",__FILE__,__LINE__);
                    }
                    
                }
            }
            
            p4c_query("UPDATE `sites` SET `import_conversions_updatetime`='".date("Y-m-d H:i:s")."' WHERE `id`='".abs($site_obj->id)."' LIMIT 1;",__FILE__,__LINE__);
            
        } else {
            if (!empty($file_get_contents)) {

                // Wenn API-Key falsch
                if (trim($file_get_contents) == 'false key') {
                    $class_errorlog->log("Conversions konnten nicht abgefragt werden!\nURL :".$url."\nError: ".$file_get_contents,__FILE__,__LINE__);    
                    return false;
                }
                
                preg_match_all('/<eroads>(.*)<\/eroads>/smU',$file_get_contents, $xml_logs);

                if (!isset($xml_logs[1]) OR empty($xml_logs[1])) {
                    $class_errorlog->log("Conversions konnten nicht abgefragt werden!\nURL :".$url."\nError :<pre>".$file_get_contents."</pre>",__FILE__,__LINE__);    
                    return false;
                }
                
                p4c_query("UPDATE `sites` SET `import_conversions_updatetime`='".date("Y-m-d H:i:s")."' WHERE `id`='".abs($site_obj->id)."' LIMIT 1;",__FILE__,__LINE__);
                
            }
        }
    }
}


// Coronjob GET-Parameter
// Aufruf wie folgt: /usr/bin/php /var/www/web1/htdocs/cronjobs/import_conversions.php --date=today
// Oder: /usr/bin/php /var/www/web1/htdocs/cronjobs/import_conversions.php --date=2022-05-23

$date = date('Y-m-d');
if (isset($_GET['date'])) {
    if ($_GET['date'] == 'today') {
        $date = date('Y-m-d');
        
    } else if ($_GET['date'] == 'yesterday') {
        $date = date('Y-m-d', strtotime("-1 days"));
        
    } else {
        $date_ary  = explode('-', $_GET['date']);
        if (checkdate($date_ary[1], $date_ary[2], $date_ary[0]) AND strlen($_GET['date']) == 10 AND count($date_ary) == 3) {
            $date = date("Y-m-d", strtotime($_GET['date']));
        }
    }
}


if (isset($date)) {
    $rs_sites = p4c_query("SELECT 
        `sites`.`id`,
        `sites`.`domain`,
        `sites`.`partner_id`,
        `sites`.`eroads_url`,
        AES_DECRYPT(`merchants`.`api_key`, '".AES_KEY."') AS `apikey`
    FROM `sites` INNER JOIN `merchants` ON `sites`.`partner_id`=`merchants`.`partner_id` WHERE
        `status`='1' AND
        `is_eroads_active`='1' AND
        `eroads_url`!='' AND
        `shop_system`='erocms' AND 
        `import_conversions_updatetime` <= '".date("Y-m-d H:i:s", strtotime("-10 Minutes"))."'
    ORDER BY `sites`.`import_conversions_updatetime` ASC LIMIT 20;",__FILE__,__LINE__);
    
    while($site_obj = p4c_fetch_object($rs_sites)) {
        $url = $site_obj->eroads_url.'?apikey='.$site_obj->apikey.'&date='.$date;
        #$class_errorlog->log($site_obj->eroads_url.'?apikey='.$site_obj->apikey.'&date='.$date,__FILE__,__LINE__);
        getConversionsData($url, $site_obj);
    }
}    
    
p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>