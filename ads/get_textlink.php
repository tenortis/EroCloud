<?php

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

// Mit CID  => /l/[site_id]/[wmid]/[cid]
// Ohne CID => /l/[site_id]/[wmid]

if (isset($_GET['site_id']) AND isset($_GET['wmid'])) {
        
    $pid        = trim(preg_replace ( '/[^a-z0-9.-]/i', '', $_GET['wmid']));
    $site_id    = abs($_GET['site_id']);
    
    $rs_site = p4c_query("SELECT * FROM `sites` WHERE `id`='".p4c_escape_string($site_id)."' LIMIT 1;",__FILE__,__LINE__);
    
    // Prüfen ob Seite existiert - wenn nicht, zu EroCloud leten
    if (p4c_num_rows($rs_site) == 0) {
        header('Location: '.URL);
        exit;
    }
    
    $site_obj = p4c_fetch_object($rs_site);

    $home_url = 'https://'.$site_obj->domain;
    
    $rs_wmid = p4c_query("SELECT * FROM `merchants` WHERE `partner_id`='".p4c_escape_string($pid)."' LIMIT 1;",__FILE__,__LINE__);
    
    // Prüfen ob Webmaster existiert - wenn nicht, User direkt zur Zielseite leiten     
    if (p4c_num_rows($rs_wmid) == 0) {
        header('Location: '.$home_url);
        exit;
    }

    // Weiterleitung OHNE cid
    $juschu = 0;
    if ($juschu == '1') {
        $url = 'http://ad5.tv/h/'.url_shorter($home_url.'/index.php?site=1&wmid='.$pid, $ref);
    } else {
        $url = $home_url.'/?wmid='.$pid;
    }
    
    
    /**
     *  Klicks loggen
     **/
    $cid = '';

    if (isset($_GET['cid'])) {
        $cid = trim(preg_replace ( '/[^a-z0-9.-]/i', '', $_GET['cid']));

        // Wenn die Campagne existiert
        $rs_campaign_exists = p4c_query("SELECT * FROM `ads_campaigns` WHERE `campaign_id`='".p4c_escape_string($cid)."' AND `p4c_partner_id`='".p4c_escape_string($pid)."' LIMIT 1;",__FILE__,__LINE__);

        if (p4c_num_rows($rs_campaign_exists) == 0) {
            $cid = '';
        }
    }

    if (isset($_SERVER["HTTP_REFERER"]) AND !empty($_SERVER["HTTP_REFERER"])) {
        $ref = $_SERVER["HTTP_REFERER"];

        $parse_url = parse_url($ref);
        if (isset($parse_url['host']) AND !empty($parse_url['host'])) {
            $host = $parse_url['host'];
            $host = str_replace('www.', '', $host);
        } else {
            $host = $ref;
        }

    } else {
        $ref = 'none';
        $host = 'none';
    }

    // Erstelle jeden Monat eine neue Tabelle
    p4c_query("CREATE TABLE IF NOT EXISTS `ads_clicks_".date('Ym')."` (
        `id` int(12) NOT NULL auto_increment,
        `p4c_partner_id` varchar(10) NOT NULL,
        `media_id` int(15) NOT NULL default '0',
        `campaign_id` varchar(10) NOT NULL,
        `session` varchar(32) NOT NULL,
        `timestamp` datetime NOT NULL default '0000-00-00 00:00:00',
        `site_id` int(10) NOT NULL,
        `url` varchar(255) NOT NULL default '',
        `referer` varchar(500) NOT NULL default '',
        PRIMARY KEY (`id`),
        KEY `session` (`session`),
        KEY `timestamp` (`timestamp`)
    ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;",__FILE__,__LINE__);

    // Wenn Klick noch nicht existiert, logge ihn  
    $rs_check = p4c_query("SELECT `timestamp` FROM `ads_clicks_".date('Ym')."` WHERE `p4c_partner_id`='".p4c_escape_string($pid)."' AND `site_id`='".abs($site_id)."' AND `referer`='". p4c_escape_string($ref)."' AND `session`='".p4c_escape_string(session_id())."' LIMIT 1",__FILE__,__LINE__);
    if (p4c_num_rows($rs_check) == 0) {
        p4c_query("INSERT INTO `ads_clicks_".(int)date('Ym')."` SET
            `p4c_partner_id`    = '".p4c_escape_string($pid)."',
            `campaign_id`       = '".p4c_escape_string($cid)."',
            `session`           = '".p4c_escape_string(session_id())."',
            `timestamp`         = '".date("Y-m-d H:i:s")."',
            `site_id`           = '".abs($site_id)."',
            `url`               = '".p4c_escape_string($host)."',
            `referer`           = '".p4c_escape_string($ref)."'
        ",__FILE__,__LINE__);

        p4c_query("UPDATE `ads_campaigns` SET `count_clicks`=count_clicks+1 WHERE `campaign_id`='".p4c_escape_string($cid)."' AND `p4c_partner_id`='".p4c_escape_string($pid)."' LIMIT 1;",__FILE__,__LINE__);

    // Wenn Klick existiert aber aelter als 5 Stunden logge ihn als neuen klick
    } else {
        $check_obj = p4c_fetch_object($rs_check);
        if ($check_obj->timestamp < date("Y-m-d H:i:s", strtotime("-24 hours"))) {
            p4c_query("INSERT INTO `ads_clicks_".(int)date('Ym')."` SET
                `p4c_partner_id`    = '".p4c_escape_string($pid)."',
                `campaign_id`       = '".p4c_escape_string($cid)."',
                `session`           = '".p4c_escape_string(session_id())."',
                `timestamp`         = '".date("Y-m-d H:i:s")."',
                `site_id`           = '".abs($site_id)."',
                `url`               = '".p4c_escape_string($host)."',
                `referer`           = '".p4c_escape_string($ref)."'
            ",__FILE__,__LINE__);

            if (!empty($cid)) {
                p4c_query("UPDATE `ads_campaigns` SET `count_clicks`=count_clicks+1 WHERE `campaign_id`='".p4c_escape_string($cid)."' AND `p4c_partner_id`='".p4c_escape_string($pid)."' LIMIT 1;",__FILE__,__LINE__);
            }
        } 
    }

    // Weiterleitung MIT cid
    $juschu = 0;
    if ($juschu == '1') {
        #$url = 'http://ad5.tv/h/'.url_shorter($home_url.'/index.php?site=1&wmid='.$pid.'&cid='.$cid, $ref);
    } else {
        $url = $home_url.'/?wmid='.$pid.'&cid='.$cid;
    }

    
    header('Location: '.$url);
    
    
} else {
    die ('<h2>404 Fehler</h2>');    
}