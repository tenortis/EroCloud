<?php

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");


if (!isset($_GET['file_id'])) {
    echo 'document.write(\'Es ist ein Fehler aufgetreten.\');';
    exit;
}

// URL mit CID  => /l/[site_id]/[wmid]/[cid]
// URL ohne CID => /l/[site_id]/[wmid]


// Prüfen ob Kampagnen-ID angegeben wurde
$cid = '';
if (isset($_GET['cid'])) {
    
    $cid = '/'.trim(preg_replace ( '/[^a-z0-9.-]/i', '', $_GET['cid']));
    $cid_sql = trim(preg_replace ( '/[^a-z0-9.-]/i', '', $_GET['cid']));
    
    // Prüfen ob Kampagne existiert
    $rs_campaign_exists = p4c_query("SELECT `ads_campaigns`.*, `sites`.`domain`, `sites`.`is_eroads_active`  FROM `ads_campaigns` INNER JOIN `sites` ON `ads_campaigns`.`site_id`=`sites`.`id` WHERE `ads_campaigns`.`campaign_id`='".p4c_escape_string($cid_sql)."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_campaign_exists) == 0) {
        echo 'document.write(\'advertising info: cid:'.$cid_sql.' not exists\');';
        exit;
    }
  
    $campaign_obj = p4c_fetch_object($rs_campaign_exists);

    // Prüfen ob Seite aktiv ist. Wenn nicht, hier abbrechen
    if ($campaign_obj->is_eroads_active == 0) {
        echo 'document.write(\'advertising info: cid:'.$cid_sql.' error\');';
        exit;
    }
        
} 


// Bannercode erstellen
$file_id = p4c_escape_string($_GET['file_id']);

