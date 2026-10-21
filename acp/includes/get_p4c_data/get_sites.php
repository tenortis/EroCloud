<?php

define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(ACP_DIR."/common.inc.php");

if (is_logged_in('acp') === false) {
    exit;   
}

// Config für P4C-API einbinden
include("config.inc.php");


/** Check Login **/
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, Pay4Coins_API_URL.'/remote/index.php');
curl_setopt($ch, CURLOPT_POSTFIELDS, 'api_name=get_sites&api_key='.$apikey);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, PROJECTNAME." - ".COMPANYNAME." - ".URL);
$data = curl_exec($ch);
curl_close($ch);

$sites_ary = json_decode($data, true);

foreach($sites_ary as $domain => $site_ary) {
    
    $rs_check_domain_exists = p4c_query("SELECT * FROM `sites` WHERE `p4c_shop_id`='".abs((int)$site_ary['site_id'])."' LIMIT 1;",__FILE__,__LINE__); 
    $site_exists = p4c_num_rows($rs_check_domain_exists); 
    
    // Alle Seiten importieren die aktiv (blocked=0) sind
    if ($site_ary['blocked'] == 0) {
        // Hinzufügen
        if ($site_exists == 0) {
            p4c_query("INSERT INTO `sites` SET
                `p4c_shop_id`   = '".abs((int)$site_ary['site_id'])."',
                `partner_id`    = '".p4c_escape_string($site_ary['partner_id'])."',
                `domain`        = '".p4c_escape_string($domain)."',
                `eroads_url`    = '".p4c_escape_string($site_ary['eroads_url'])."',
                `status`        = '1',
                `is_eroads_active`='".abs((int)$site_ary['is_eroads_active'])."',
                `webmaster_commision`   = '".abs((float)$site_ary['webmaster_commision'])."',
                `number_of_articles`    = '".abs((int)$site_ary['number_of_articles'])."',
                `number_of_movies`      = '".abs((int)$site_ary['number_of_movies'])."',
                `number_of_photoalbums` = '".abs((int)$site_ary['number_of_photoalbums'])."',
                `last_update_timestamp` = '".date("Y-m-d H:i:s")."',
                `shop_system` = '".p4c_escape_string($site_ary['shop_system'])."'
            ",__FILE__,__LINE__);

        // Aktualisieren
        } else {
            p4c_query("UPDATE `sites` SET
                `partner_id`            = '". p4c_escape_string($site_ary['partner_id'])."',
                `eroads_url`    = '".p4c_escape_string($site_ary['eroads_url'])."',
                `status`                = '1',
                `is_eroads_active`      = '".abs((int)$site_ary['is_eroads_active'])."',
                `webmaster_commision`   = '".abs((float)$site_ary['webmaster_commision'])."',
                `number_of_articles`    = '".abs((int)$site_ary['number_of_articles'])."',
                `number_of_movies`      = '".abs((int)$site_ary['number_of_movies'])."',
                `number_of_photoalbums` = '".abs((int)$site_ary['number_of_photoalbums'])."',
                `last_update_timestamp` = '".date("Y-m-d H:i:s")."',
                `shop_system` = '".p4c_escape_string($site_ary['shop_system'])."'
            WHERE 
                `p4c_shop_id`= '". abs((int)$site_ary['site_id'])."'
            LIMIT 1;",__FILE__,__LINE__);
        }
        
    // alle Seiten aktualisieren die nicht mehr aktiv sind
    } else {
        if ($site_exists == 1) {
            p4c_query("UPDATE `sites` SET
                `partner_id`= '". p4c_escape_string($site_ary['partner_id'])."',
                `eroads_url`    = '".p4c_escape_string($site_ary['eroads_url'])."',
                `status`    = '0',
                `is_eroads_active`='0',
                `webmaster_commision`='".abs((float)$site_ary['webmaster_commision'])."',
                `number_of_articles`    = '".abs((int)$site_ary['number_of_articles'])."',
                `number_of_movies`      = '".abs((int)$site_ary['number_of_movies'])."',
                `number_of_photoalbums` = '".abs((int)$site_ary['number_of_photoalbums'])."',
                `last_update_timestamp` = '".date("Y-m-d H:i:s")."',
                `shop_system` = '".p4c_escape_string($site_ary['shop_system'])."'
            WHERE 
                `p4c_shop_id`= '". abs((int)$site_ary['site_id'])."'
            LIMIT 1;",__FILE__,__LINE__);

            // Wenn Seite nicht mehr Aktiv
            $rs_user_tracking_sites = p4c_query("SELECT * FROM `user_tracking_sites` WHERE `url` LIKE 'https://".$domain."%' OR `url` LIKE 'http://".$domain."%';",__FILE__,__LINE__);
            if (p4c_num_rows($rs_user_tracking_sites) > 0) {
                $sites_obj = p4c_fetch_object($rs_user_tracking_sites);
                p4c_query("DELETE FROM `user_tracking_website_access` WHERE `url_id`='".p4c_escape_string($sites_obj->url_id)."';", __FILE__,__LINE__);
                p4c_query("DELETE FROM `user_tracking_sites` WHERE `id`='".$sites_obj->id."';",__FILE__,__LINE__);                                
            }
        }
    }
    
    // Wenn Domainname sich geändert hat
    if ($site_exists == 1) {
        $site_obj = p4c_fetch_object($rs_check_domain_exists);
        if ($site_obj->domain != trim($domain)) {
            p4c_query("UPDATE `sites` SET               `domain` = '".p4c_escape_string($domain)."' WHERE `p4c_shop_id`= '". abs((int)$site_ary['site_id'])."' LIMIT 1;",__FILE__,__LINE__);
            p4c_query("UPDATE `actor_member_info` SET   `domain` = '".p4c_escape_string($domain)."' WHERE `p4c_shop_id`= '". abs((int)$site_ary['site_id'])."';",__FILE__,__LINE__);
            p4c_query("UPDATE `messenger_sync` SET      `domain` = '".p4c_escape_string($domain)."' WHERE `p4c_shop_id`= '". abs((int)$site_ary['site_id'])."';",__FILE__,__LINE__);
            
        }
    }
    
}

/*
if (array_key_exists('amateurfick.tv', $site_ary)) {
    echo $site_ary['amateurfick.tv']['partner_id'];
}
*/
