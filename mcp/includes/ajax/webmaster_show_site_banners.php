<?php
 
define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

header('Content-Type: text/html; charset=utf-8');

if (is_logged_in('mcp') === false) {
    exit;
}

$merchant_id = abs($_SESSION['merchant_id']);
$site_id     = abs($_POST['site_id']);
$wmid        = strip_tags($_POST['wmid']);

if (isset($_POST['cid'])) {
    $cid = strip_tags($_POST['cid']);
}

// Site-Klasse einbinden 
include(SOURCEDIR.'/includes/klassen/site.inc.php');

$website = new Site($mysql,$site_id);

if ($website->get_var("id") == '') {
    exit;
}

$rs_ads = p4c_query("SELECT * FROM `ads_media` WHERE 
    `site_id` = '".abs($site_id)."' AND
    (`rejected`='0' OR `rejected`='3') AND 
    (`filename`!=`new_filename` OR `new_filename`='')
ORDER BY `upload_datetime` DESC;",__FILE__,__LINE__);

$site = '';

if (p4c_num_rows($rs_ads) > 0) {
    while($ads_obj = p4c_fetch_object($rs_ads)) {

        $width  = $ads_obj->width;
        $height = $ads_obj->height;
        
        $site .= '
        <div style="margin-bottom:5px;">
            <div style="display:inline-block; padding-right:50px;">Gr&ouml;&szlig;e: <b>'.$ads_obj->width.'x'.$ads_obj->height.'</b> Pixel</div>
            <div style="display:inline-block">Banner-ID: '.$ads_obj->file_id.'</div>
        </div>
        <div>';        
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
            
            $uri_cid = '';
            if (isset($cid)) { 
                $uri_cid = '/'.$cid;
            }
            
            $uri_wmid = '';
            if (isset($wmid)) { 
                $uri_wmid = '/'.$wmid;
            }
            
            $site .= '
            <div style="margin-bottom:10px; text-align:center;">
                <script src="'.ADS_URL.'/bc/'.$ads_obj->file_id.'&admin" type="text/javascript"></script>
                <ins data-tooltip="In Originalgr&ouml;&szlig;e anzeigen." class="adsbyerocloud" data-id="'.$ads_obj->file_id.'" style="display:inline-block;'.$css_width.';'.$css_height.';"></ins>
                <div><a href="javascript:;" class="show-banner-code" data-banner_id="'.$ads_obj->file_id.'">Bannercode anzeigen</a></div>
            </div>
            <div class="banner-code" data-banner_id="'.$ads_obj->file_id.'" style="display:none;">
                <textarea style="font-family:\'Source Code Pro\', monospace;width: 100%; width: -webkit-fill-available; width: -moz-available; height: 160px; margin: 0px; padding: 5px;"><script src="'.ADS_URL.'/bc/'.$ads_obj->file_id.$uri_wmid.$uri_cid.'" type="text/javascript"></script>
                <ins class="adsbyerocloud" style="display:inline-block;width:'.$ads_obj->width.'px;height:'.$ads_obj->height.'px;" data-id="'.$ads_obj->file_id.'"></ins></textarea>
            </div>
        </div>
        <div style="padding:10px 0;"><hr /></div>
        ';
    }
} else {
    $site .= 'Es stehen noch keine Banner zur Verf&uuml;gung.';
}

echo $site;

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());


?>