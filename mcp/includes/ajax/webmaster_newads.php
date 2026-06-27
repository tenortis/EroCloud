<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
 
define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

if (is_logged_in('mcp') === false) {
    exit;   
}

$rs_ads = p4c_query("SELECT
    `ads_media`.`file_id`,
    `ads_media`.`width`,
    `ads_media`.`height`,
    `ads_media`.`site_id`,
    `ads_media`.`type`,
    `ads_media`.`upload_datetime`,
    `sites`.`domain`
FROM `ads_media` INNER JOIN `sites` ON `ads_media`.`site_id`=`sites`.`id` WHERE `new_filename`='' AND `rejected`='0' ORDER BY `upload_datetime` DESC", __FILE__, __LINE__);


if (p4c_num_rows($rs_ads) > 0) {
    $output = array(
    	"sEcho" => 0,
    	"iTotalRecords" => p4c_num_rows($rs_ads),
    	"iTotalDisplayRecords" => 25,
    	"aaData" => array()
    );

    while($ads_obj = p4c_fetch_object($rs_ads)) {
        
        $width  = $ads_obj->width;
        $height = $ads_obj->height;
        
        $css_width  = 'width:'.$ads_obj->width.'px';
        $css_height = 'height:'.$ads_obj->height.'px';
        $style = '';
        
        if ($width > 550) {
            $css_width = 'max-width:550px';
            $css_height = 'height:auto';
            $style = '<style>
                .adsbyerocloud[data-id="'.$ads_obj->file_id.'"] img {
                    '.$css_width.' !important;
                    '.$css_height.' !important;
                }
            </style>';
        } else 

        if ($height > 60) {
            $css_width = 'width:auto';
            $css_height = 'max-height:250px';
            $style = '<style>
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
        
        $banner = '
        '.$style.'
        <div style="margin-bottom:10px; text-align:center;">
            <script src="'.ADS_URL.'/bc/'.$ads_obj->file_id.'&admin" type="text/javascript"></script>
            <ins data-tooltip="In Originalgr&ouml;&szlig;e anzeigen." class="adsbyerocloud" data-id="'.$ads_obj->file_id.'" style="display:inline-block;'.$css_width.';'.$css_height.';"></ins>
            <div><a href="javascript:;" class="show-banner-code" data-banner_id="'.$ads_obj->file_id.'">Bannercode anzeigen</a></div>
        </div>
        <div class="banner-code" data-banner_id="'.$ads_obj->file_id.'" style="display:none;">
            <textarea style="font-family:\'Source Code Pro\', monospace;width: 100%; width: -webkit-fill-available; width: -moz-available; height: 100px; margin: 0px; padding: 5px;"><script src="'.ADS_URL.'/bc/'.$ads_obj->file_id.$uri_wmid.$uri_cid.'" type="text/javascript"></script>
            <ins class="adsbyerocloud" style="display:inline-block;width:'.$ads_obj->width.'px;height:'.$ads_obj->height.'px;" data-id="'.$ads_obj->file_id.'"></ins></textarea>
        </div>
        ';
                
    	$row = array();
        $row[] = '';
        $row[] = '<a href="'.MCP_URL.'/Webmaster/Ads?website='.$ads_obj->domain.'">'.$ads_obj->domain.'</a>';
        $row[] = $ads_obj->type;
        $row[] = $ads_obj->width.'x'.$ads_obj->height;
        $row[] = $banner;
        
    	$output['aaData'][] = $row;
    }

} else {
    $output = array(
    	"sEcho" => 0,
    	"iTotalRecords" => 0,
    	"iTotalDisplayRecords" => 0,
    	"aaData" => 0
    );
}

echo json_encode($output);

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());


?>