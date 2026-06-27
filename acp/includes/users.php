<?php
 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$rs_users = p4c_query("SELECT * FROM `members`", __FILE__, __LINE__);


$site .= '
<div id="tabs">
    <ul>
        <li><a href="#a">EroCloud-User &Uuml;bersicht</a></li>
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
                               
               
            	oTable = jQuery("#table_users").dataTable({
                    "bJQueryUI": true,
                    "iDisplayLength": 50,
                    "aaSorting": [[ 4, "desc" ]],
                    //"bProcessing": true,
                    //"bDeferRender": true,
                    
                    "bProcessing": true,
                    //"bServerSide": true,
                    "sAjaxSource": "'.ACP_URL.'/includes/ajax/users.php",
                    
                    "aoColumns": [
                        {"sClass": "center"},
                        {"sClass": "center"},
                        {"sClass": "center"},
                        {"sClass": "center"},
                        {"sClass": "center"},
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
        
        <div class="ui-widget-header" style="padding:10px; font-size:20px;">'.p4c_num_rows($rs_users).' User</div>
        <div class="ui-widget-content" style="padding:10px; border-top:none; margin-bottom:20px;">
            Hier sind alle User aufgelistet die in der Cloud aktiv sind.  
        </div>

        <table id="table_users" style="width:100%;">
            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th style="width:170px;">Username</th>
                    <th style="width:150">E-Mail-Adresse</th>
                    <th style="width:90px;">Coins</th>
                    <th style="width:150px">zuletzt online</th>
                    <th style="width:110px">P4C-Shop-ID</th>
                    <th style="width:150px">Domain</th>
                    <th style="width:100;">Chats</th>
                    <th style="width:auto;">gesendete Nachrichten</th>
                    <th style="width:100;">Device</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>

';


?>