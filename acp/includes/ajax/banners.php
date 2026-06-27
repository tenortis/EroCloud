<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
 
define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(ACP_DIR."/common.inc.php");

if (is_logged_in('acp') === false) {
    exit;   
}

$rs_ads = p4c_query("SELECT
    `ads_media`.`file_id`,
    `ads_media`.`width`,
    `ads_media`.`height`,
    `ads_media`.`site_id`,
    `ads_media`.`type`,
    `ads_media`.`upload_datetime`,
    `ads_media`.`rejected`,
    `sites`.`domain`
FROM `ads_media` INNER JOIN `sites` ON `ads_media`.`site_id`=`sites`.`id` AND `new_filename`='' ORDER BY `rejected` DESC", __FILE__, __LINE__);

if (p4c_num_rows($rs_ads) > 0) {
    $output = array(
    	"sEcho" => 0,
    	"iTotalRecords" => p4c_num_rows($rs_ads),
    	"iTotalDisplayRecords" => 25,
    	"aaData" => array()
    );

    while($ads_obj = p4c_fetch_object($rs_ads)) {
        
        if ($ads_obj->rejected == '0') {
            $status = '<img src="'.ACP_URL.'/images/icons/on.png" alt="" title="aktiv" />';
        } else {
            $status = '<img src="'.ACP_URL.'/images/icons/off.png" alt="" title="inaktiv" />';
        }
        
        $width  = $ads_obj->width;
        $height = $ads_obj->height;
        
        $css_width  = 'width:'.$ads_obj->width.'px';
        $css_height = 'height:'.$ads_obj->height.'px';

        $banner = '';
        
        if ($width > 550) {
            $css_width = 'max-width:550px';
            $css_height = 'height:auto';
            $banner .= '<style>
                .adsbyerocloud[data-id="'.$ads_obj->file_id.'"] img {
                    '.$css_width.' !important;
                    '.$css_height.' !important;
                }
            </style>';
        } else 

        if ($height > 60) {
            $css_width = 'width:auto';
            $css_height = 'max-height:250px';
            $banner .= '<style>
                .adsbyerocloud[data-id="'.$ads_obj->file_id.'"] img {
                    '.$css_width.' !important;
                    '.$css_height.' !important;
                }
            </style>';
        }

        $banner .= '
        <div class="adsbyerocloud" title="'.$ads_obj->file_id.'" data-id="'.$ads_obj->file_id.'" style="display:inline-block; min-width:500px;'.$css_height.';">
            <a hreF="'.ADS_URL.'/b/'.$ads_obj->file_id.'" target="_blank"><img src="'.ADS_URL.'/b/'.$ads_obj->file_id.'" /></a><br />
            Banner-ID: '.$ads_obj->file_id.' - Banner l&ouml;schen <a data-tooltip="Das Banner wird sofort gel&ouml;scht." href="'.ACP_URL.'/Banner/?delete='.$ads_obj->file_id.'">X</a> klicken.
        </div>
        ';
        
        
    	$row = array();
        $row[] = $status;
        $row[] = '<a href="'.ACP_URL.'/Site/'.$ads_obj->domain.'">'.$ads_obj->domain.'</a>';
        $row[] = $ads_obj->type;
        $row[] = $ads_obj->upload_datetime;
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