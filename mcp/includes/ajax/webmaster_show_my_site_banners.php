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

// Site-Klasse einbinden 
include(SOURCEDIR.'/includes/klassen/site.inc.php');

$website = new Site($mysql,$site_id);

if ($website->get_var("id") == '') {
    exit;
}

$rs_ads = p4c_query("SELECT * FROM `ads_media` WHERE 
    `site_id` = '".abs($site_id)."'
ORDER BY `upload_datetime` DESC;",__FILE__,__LINE__);

$site = '';

if (p4c_num_rows($rs_ads) == 0) {
    $site .= 'Es wurde noch kein Banner hochgeladen.';
} else {
    $site .= ' 
    <script>
        jQuery(document).ready(function() {
            jQuery(".update_banner").click(function(){
                var file_id = jQuery(this).attr("data-id");

                jQuery("#my_banners_popup_content").html("");
                jQuery(".my_banners_popup").hide();
                jQuery(".my_banner_update_popup").show();

                jQuery.ajax({
                    url: "'.MCP_URL.'/includes/overlays/webmaster_my_banner_update.php",
                    data: "site_id='.$site_id.'&file_id="+file_id,
                    method: "POST",
                    dataType: "html",
                    async: true,
                    success: function (data) {
                        jQuery("#my_banner_update_popup_content").html(data);
                    }
                })

            })
        })
    </script>


    <center><table style="width:100%; max-width:1200px;">';
    while($ads_obj = p4c_fetch_object($rs_ads)) {

        $width  = $ads_obj->width;
        $height = $ads_obj->height;
        
        $site .= '
        <tr>
            <td style="width:412px; text-align:center; vertical-align:top;">
                <div>Banner-ID: '.$ads_obj->file_id.'</div>
                <div>Hochgeladen am: '.date("Y-m-d H:i", strtotime($ads_obj->upload_datetime)).'</div>
                <div>Gr&ouml;&szlig;e: <b>'.$ads_obj->width.' x '.$ads_obj->height.' Pixel</b> (H&ouml;he x Breite)</div>
                <div>Datei-Type: <b>'.$ads_obj->type.'</b></div>
                <div><a href="javascript:;" class="update_banner" data-id="'.$ads_obj->file_id.'">Banner ersetzen</a></div>
                ';
                if ($ads_obj->new_filename != '' AND $ads_obj->rejected == '0') {
                    $site .= '<div style="color:#F44336">Das neues Banner befindet sich derzeit in der Pr&uuml;fung.</div>';
                } else if ($ads_obj->new_filename != '' AND $ads_obj->rejected == '3') {
                    $site .= '<div style="color:#F44336">Das neue Banner wurde abgelehnt.</div>';
                }

                $site .= '                 
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

echo $site;

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());


?>