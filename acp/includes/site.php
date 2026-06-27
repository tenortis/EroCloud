<?php

 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

if (!in_array('all', $_SESSION['employee_access_area']) AND !in_array('website', $_SESSION['employee_access_area'])) {
    header('Location: '.ACP_URL);
    exit;        
}


if (is_numeric($_GET['id'])) {
    $site_id = abs($_GET['id']);
} else {
    $site_id = p4c_escape_string($_GET['id']);
}

// Site-Klasse einbinden 
include(SOURCEDIR.'/includes/klassen/site.inc.php');

$website = new Site($mysql,$site_id);

if ($website->get_var("id") == '') {
    echo 'Diese Webseite existiert nicht.';
    exit;
}

$s['id'] = $website->get_var("id");
$s['partner_id'] = $website->get_var("partner_id");
$s['p4c_shop_id'] = $website->get_var("p4c_shop_id");
$s['domain'] = $website->get_var("domain");
$s['eroads_url'] = $website->get_var("eroads_url");
$s['is_eroads_active'] = $website->get_var("is_eroads_active");
$s['webmaster_commision'] = $website->get_var("webmaster_commision");

$merchant = new Merchant($mysql,$s['partner_id']);
if ($merchant->id() == '') {
    echo 'Dieser Merchant existiert nicht';
    exit;
}
$merchant_id = $merchant->id();


if (isset($_GET['saved'])) {
    $site .= '
    <script type="text/javascript">
    // <![CDATA[
    	jQuery(document).ready(function() {
            jQuery("#tabs").tabs("option", "active", '.$_GET['saved'].');';
    
            if (isset($_GET['saved']) AND $_GET['saved'] == '2') {
                $site .= '
                jQuery(".msg_saved_settings").show();
                setTimeout(function() {jQuery(".msg_saved_settings").fadeToggle("slow", "linear");}, 4000);
                ';
            }
            
            $site .= ' 
        })
    // ]]>
    </script>';
}

if ($s['is_eroads_active'] == 1) {
    $is_eroads_active = '<span style="color:#008000;">Nimmt am Webmasterprogramm teil.</span>';
} else {
    $is_eroads_active = '<span style="color:#ff4500;">Nimmt nicht Webmasterprogramm teil.</span>';
}

$rs_ads = p4c_query("SELECT * FROM `ads_media` WHERE 
    `site_id` = '".abs($s['id'])."'
ORDER BY `upload_datetime` DESC;",__FILE__,__LINE__);

$number_of_banners = p4c_num_rows($rs_ads);

$site .= '
<div class="ui-widget-header" style="padding:10px; font-size:20px; margin-bottom:20px;">'.$s['domain'].'</div>

<div id="tabs">
    <ul>
        <li><a href="#infos">Infos zur Webseite</a></li>
        <li><a href="#banners">Banner ('.$number_of_banners.')</a></li>
    </ul>

    <div id="infos">
        <table>
            <tbody>
                <tr>
                    <td style="width:500px; vertical-align:top;">

                        <div class="ui-widget-header" style="padding:5px 10px;">Infos</div>
                        <div class="ui-widget-content" style="padding:10px; border-top:none;">
                        
                            <div class="edit_content" style="margin-bottom:8px; font-size:14px;">'.$is_eroads_active.'</div>
                            ';
                            if ($s['is_eroads_active'] == 1) {
                                $site .= '
                                <div class="edit_title">Webmasterprovision:</div>
                                <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$s['webmaster_commision'].'%" readonly disabled="disabled" /></div>
                                ';
                            }
                            
                            $site .= '
                            <div class="edit_title">Domain:</div>
                            <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$s['domain'].'" readonly disabled="disabled" /></div>

                            <div class="edit_title">EroAds-URL (<a href="'.$s['eroads_url'].'?apikey='.$merchant->api_key('aes_decrypt').'&date='.date("Y-m-d").'" target="_blank">API aufrufen</a>):</div>
                            <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$s['eroads_url'].'" readonly disabled="disabled" /></div>

                            <div class="edit_title">ID:</div>
                            <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$s['id'].'" readonly disabled="disabled" /></div>

                            <div class="edit_title">PID (Pay4Coins-Partner-ID - <a href="'.ACP_URL.'/Haendler/'.$s['partner_id'].'" target="_blank">Merchant anzeigen</a>):</div>
                            <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$s['partner_id'].'" readonly disabled="disabled" /></div>

                            <div class="edit_title">Pay4Coins-Shop-ID - <a href="'.Pay4Coins_ACP_URL.'/Shop/'.$s['p4c_shop_id'].'" target="_blank">Shop in Pay4Coins aufrufen</a>:</div>
                            <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$s['p4c_shop_id'].'" readonly disabled="disabled" /></div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div id="banners">';

        if ($number_of_banners > 0) {
            $site .= ' 

            <center><table style="width:100%; max-width:1200px;">';
            while($ads_obj = p4c_fetch_object($rs_ads)) {

                $width  = $ads_obj->width;
                $height = $ads_obj->height;

                $site .= '
                <tr>
                    <td style="width:412px; text-align:center; vertical-align:top;">
                        <div>Banner-ID: '.$ads_obj->file_id.'</div>
                        <div>Gr&ouml;&szlig;e: '.$ads_obj->width.' x '.$ads_obj->height.' Pixel</div>
                        <div>Hochgeladen am: '.date("Y-m-d H:i", strtotime($ads_obj->upload_datetime)).'</div>
                    </td>
                    <td style="width:auto; text-align:center; vertical-align:top;">';

                        $css_width  = 'width:'.$ads_obj->width.'px';
                        $css_height = 'height:'.$ads_obj->height.'px';

                        if ($width > 550) {
                            $css_width = 'max-width:550px';
                            $css_height = 'height:auto';
                            $site .= '<style>
                                .adsbyerocloud[data-id="'.$ads_obj->file_id.'"] img {
                                    '.$css_width.' !important;
                                    '.$css_height.' !important;
                                }
                            </style>';
                        } else 

                        if ($height > 60) {
                            $css_width = 'width:auto';
                            $css_height = 'max-height:250px';
                            $site .= '<style>
                                .adsbyerocloud[data-id="'.$ads_obj->file_id.'"] img {
                                    '.$css_width.' !important;
                                    '.$css_height.' !important;
                                }
                            </style>';
                        }

                        $site .= '
                        <div style="margin-bottom:10px;">
                            <script src="'.ADS_URL.'/bc/'.$ads_obj->file_id.'" type="text/javascript"></script>
                            <div data-tooltip="In Originalgr&ouml;&szlig;e anzeigen." class="adsbyerocloud" data-id="'.$ads_obj->file_id.'" style="display:inline-block;'.$css_width.';'.$css_height.';"></div>
                        </div>
                    </td>
                </tr>
                <tr><td colspan="2" style="padding:10px;"><hr /></td></tr>
                ';
            }
            $site .= '
            </table></center>
            ';
        }
        $site .= '
    </div>
</div>        
';
