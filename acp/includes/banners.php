<?php
 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$rs_banners = p4c_query("SELECT * FROM `ads_media`", __FILE__, __LINE__);

if (isset($_GET['delete'])) {
    $rs_banner = p4c_query("SELECT * FROM `ads_media` WHERE `file_id`='". p4c_escape_string($_GET['delete'])."' LIMIT 1;", __FILE__, __LINE__);
    if (p4c_num_rows($rs_banner) > 0) {
        $banner_obj = p4c_fetch_object($rs_banner);
        $path = ADS_PATH.'/'.ADS_DEFAULT_DIR.'/'.$banner_obj->site_id.'/'.$banner_obj->filename;
        if (p4c_query("DELETE FROM `ads_media` WHERE `file_id`='". p4c_escape_string($_GET['delete'])."' LIMIT 1;;", __FILE__, __LINE__)) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        
        $path_new_filename = ADS_PATH.'/'.ADS_DEFAULT_DIR.'/'.$banner_obj->site_id.'/'.$banner_obj->new_filename;
        if (is_file($path_new_filename)) {
            unlink($path_new_filename);
        }
    }
    header("Location: ".ACP_URL.'/Banners');
    exit;    
}

$site .= '

<div id="tabs">
    <ul>
        <li><a href="#a">Alle Banner</a></li>
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
                    },
                    
                    "data_id-string-pre": function ( a ) {
                        return a.match(/title="(.*?)"/)[1].toLowerCase();
                    },
                 
                    "data_id-string-asc": function ( a, b ) {
                        return ((a < b) ? -1 : ((a > b) ? 1 : 0));
                    },
                 
                    "data_id-string-desc": function ( a, b ) {
                        return ((a < b) ? 1 : ((a > b) ? -1 : 0));
                    }
                } );
                               
               
            	oTable = jQuery("#table_banners").dataTable({
                    "bJQueryUI": true,
                    "iDisplayLength": 50,
                    "aaSorting": [[ 3, "desc" ]],
                    //"bProcessing": true,
                    //"bDeferRender": true,
                    
                    "bProcessing": true,
                    //"bServerSide": true,
                    "sAjaxSource": "'.ACP_URL.'/includes/ajax/banners.php",
                    
                    "aoColumns": [
                        {"sClass": "center", "sType": "title-string"},
                        {"sClass": "center"},
                        {"sClass": "center"},
                        {"sClass": "center"},
                        {"sClass": "center", "sType": "title-string" }
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
        
        <div class="ui-widget-header" style="padding:10px; font-size:20px;">'.p4c_num_rows($rs_banners).' Banner</div>
        <div class="ui-widget-content" style="padding:10px; border-top:none; margin-bottom:20px;">
            Hier sind alle Banner aufgelistet die Webmastern zur Verf&uuml;gung stehen.
        </div>

        <table id="table_banners" style="width:100%;">
            <thead>
                <tr>
                    <th style="width:20px;">Status</th>
                    <th style="width:200px;">Domain</th>
                    <th style="width:50px;">Typ</th>
                    <th style="width:150px;">Hochgeladen am</th>
                    <th style="width:auto;"></th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>

';


?>