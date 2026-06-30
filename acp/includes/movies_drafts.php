<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$site .= '
<div id="tabs">
    <ul>
        <li><a href="#a">Filme in Planung</a></li>
    </ul>
    <div id="a">
        <div class="ui-widget-header" style="padding:10px; font-size:20px;">'.$count_movies_drafts.' Filme in Planung</div>
        <div class="ui-widget-content" style="padding:10px; border-top:none; margin-bottom:20px;">
            Hier sind alle Filme aufgelistet, die von Creatorn hochgeladen wurden oder sich derzeit in Vorbereitung befinden, aber noch nicht zur Pr&uuml;fung eingereicht wurden.
        </div>
        
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
                               
            	oTable = jQuery("#table_movies").dataTable({
                    "bJQueryUI": true,
                    "iDisplayLength": 50,
                    "aaSorting": [[ 0, "desc" ]],
                    
                    "bProcessing": true,
                    "sAjaxSource": "'.ACP_URL.'/includes/ajax/movies_drafts.php",
                    
                    "aoColumns": [
                        {"sClass": "center", "sWidth":"50px", "sType": "num-html"},
                        {"sClass": "center", "sWidth":"50px", "sType": "num-html"},
                        {"sClass": "center", "sWidth":"90px"}, // status badge
                        {"sClass": "center", "sWidth":"150px"}, // preview
                        {"sClass": "center", "sWidth":"120px"}, // actor
                        {"sClass": "center", "sWidth":"160px"}, // online_at / geplant für
                        {"sClass": "center", "sWidth":"100px", "sType": "num-html"}, // storage size
                        {"sClass": "center", "sWidth":"80px"}, // language
                        {"sClass": "center", "sWidth":"80px"}, // category
                        {"sClass": "left", "sWidth":"auto"} // title
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
        
        <table id="table_movies" style="width:100%;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>MID</th>
                    <th>Status</th>
                    <th>Vorschaubilder</th>
                    <th>Darsteller</th>
                    <th>Geplant f&uuml;r</th>
                    <th>Speicherplatz</th>
                    <th>Filmsprache</th>
                    <th>Hauptkat.</th>
                    <th style="text-align:left;">Filmtitel</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>';

?>
