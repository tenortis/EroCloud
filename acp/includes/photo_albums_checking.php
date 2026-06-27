<?php
  
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$site .= '
<div id="tabs">
    <ul>
        <li><a href="#a">Fotoalben &Uuml;bersicht</a></li>
    </ul>
    <div id="a">
        <div class="ui-widget-header" style="padding:10px; font-size:20px;">'.$count_photo_albums_checking.' Alben m&uuml;ssen gepr&uuml;ft werden</div>
        <div class="ui-widget-content" style="padding:10px; border-top:none; margin-bottom:20px;">
            Hier sind alle Fotoalben aufgelistet die gepr&uuml;ft werden m&uuml;ssen. 
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


                oTable = jQuery("#table_photo_albums").dataTable({
                    "bJQueryUI": true,
                    "iDisplayLength": 50,
                    "aaSorting": [[ 4, "asc" ]],
                    //"bProcessing": true,
                    //"bDeferRender": true,

                    "bProcessing": true,
                    //"bServerSide": true,
                    "sAjaxSource": "'.ACP_URL.'/includes/ajax/photo_albums_checking.php",

                    "bAutoWidth": false,
                    "aoColumns": [
                        {sWidth: "60px", "sClass": "center", "sType": "title-string"},
                        {sWidth: "60px", "sClass": "center"},
                        {sWidth: "120px", "sClass": "center"},
                        {sWidth: "150px", "sClass": "center"},
                        {sWidth: "150px", "sClass": "center"},
                        {sWidth: "auto", "sClass": "left"}
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


        <table id="table_photo_albums" style="width:100%;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>MID</th>
                    <th>Vorschaubilder</th>
                    <th>Darsteller</th>
                    <th>Geht online am</th>
                    <th style="text-align:left;">Albumtitel</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>
';

