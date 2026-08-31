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


// Site-Klasse einbinden 
include(SOURCEDIR.'/includes/klassen/site.inc.php');

$merchant = new Merchant($mysql,$_SESSION['merchant_id']);

$wmid = $merchant->partner_id();

$site .= '
<div style="min-width:700px; width:min-content;">
    <h1 class="h4">Werbemittel</h1>
    
    <p>
        Du kannst Werbemittel auch nutzen ohnen eine Kampagne zu erstellen.<br />
        Willst du ein Banner einer Kampagne zuweisen, w&auml;hle bitte
        unter <a href="'.MCP_URL.'/Webmaster/Ads">Kunden werben</a> den Tab "Websites", suche die etsprechende Website herauas und klicke "Kampagne erstellen."
    </p>
    
    <script type="text/javascript">
        jQuery(document).ready(function() {

            jQuery.extend( jQuery.fn.dataTableExt.oSort, {
                "numeric-comma-pre": function ( a ) {
                    var x = (a == "-") ? 0 : a.replace( /,/, "." );
                    return parseFloat( x );
                },

                "numeric-comma-asc": function ( a, b ) {
                    return ((a < b) ? -1 : ((a > b) ? 1 : 0));
                },

                "numeric-comma-desc": function ( a, b ) {
                    return ((a < b) ? 1 : ((a > b) ? -1 : 0));
                },

                "num-html-pre": function ( a ) {
                    var x = String(a).replace( /<[\s\S]*?>/g, "" );
                    return parseFloat( x );
                },

                "num-html-asc": function ( a, b ) {
                    return ((a < b) ? -1 : ((a > b) ? 1 : 0));
                },

                "num-html-desc": function ( a, b ) {
                    return ((a < b) ? 1 : ((a > b) ? -1 : 0));
                },

                "title-string-pre": function ( a ) {
                    return a.match(/title="(.*?)"/)[1].toLowerCase();
                },

                "title-string-asc": function ( a, b ) {
                    return ((a < b) ? -1 : ((a > b) ? 1 : 0));
                },

                "title-string-desc": function ( a, b ) {
                    return ((a < b) ? 1 : ((a > b) ? -1 : 0));
                }
            } );

            jQuery("table #banners").DataTable({
                "iDisplayLength": 25,
                "order": [[ 4, "asc" ]],
                "bLengthChange" : false,
                "bFiler":false,
                "sAjaxSource": "'.MCP_URL.'/includes/ajax/webmaster_newads.php",
                "columns": [
                    {"className": "center"},
                    {"className": "dt-body-left"},
                    {"className": "center"},
                    {"className": "center"},
                    {"className": "center"}
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
                },
                
                "fnInitComplete": function(settings, json) {
                    jQuery(".show-banner-code").click(function(){
                        var banner_id = jQuery(this).attr("data-banner_id");
                        jQuery(\'.banner-code[data-banner_id="\'+banner_id+\'"]\').toggle();
                    })
                }
            });
        })
    </script>

    <div>
        <div class="info_box" style="padding:5px 10px; margin:0 0 10px 0;">F&uuml;r eine bessere &Uuml;bersicht werden die Banner hier verkleinert angezeigt.<br />Klicke ein Banner um es in originalgr&ouml;&szlig;e zu sehen.</div>
        <div class="ui-widget-content ads" style="position:relative; padding: 10px; margin-bottom:20px;">

        <table id="banners" class="display" style="width:100%;">
            <thead>
                <tr>
                    <th style="vertical-align:top;">Neu</th>
                    <th style="text-align:left; vertical-align:top;">Website</th>
                    <th style="text-align:left; vertical-align:top;">Type</th>
                    <th style="vertical-align:top;">Gr&ouml;&szlig;e</th>
                    <th style="vertical-align:top;">Banner</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        </div>
    </div>
    
</div>';


}
?>