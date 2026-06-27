<?php
 
 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

   
    $site .= '
    <script type="text/javascript">
    // <![CDATA[
            jQuery(document).ready(function() {
           
                jQuery.fn.checked = function(id, checked, month) {
                    var object = jQuery(this);

                    jQuery.ajax({
                        url: "'.URL.'/includes/amoredea/ajax/comments.php",
                        global: false,
                        type: "POST",
                        dataType: "text",
                        data: "checked="+checked+"&comment_id="+id+"&month="+month,
                        success: function(checked){
                            if (checked == 1) {
                                object.html("check_circle").attr("data-status", "0").attr("title", "Als ungelesen markieren").attr("onclick", "jQuery(this).checked("+id+", 0, "+month+");").removeClass("unchecked").addClass("checked");
                            } else {
                                object.html("check_circle").attr("data-status", "1").attr("title", "Als gelesen markieren").attr("onclick", "jQuery(this).checked("+id+", 1, "+month+");").removeClass("checked").addClass("unchecked");;
                            }
                        }
                    });
                }
                
                jQuery.fn.delete = function(id, month) {
                    var $this = jQuery(this);
                    jQuery.ajax({
                        url: "'.URL.'/includes/amoredea/ajax/comments.php",
                        global: false,
                        type: "POST",
                        dataType: "text",
                        data: "delete_id="+id+"&month="+month,
                        success: function(data){
                            console.log(data);
                            if (data == "ok") {
                                $this.parent().parent().hide();                                
                            }
                        }
                    });
                }
                
                jQuery.fn.reply_comment = function(comment_id, month) {
                    jQuery("#overlay").show(function() {
                        jQuery(".reply_comment_popup").show();
                        jQuery("#reply_comment_popup_content").html("");
                        jQuery.ajax({
                            url:"'.URL.'/includes/amoredea/overlays/reply_comment.php",
                            dataType:"text",
                            type: "POST",
                            data: "comment_id="+comment_id+"&month="+month,
                            success: function(data) {
                                jQuery("#reply_comment_popup_content").html(data);
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


                oTable = jQuery("#table_comments").dataTable({
                    "bJQueryUI": true,
                    "iDisplayLength": 50,
                    "aaSorting": [[ 0, "asc" ]],

                    "bProcessing": true,
                    "sAjaxSource": "'.URL.'/includes/amoredea/ajax/comments.php",


                    "bAutoWidth": false,
                    "aoColumns": [
                        {sWidth: "80px", "sClass": "center"},
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
        <h1 style="margin-bottom:10px;">User-Kommentare auf deine Filme und Fotoalben</h1>
        <div style="margin-bottom:20px;">Klicke in der Spalte "Gelesen" um den Kommentar als gelesen zu best&auml;tigen oder auf "l&ouml;schen" um ihn zu l&ouml;schen.</div>
        <table id="table_comments" style="width:100%;">
            <thead>
                <tr>
                    <th>Gelesen</th>
                    <th>ContentID</th>
                    <th>Username</th>
                    <th>Zeitpunkt</th>
                    <th>Kommentar</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>

    <div class="reply_comment_popup" style="max-width:800px; left:auto;">
        <div style="text-align:right; top:20px; right:10px; position:absolute;">
            <a href="javascript:;" class="close_overlay"><b>&#x2715;</b></a>
        </div>
        
        <div id="reply_comment_popup_content"></div>
    </div>

    ';

?>