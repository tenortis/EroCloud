<?php

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

$cookie_ary = array();
if (isset($_COOKIE['erocloud']) && is_string($_COOKIE['erocloud'])) {
    $erocloud_cookie = base64_decode($_COOKIE['erocloud']);
    if (is_string($erocloud_cookie)) {
        $decoded_cookie = json_decode($erocloud_cookie, true);
        if (is_array($decoded_cookie)) {
            $cookie_ary = $decoded_cookie;
        }
    }
}

if (!isset($_GET['file_id']) || !is_scalar($_GET['file_id'])) {
    p4c_close(DB_HOST);
    exit;
}


// Bannercode erstellen
$file_id = (string)$_GET['file_id'];

$rs_ads = p4c_query("SELECT * FROM `ads_media` WHERE
    `file_id` = '".p4c_escape_string($file_id)."'
LIMIT 1;",__FILE__,__LINE__);

// Prüfen ob Seite existiert - wenn nicht, zu EroCloud leiten
if (p4c_num_rows($rs_ads) == 0) {
    p4c_close(DB_HOST);
    exit;
}

$ads_obj = p4c_fetch_object($rs_ads);
if (!$ads_obj) {
    p4c_close(DB_HOST);
    exit;
}

$filename = ADS_PATH.'/'.ADS_DEFAULT_DIR.'/'.abs((int)$ads_obj->site_id).'/'.(string)$ads_obj->filename;

if (!is_file($filename)) {
    http_response_code(404);
    p4c_close(DB_HOST);
    exit;
}

$mime_content_type = mime_content_type($filename);
if (!is_string($mime_content_type) || $mime_content_type === '') {
    $mime_content_type = 'application/octet-stream';
}

if (!function_exists('getRequestHeaders')) {
    function getRequestHeaders() {
        if (function_exists("apache_request_headers")) {
            if($headers = apache_request_headers()) {
                return $headers;
            }
        }
        $headers = array();
        // Grab the IF_MODIFIED_SINCE header
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $headers['If-Modified-Since'] = $_SERVER['HTTP_IF_MODIFIED_SINCE'];
        }
        return $headers;
    }
}

$headers = getRequestHeaders();
$file_mtime = filemtime($filename);
$file_mtime_int = ($file_mtime !== false) ? (int)$file_mtime : time();
$if_mod_ts = (isset($headers['If-Modified-Since']) && is_string($headers['If-Modified-Since']))
    ? strtotime($headers['If-Modified-Since'])
    : false;

if ($if_mod_ts !== false && $file_mtime !== false && $if_mod_ts == $file_mtime_int) {
    header('Last-Modified: '.gmdate('D, d M Y H:i:s', $file_mtime_int).' GMT', true, 304);
} else {
    header('Last-Modified: '.gmdate('D, d M Y H:i:s', $file_mtime_int).' GMT', true, 200);
    header('Content-transfer-encoding: binary');
    
    header('Content-type: '.$mime_content_type);
    $file_size = filesize($filename);
    if ($file_size !== false) {
        header("Content-Length: " . $file_size);
    }
    readfile($filename);
}




/**
 *  Impressions loggen
 **/

if (isset($_SERVER["HTTP_REFERER"]) && is_string($_SERVER["HTTP_REFERER"]) && trim($_SERVER["HTTP_REFERER"]) !== '') {
    $ref = $_SERVER["HTTP_REFERER"];

    $parse_url = parse_url($ref);
    if (is_array($parse_url) && !empty($parse_url['host'])) {
        $host = (string)$parse_url['host'];
        $host = str_replace('www.', '', $host);
    } else {
        $host = $ref;
    }

} else {
    $ref = 'none';
    $host = 'none';
}

/**
 *  Impressions loggen
 **/
if ($ref == 'none') {
    p4c_close(DB_HOST);
    p4c_errorlog(error_get_last());
    exit;
}

if (isset($_GET['wmid']) && is_scalar($_GET['wmid'])) {
    
    $pid = trim((string)preg_replace('/[^a-z0-9.-]/i', '', (string)$_GET['wmid']));
    $cid  = '';
    
    // Nur Loggen wenn wmid != pid aus Cookie
    // Wenn wmid == pid dann ist es der WM selber der das Banner aufruft.
    if (isset($cookie_ary['pid']) AND $cookie_ary['pid'] == $pid) {
        p4c_close(DB_HOST);
        p4c_errorlog(error_get_last());
        exit;
    }
    
    if (isset($_GET['cid']) && is_scalar($_GET['cid'])) {
        $cid = trim((string)preg_replace('/[^a-z0-9.-]/i', '', (string)$_GET['cid']));

        // Wenn die Campagne existiert
        $rs_campaign_exists = p4c_query("SELECT * FROM `ads_campaigns` WHERE `campaign_id`='".p4c_escape_string($cid)."' AND `p4c_partner_id`='".p4c_escape_string($pid)."' LIMIT 1;",__FILE__,__LINE__);

        if (p4c_num_rows($rs_campaign_exists) == 0) {
            $cid = '';
        }
    }
    
    // Erstelle jeden Monat eine neue Tabelle
    p4c_query("CREATE TABLE IF NOT EXISTS `ads_impressions_".date('Ym')."` (
        `id` int(12) NOT NULL auto_increment,
        `p4c_partner_id` varchar(10) NOT NULL,
        `media_id` int(15) NOT NULL,
        `campaign_id` varchar(10) NOT NULL,
        `number_of_impressions` int(15) NOT NULL default '0', 
        `timestamp` date NOT NULL default '0000-00-00',
        `site_id` int(12) NOT NULL,
        `url` varchar(255) NOT NULL default '',
        `referer` varchar(500) NOT NULL default '',
        PRIMARY KEY (`id`),
        KEY `timestamp` (`timestamp`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;",__FILE__,__LINE__);

    $rs_check = p4c_query("SELECT `timestamp` FROM `ads_impressions_".date('Ym')."` WHERE
        `p4c_partner_id`= '".p4c_escape_string($pid)."' AND
        `site_id`    = '".abs((int)$ads_obj->site_id)."' AND
        `referer`   = '".p4c_escape_string($ref)."' AND
        `timestamp` = '".date("Y-m-d")."' LIMIT 1;",__FILE__,__LINE__);

    if (p4c_num_rows($rs_check) == 0) {
        p4c_query("INSERT INTO `ads_impressions_".date('Ym')."` SET
            `p4c_partner_id`= '".p4c_escape_string($pid)."',
            `media_id`  = '".abs((int)$ads_obj->id)."',
            `campaign_id` = '".p4c_escape_string($cid)."',
            `number_of_impressions`=number_of_impressions+1,
            `timestamp` = '".date("Y-m-d")."',
            `site_id`   = '".abs((int)$ads_obj->site_id)."',
            `url`       = '".p4c_escape_string($host)."',
            `referer`   = '".p4c_escape_string($ref)."';
        ",__FILE__,__LINE__);

    // Wenn Einblendung existiert updaten
    } else {
        p4c_query("UPDATE `ads_impressions_".date('Ym')."` SET
            `number_of_impressions`=number_of_impressions+1
        WHERE
            `p4c_partner_id`= '".p4c_escape_string($pid)."' AND
            `site_id`    = '".abs((int)$ads_obj->site_id)."' AND
            `timestamp` = '".date("Y-m-d")."' AND
            `url`       = '".p4c_escape_string($host)."' AND
            `referer`   = '".p4c_escape_string($ref)."';
        ",__FILE__,__LINE__);

    }
}

p4c_close(DB_HOST);
p4c_errorlog(error_get_last());
