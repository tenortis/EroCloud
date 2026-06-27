<?php
 
 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

   
    $site .= '
    <script type="text/javascript">
    // <![CDATA[
            jQuery(document).ready(function() {
           
                jQuery.fn.mark_as_read = function(chat_id) {
                    var object = jQuery(this);

                    jQuery.ajax({
                        url: "'.URL.'/includes/amoredea/ajax/messages.php",
                        global: false,
                        type: "POST",
                        dataType: "text",
                        data: "mark_as_read=true&chat_id="+chat_id,
                        success: function(read){
                        console.log(read);
                            if (read == 1) {
                                object.html("check_circle").attr("data-status", "0").attr("title", "Als ungelesen markieren").attr("onclick", "jQuery(this).mark_as_read(\'"+chat_id+"\');").removeClass("unchecked").addClass("checked");
                            } else {
                                object.html("check_circle").attr("data-status", "1").attr("title", "Als gelesen markieren").attr("onclick", "jQuery(this).mark_as_read(\'"+chat_id+"\');").removeClass("checked").addClass("unchecked");;
                            }
                        }
                    });
                }
                
                jQuery.fn.reply_message = function(chat_id, member_id) {
                    jQuery("#overlay").show(function() {
                        jQuery(".reply_message_popup").show();
                        jQuery("#reply_message_popup_content").html("");
                        jQuery.ajax({
                            url:"'.URL.'/includes/amoredea/overlays/reply_message.php",
                            dataType:"text",
                            type: "POST",
                            data: "chat_id="+chat_id+"&member_id="+member_id,
                            success: function(data) {
                                jQuery("#reply_message_popup_content").html(data);
                            }
                        })

                    });
                };

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
                    }
                } );


                oTable = jQuery("#table_messages").dataTable({
                    "bJQueryUI": true,
                    "iDisplayLength": 50,
                    "aaSorting": [[ 0, "asc" ]],

                    "bProcessing": true,
                    "sAjaxSource": "'.URL.'/includes/amoredea/ajax/messages.php",


                    "bAutoWidth": false,
                    "aoColumns": [
                        {sWidth: "80px", "sClass": "center"},
                        {sWidth: "10px", "sClass": "center"},
                        {sWidth: "160px", "sClass": "center"},
                        {sWidth: "auto", "sClass": "left"},
                        {sWidth: "80px", "sClass": "center", "bSortable": false},
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
                            "sNext":     "N&auml;chste",
                            "sLast":     "Letzter"
                        }
                    }                            
                });
        })

    // ]]>
    </script>

    <div style="max-width:1300px;">
        <h1 style="margin-bottom:10px;">ungelesene Nachrichten von Usern</h1>
        <div style="margin-bottom:20px;">Klicke in der Spalte "Gelesen" um den Chat als gelesen zu markieren oder auf den Antworten-Pfeil um dem User zu antworten.</div>
        <table id="table_messages" style="width:100%;">
            <thead>
                <tr>
                    <th>Gelesen</th>
                    <th>Username</th>
                    <th>Zeitpunkt</th>
                    <th style="text-align:left;">Nachricht</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>

    <div class="reply_message_popup" style="max-width:800px; left:auto;">
        <div style="text-align:right; top:20px; right:10px; position:absolute;">
            <a href="javascript:;" class="close_overlay"><b>&#x2715;</b></a>
        </div>
        
        <div id="reply_message_popup_content"></div>
    </div>

    ';

?>