$rs_ads = p4c_query("SELECT * FROM `ads_media` WHERE
    `file_id` = '".p4c_escape_string($file_id)."'
LIMIT 1;",__FILE__,__LINE__);

// Prüfen ob Banner existiert
if (p4c_num_rows($rs_ads) == 0) {

    if (!isset($_GET['cid'])) {
        exit;
    }

    $wmid = '';
    if (isset($_GET['wmid'])) { 
        $wmid = '/'.trim(preg_replace ( '/[^a-z0-9.-]/i', '', $_GET['wmid']));
    }
    
    $banner_url = $campaign_obj->reserve_value;
    $link_uri = ADS_URL.'/l/'.$ads_obj->site_id.$wmid.$cid;
    
    // Wenn Datei aus ACP oder MCP aufgerufen wird.
    // Klick auf Banner soll Bild anzeigen, nicht zur Seite weiterleiten
    if (isset($_GET['admin'])) {
        $link_uri = $banner_url;
    }
    
    
    if ($campaign_obj->reserve == 'blank') {
        exit;
    } else if ($campaign_obj->reserve == 'image') {
        $width = 'auto';
        $height = 'auto';
        
        echo '
        if (window.jQuery === undefined) {
            if(window.addEventListener) {
                window.addEventListener("load", function(){
                    var b_uri   = "'.$banner_url.'";
                    var l_uri   = "'.$link_uri.'";
                    var width   = "'.$width.'";
                    var height  = "'.$height.'";
                    var file_id = "'.$file_id.'";

                    var content = "<a href=\"'.$link_uri.'\" target=\"_blank\">"+
                        "<img src=\""+b_uri+"\" alt=\"\" title=\"\" style=\"border:none; display:block; width:"+width+"; height:"+height+";\" />"+
                    "</a>";

                    document.querySelector(".adsbyerocloud[data-id=\'"+file_id+"\']").innerHTML = content;                    

                })
            } else {
                var b_uri   = "'.$banner_url.'";
                var l_uri   = "'.$link_uri.'";
                var width   = "'.$width.'";
                var height  = "'.$height.'";
                var file_id = "'.$file_id.'";

                var content = "<a href=\"'.$link_uri.'\" target=\"_blank\">"+
                    "<img src=\""+b_uri+"\" alt=\"\" title=\"\" style=\"border:none; display:block; width:"+width+"; height:"+height+";\" />"+
                "</a>";

                document.querySelector(".adsbyerocloud[data-id=\'"+file_id+"\']").innerHTML = content;                    

            }
        } else {
            jQuery("[data-id=\''.$ads_obj->file_id.'\']").ready(function() {
                var b_uri   = "'.$banner_url.'";
                var l_uri   = "'.$link_uri.'";
                var width   = "'.$width.'";
                var height  = "'.$height.'";
                var file_id = "'.$file_id.'";

                var content = "<a href=\"'.$link_uri.'\" target=\"_blank\">"+
                    "<img src=\""+b_uri+"\" alt=\"\" title=\"\" style=\"border:none; display:block; width:"+width+"; height:"+height+";\" />"+
                "</a>";

                document.querySelector(".adsbyerocloud[data-id=\'"+file_id+"\']").innerHTML = content;                    
            })
        }
        ';
    } else if ($campaign_obj->reserve == 'color') {
        
        echo '
        if (window.jQuery === undefined) {
            if(window.addEventListener) {
                window.addEventListener("load", function(){
                    var b_uri   = "'.$banner_url.'";
                    var l_uri   = "'.$link_uri.'";
                    var width   = "100%";
                    var height  = "100%";
                    var file_id = "'.$file_id.'";
                    var data = document.querySelector(".adsbyerocloud[data-id=\'"+file_id+"\']");
                    var content = "<div style=\"width:"+width+"; height:"+height+";background-color:'.$campaign_obj->reserve_value.'\"></div>";
                    document.querySelector(".adsbyerocloud[data-id=\'"+file_id+"\']").innerHTML = content;                    
                })
            } else {
                var b_uri   = "'.$banner_url.'";
                var l_uri   = "'.$link_uri.'";
                var width   = "100%";
                var height  = "100%";
                var file_id = "'.$file_id.'";
                var data = document.querySelector(".adsbyerocloud[data-id=\'"+file_id+"\']");
                var content = "<div style=\"width:"+width+"; height:"+height+";background-color:'.$campaign_obj->reserve_value.'\"></div>";
                document.querySelector(".adsbyerocloud[data-id=\'"+file_id+"\']").innerHTML = content;                    
            }
        } else {
            jQuery("[data-id=\''.$ads_obj->file_id.'\']").ready(function() {
                var b_uri   = "'.$banner_url.'";
                var l_uri   = "'.$link_uri.'";
                var width   = "100%";
                var height  = "100%";
                var file_id = "'.$file_id.'";
                var data = document.querySelector(".adsbyerocloud[data-id=\'"+file_id+"\']");
                var content = "<div style=\"width:"+width+"; height:"+height+";background-color:'.$campaign_obj->reserve_value.'\"></div>";
                document.querySelector(".adsbyerocloud[data-id=\'"+file_id+"\']").innerHTML = content;                    
            })
        }
        ';
    } else {
        exit;
    }
    
} else {
    $ads_obj = p4c_fetch_object($rs_ads);    
    
    $file_id = $ads_obj->file_id;
    
    $width = $ads_obj->width.'px';
    $height = $ads_obj->height.'px';

    $wmid = '';
    if (isset($_GET['wmid'])) { 
        $wmid = '/'.trim(preg_replace ( '/[^a-z0-9.-]/i', '', $_GET['wmid']));
    }
    
    $banner_url = ADS_URL.'/b/'.$file_id.$wmid.$cid;
    $link_uri = ADS_URL.'/l/'.$ads_obj->site_id.$wmid.$cid;
    
    // Wenn Datei aus ACP oder MCP aufgerufen wird.
    // Klick auf Banner soll Bild anzeigen, nicht zur Seite weiterleiten
    if (isset($_GET['admin'])) {
        $link_uri = $banner_url;
    }
    
    echo '

    if (window.jQuery === undefined) {
        if(window.addEventListener) {
            window.addEventListener("load", function(){
                var b_uri   = "'.$banner_url.'";
                var l_uri   = "'.$link_uri.'";
                var width   = "'.$width.'";
                var height  = "'.$height.'";
                var file_id = "'.$file_id.'";

                var content = "<a href=\""+l_uri+"\" target=\"_blank\">"+
                    "<img src=\""+b_uri+"\" alt=\"\" title=\"\" style=\"border:none; display:block; width:"+width+"; height:"+height+";\" />"+
                "</a>";

                document.querySelector(".adsbyerocloud[data-id=\'"+file_id+"\']").innerHTML = content;                    

            })
        } else {
            var b_uri   = "'.$banner_url.'";
            var l_uri   = "'.$link_uri.'";
            var width   = "'.$width.'";
            var height  = "'.$height.'";
            var file_id = "'.$file_id.'";
                
            var content = "<a href=\""+l_uri+"\" target=\"_blank\">"+
                "<img src=\""+b_uri+"\" alt=\"\" title=\"\" style=\"border:none; display:block; width:"+width+"; height:"+height+";\" />"+
            "</a>";

            document.querySelector(".adsbyerocloud[data-id=\'"+file_id+"\']").innerHTML = content;                    

        }
    } else {
        jQuery(".adsbyerocloud[data-id=\'"+file_id+"\']").ready(function() {
            var b_uri   = "'.$banner_url.'";
            var l_uri   = "'.$link_uri.'";
            var width   = "'.$width.'";
            var height  = "'.$height.'";
            var file_id = "'.$file_id.'";

            var content = "<a href=\""+l_uri+"\" target=\"_blank\">"+
                "<img src=\""+b_uri+"\" alt=\"\" title=\"\" style=\"border:none; display:block; width:"+width+"; height:"+height+";\" />"+
            "</a>";

            if (jQuery(".adsbyerocloud[data-id=\'"+file_id+"\']").length ) {
                document.querySelector(".adsbyerocloud[data-id=\'"+file_id+"\']").innerHTML = content;                    
            } else {
                console.log("EroAds Error: Selector not found on page: .adsbyerocloud[data-id=\'"+file_id+"\']");
            }

        })
    }


    ';  
}



 





    
