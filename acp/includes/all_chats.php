<?php


if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$rs_count_messages = p4c_query("SELECT COUNT(*) FROM `chat_messages_history` LIMIT 5000;", __FILE__,__LINE__);
$count_messanges = p4c_num_rows($rs_count_messages);

// Site-Klasse einbinden 
include_once(SOURCEDIR.'/includes/klassen/site.inc.php');

$site .= '
<div class="ui-widget-header" style="padding:10px; font-size:20px; margin-bottom:20px;">Die letzten 5000 Nachrichten</div>

<div id="tabs">
    <ul>
        <li><a href="#actors">einzelne Nachrichten</a></li>
    </ul>
   
    <div id="actors">
        
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
                               
               
            	oTable = jQuery("#table_actors").dataTable({
                    "bJQueryUI": true,
                    "iDisplayLength": 100,
                    "aaSorting": [[ 4, "desc" ]],
                    //"bProcessing": true,
                    //"bDeferRender": true,
                    
                    "bProcessing": true,
                    //"bServerSide": true,
                    "sAjaxSource": "'.ACP_URL.'/includes/ajax/all_chats.php",
                    "aoColumns": [
                        {"sClass": "center"},
                        {"sClass": "center"},
                        {"sClass": "center"},
                        {"sClass": "center"},
                        {"sClass": "center"},
                        {"sClass": "center"},
                        {"sClass": "left"}
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

        <table id="table_actors" style="width:100%;">
            <thead>
                <tr>
                    <th style="width:20px;">Chat-ID</th>
                    <th style="width:150px;">Darsteller</th>
                    <th style="width:90px;">User</th>
                    <th style="width:200px;">Website</th>
                    <th style="width:150px;">gesendet am</th>
                    <th style="width:80px;">Preis/ct</th>
                    <th style="width:auto;">Nachricht</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>

    </div>

</div>        
';
