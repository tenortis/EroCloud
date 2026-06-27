<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$rs_sites = p4c_query("SELECT * FROM `sites`", __FILE__, __LINE__);


$site .= '
    
<script>

    jQuery(document).ready(function() {
        
        jQuery("#update_sites").show().html("Webseiten werden aktualisiert...").addClass("info_box");

        jQuery.get("includes/get_p4c_data/get_sites.php")
        .done(function(status){
            if (status == "") {
                jQuery("#update_sites").show().html("Webseiten soeben aktualisiert.").removeClass("info_box").addClass("ui-state-highlight");
            } else {
                alert("Fehler beim aktualisieren der Webseiten!\n"+status);
            }
        })
    })
</script>

<div id="tabs">
    <ul>
        <li><a href="#a">Alle Webseiten</a></li>
    </ul>
    <div id="a">

        <script type="text/javascript">
        // <![CDATA[
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
                               
               
            	oTable = jQuery("#table_sites").dataTable({
                    "bJQueryUI": true,
                    "iDisplayLength": 50,
                    "aaSorting": [[ 0, "desc" ]],
                    //"bProcessing": true,
                    //"bDeferRender": true,
                    
                    "bProcessing": true,
                    //"bServerSide": true,
                    "sAjaxSource": "'.ACP_URL.'/includes/ajax/sites.php",
                    
                    "aoColumns": [
                        {"sClass": "center"},
                        {"sClass": "center", "sType": "title-string"},
                        {"sClass": "center", "sType": "title-string"},
                        {"sClass": "center"},
                        {"sClass": "center"},
                        {"sClass": "center"},
                        {"sClass": "center"},
                        {"sClass": "center"}
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
        
        <div class="ui-widget-header" style="padding:10px; font-size:20px;">'.p4c_num_rows($rs_sites).' Webseiten</div>
        <div class="ui-widget-content" style="padding:10px; border-top:none; margin-bottom:20px;">
            Hier sind alle Webseiten aufgelistet die Zugriff auf '.PROJECTNAME.' haben.
        </div>

        <div id="update_sites" style="display:none; margin-bottom:10px; padding:10px;"></div>

        <table id="table_sites" style="width:100%;">
            <thead>
                <tr>
                    <th style="width:20px;">ID</th>
                    <th style="width:90px;">Status</th>
                    <th style="width:90px;">Ads</th>
                    <th style="width:120px;">WM-Provision</th>
                    <th style="width:200px;">Domain</th>
                    <th style="width:120px;">Partner-ID</th>
                    <th style="width:80px;">Banner</th>
                    <th style="width:auto;">aktualisiert am</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>

';


?>