<?php

if (!defined('SAFE_INC')) {
    die ("Access denied!");
}

$merchant_obj = new Merchant($mysql, $_SESSION['merchant_id']);
if (!in_array($merchant_obj->partner_id(), ['ABPP2HNMDG', 'KJDJEEESAX', 'CCRVWMVD67', '9SN2XTH2TU'])) {
    $site .= '
    <div class="card border-0 shadow-sm text-center p-4 p-md-5 my-4">
        <div class="card-body">
            <div class="mb-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning rounded-circle p-4" style="width: 90px; height: 90px;">
                    <i class="bi bi-exclamation-triangle display-4"></i>
                </div>
            </div>
            <h2 class="fw-bold text-dark mb-3">Partnerprogramm eingestellt</h2>
            <p class="lead text-muted mb-4">
                Das <strong>Partnerprogramm</strong> wurde im <strong>Juli 2026</strong> eingestellt und steht nicht mehr zur Verf&uuml;gung.
            </p>
            <p class="text-secondary mb-4">
                Sollten Sie Fragen hierzu haben oder Unterst&uuml;tzung ben&ouml;tigen, wenden Sie sich bitte an unseren Support.
            </p>
            <div>
                <a href="'.MCP_URL.'/Startseite" class="btn btn-primary btn-lg shadow-sm px-4">
                    <i class="bi bi-house-door me-2"></i>Zur&uuml;ck zur Startseite
                </a>
            </div>
        </div>
    </div>';
} else {


if (!isset($_GET['domain'])) {
    header('Location: '.MCP_URL.'/Webmaster/Ads');
    exit;
}

$domain = strip_tags($_GET['domain']);

// Site-Klasse einbinden 
include(SOURCEDIR.'/includes/klassen/site.inc.php');

$website = new Site($mysql,$domain);

if ($website->get_var("id") == '') {
    header('Location: '.MCP_URL.'/Webmaster/Ads');
    exit;
}

$merchant = new Merchant($mysql,$_SESSION['merchant_id']);

$s['id'] = $website->get_var("id");
$s['domain'] = $website->get_var("domain");
$s['webmaster_commision'] = $website->get_var("webmaster_commision");

$wmid = $merchant->partner_id();

$site .= '
<div style="width:700px;">
    <h1 class="h4">Werbemittel</h1>
    
    <p>
        Du kannst die Werbemittel nutzen ohnen eine Kampagne zu erstellen.
    </p>
    
    <script type="text/javascript">
    // <![CDATA[
        jQuery(document).ready(function() {
            jQuery( "#accordion" ).accordion({
                collapsible: true,
                heightStyle: "content",
                active: false
            });   
        })
        
        function show_banners(site_id) {
            jQuery.ajax({
                url: "'.MCP_URL.'/Ajax/webmaster_show_site_banners.php",
                data: "site_id='.$s['id'].'&wmid='.$wmid.'",
                method: "POST",
                dataType: "html",
                async: true,
                success: function (data) {
                    console.log(data);
                    jQuery(".ads").html(data);

                    jQuery(".show-banner-code").click(function(){
                        var banner_id = jQuery(this).attr("data-banner_id");
                        jQuery(\'.banner-code[data-banner_id="\'+banner_id+\'"]\').toggle();
                    })

                }
            })
        }

        show_banners('.$s['id'].');

    // ]]>
    </script>
        
    <div class="ui-widget-content" style="padding:10px; margin-bottom:20px;">
        <table style="width:100%;">
            <tr>
                <td style="width:50%;">
                    <div class="edit_title">Webseite</div>
                    <div class="edit_content" style="font-size:20px; font-weight:400;">
                        '.$s['domain'].'
                    </div>
                </td>
                <!--
                <td style="width:25%">
                    <div class="edit_title">Bisher erzielte Klicks</div>
                    <div class="edit_content" style="font-size:20px; font-weight:400;">
                        X
                    </div>
                </div>
                -->
                <td style="width:50%">
                    <div class="edit_title">Ihre Provision</div>
                    <div class="edit_content" style="font-size:20px; font-weight:400;">
                        '.$s['webmaster_commision'].'%
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div id="accordion">
        <h3>URL / Textlink</h3>
        <div>
            <div class="ui-widget-header" style="padding:5px 10px; border-bottom:none;">URL</div>
            <input type="text" value="'.ADS_URL.'/l/'.$s['id'].'/'.$wmid.'" readonly style="padding:3px 5px; width:100%; width: -webkit-fill-available; width: -moz-available; font-size:15px;" />

            <div class="ui-widget-header" style="margin-top:20px; padding:5px 10px; border-bottom:none;">Textlink</div>
            <textarea readonly style="padding:3px 5px; height:50px; width:100%; width: -webkit-fill-available; width: -moz-available; font-size:15px;"><a href='.ADS_URL.'/l/'.$s['id'].'/'.$wmid.'" target="_blank">'.$s['domain'].'</a></textarea>
        </div>
        
        <h3>XML-API</h3>
        <div>
            <div><b>Informationen &uuml;ber den Darsteller</b></div>
            <input type="text" style="width:100%; padding:2px; 5px; " value="https://'.$s['domain'].'/api/open-v1/actor_infos" />
            <div>
                &Uuml;ber diese API, bekommst du informationen wie den Online-/Offlinestatus und auch Details wie Haarfarbe, K&ouml;rbchengr&ouml;&szlig;e, "&Uuml;ber mich" usw.
            </div>
        </div>

        <h3>Banner</h3>
        <div>
            <div class="info_box" style="padding:5px 10px; margin:0 0 10px 0;">F&uuml;r eine bessere &Uuml;bersicht werden die Banner hier verkleinert angezeigt.<br />Klicke ein Banner um es in originalgr&ouml;&szlig;e zu sehen.</div>
            <div class="ui-widget-content ads" style="position:relative; padding: 10px; margin-bottom:20px;"></div>
        </div>
    </div>
    
</div>';


}
?>