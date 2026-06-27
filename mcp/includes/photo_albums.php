<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
 
 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$deleted_album = '';
if (isset($_GET['del']) AND $_GET['del'] == 'ok') {
    $deleted_album = '
    <div class="ui-state-error" style="padding:5px 10px; margin-bottom:10px;">
        Das Album wurde erfolgreich gel&ouml;scht.
    </div>
    ';
}

if ($count_albums == 0) {
    $site .= '
    <div style="width:600px;">
        <h1 style="margin-bottom:20px;">Meine Fotoalben in der EroCloud</h1>
        '.$deleted_album.'
        <div class="info_box">Du hast noch keine Fotoalben hochgeladen. <a href="'.MCP_URL.'/Photo-Album-Upload">Lade jetzt ein Fotoalbum hoch.</a></div>
    </div>
    ';
    
} else {
    
    $site .= '
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


            oTable = jQuery("#table_photo_albums").dataTable({
                "bJQueryUI": true,
                "iDisplayLength": 10,
                "aaSorting": [[ 1, "desc" ]],
                //"bProcessing": true,
                //"bDeferRender": true,

                "bProcessing": true,
                //"bServerSide": true,
                "sAjaxSource": "'.MCP_URL.'/includes/ajax/photo_albums.php",

                "bAutoWidth": false,
                "aoColumns": [
                    {sWidth: "8%", "sClass": "center", "sType": "title-string"},
                    {sWidth: "8%", "sClass": "center"},
                    {sWidth: "8%", "sClass": "center", "orderable": false},
                    {sWidth: "auto"},
                    {sWidth: "15%", "sClass": "center", "sType": "title-string"},
                    {sWidth: "12%", "sClass": "center"},
                    {sWidth: "12%", "sClass": "center"}
                ],
                
                "aoColumns": [
                    {sWidth: "40px", "sClass": "center", "sType": "title-string"},
                    {sWidth: "40px", "sClass": "center"},
                    {sWidth: "120px", "sClass": "center", "orderable": false},
                    {sWidth: "auto"},
                    {sWidth: "15%", "sClass": "center", "sType": "title-string"},
                    {sWidth: "8%", "sClass": "center"},
                    {sWidth: "80px", "sClass": "center"}
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

    <style type="text/css">
    <!--
        input.button.ui-widget {font-size:16px !important;}
    -->
    </style>

    <div style="max-width:1300px;">
        <h1 style="margin-bottom:20px;">Meine Fotoalben in der EroCloud</h1>
        '.$deleted_album.'
        <table id="table_photo_albums" style="width:100%;">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>ID</th>
                    <th>Vorschaubilder</th>
                    <th style="text-align:left;">Albumtitel</th>
                    <th>Darsteller</th>
                    <th>K&auml;ufe</th>
                    <th>Provision</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>


    </div>
    ';
}

?>