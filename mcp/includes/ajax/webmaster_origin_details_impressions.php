<?php
 
define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

$site = '';

if (is_logged_in('mcp') === false) {
    exit;
}

$merchant = new Merchant($mysql,$_SESSION['merchant_id']);

if (!isset($_POST['month'])) {
    exit;
}
        
$month  = date("Ym", strtotime($_POST['month']));
$origin = trim(strip_tags($_POST['origin']));

// Wenn eine Kampagne angegeben wurde
if (isset($_POST['cid'])) {
    $cid = strip_tags($_POST['cid']);

    // Kampagnen-Klasse einbinden 
    include(SOURCEDIR.'/includes/klassen/ads_campaign.inc.php');

    $campaign = new Campaign($mysql,$cid);

    if ($campaign->get_var("campaign_id") == '') {
        exit;
    }
    
    $rs_impressions = p4c_query("SELECT * FROM `ads_impressions_".$month."` WHERE
        `url`='".p4c_escape_string($origin)."' AND
        `campaign_id`='".p4c_escape_string($cid)."' AND
        `p4c_partner_id`='".$merchant->partner_id()."'
    ORDER BY `timestamp` ASC", __FILE__, __LINE__);
    
} else {
    $rs_impressions = p4c_query("SELECT * FROM `ads_impressions_".$month."` WHERE
        `url`='".p4c_escape_string($origin)."' AND
        `p4c_partner_id`='".$merchant->partner_id()."'
    ORDER BY `timestamp` ASC", __FILE__, __LINE__);
}
    
if (p4c_num_rows($rs_impressions) > 0) {

    
    $site .= ' 
    <script type="text/javascript">
    // <![CDATA[
        jQuery(document).ready(function() {
            oTable = jQuery("#table_refs").dataTable({
                "iDisplayLength": 25,
                "order": [[ 0, "asc" ]],
                "bLengthChange" : false,
                "bInfo":false,  
                "bFiler":false,

                "bAutoWidth": false,
                "aoColumns": [
                    {sWidth: "120px", "className": "center"},
                    {sWidth: "100px", "className": "center"},
                    {sWidth: "auto", "className": "dt-body-left"}
                ],

                "oLanguage": {
                    "sProcessing":   "Bitte warten...",
                    "sLengthMenu":   "_MENU_ Eintr&auml;ge anzeigen",
                    "sZeroRecords":  "Keine Eintr&auml;ge vorhanden.",
                    "sInfo":         "_START_ bis _END_ von _TOTAL_ Eintr&auml;gen",
                    "sInfoEmpty":    "0 bis 0 von 0 Eintr&auml;gen",
                    "sInfoFiltered": "(gefiltert von _MAX_  Eintr&auml;gen)",
                    "sInfoPostFix":  "",
                    "sSearch":       "Suchen",
                    "sUrl":          "",
                    "oPaginate": {
                        "sFirst":    "Erster",
                        "sPrevious": "Zur&uuml;ck",
                        "sNext":     "N&auml;chster",
                        "sLast":     "Letzter"
                    }
                }

            });
        })

    // ]]>
    </script>        

    <h1 class="h4">'.$origin.'</h1>
    <div style="margin:20px 0;">
        <table id="table_refs" style="width:100%;">
            <thead>
                <tr>
                    <th style="width:100px;">Zeitpunkt</th>
                    <th style="width:100px;">Impressions</th>
                    <th style="width:110px; text-align:left;">Referer</th>                
                </tr>
            </thead>
            <tbody>';

            while($impressions_obj = p4c_fetch_object($rs_impressions)) {
                $site .= '
                <tr>
                    <td>'.$impressions_obj->timestamp.'</td>
                    <td>'.$impressions_obj->number_of_impressions.'</td>
                    <td>'.$impressions_obj->referer.' <a href="'.$impressions_obj->referer.'" target="_blank"><i class="material-symbols-outlined" style="font-size: 18px; vertical-align: sub;">open_in_new</i></td>
                </tr>
                ';
            }
            echo '
            </tbody>
        </table>
    </div>';
   
}

echo $site;

// Garbage Collection
p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

