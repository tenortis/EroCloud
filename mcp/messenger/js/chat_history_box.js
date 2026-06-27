jQuery(document).ready(function() {
    
    jQuery("#chat_history").on('scroll', function() {
        var divHeight = jQuery(this).innerHeight();
        var scrollTop = jQuery(this).scrollTop();
        scrollHeight = jQuery(this).prop('scrollHeight');

        //console.log("divHeight: "+divHeight);
        //console.log("scrollTop: "+scrollTop);
        //console.log("scrollHeight: "+scrollHeight);
        
        if (typeof(scrollTop_old) === 'undefined') {
            scrollTop_old = scrollTop;
        }
        
        if(scrollHeight > divHeight && scrollTop < scrollTop_old/4) {
            scrollTop_old = scrollHeight;

            var mess_timestamp = jQuery("#chat_history_old").find("[data-mess_timestamp]").attr("data-mess_timestamp");

            if (typeof mess_timestamp === "undefined") {
                var mess_timestamp = jQuery("#chat_history").find("[data-mess_timestamp]").attr("data-mess_timestamp");
            }

            jQuery(this).chat_history_old(mess_timestamp);

        }
    });
    
    jQuery.fn.chat_history_old = function(mess_timestamp) { 
        jQuery.ajax({
            url: mcp_url+"/Messenger/Ajax/chat.php",
            data: "remote_member_id="+chat_id+"&mess_timestamp="+mess_timestamp,
            method: "POST",
            dataType: "json",
            async: true,
            success: function (data) {

                // Chat aktualisieren wenn sich die Chat-Checksum geändert hat
                if (typeof chat_checksum == "undefined" || chat_checksum != data.chat_checksum) {
                    chat_checksum = data.chat_checksum;

                    var mess_ary = data.message;

                    for(var i in mess_ary) {
                        var mess_type   = mess_ary[i]["mess_type"];
                        var message     = mess_ary[i]["message"];

                        message = wdtEmojiBundle.render(message);

                        // Date
                        if (mess_type === 'date') {
                            jQuery("#chat_history_old").prepend("<div class=\"history_date ui-widget-header ui-corner-all\">"+message+"</div>");

                        // System message
                        } else if (mess_type === 'system') {
                            var mess_id     = mess_ary[i]["mess_id"];
                            var mess_from   = mess_ary[i]["mess_from"];
                            var mess_time   = mess_ary[i]["mess_time"];
                            var mess_timestamp = mess_ary[i]["mess_timestamp"];
                            var status      = mess_ary[i]["status"];

                            if (mess_ary[i]["message_read"] === 1) {
                                var message_read = 1;
                            }

                            var from_class;
                            var from_class_content;
                            var history_actor_triangle;
                            var history_triangle;
                            if (mess_from === 'actor') {
                                from_class = 'history_actor';
                                history_triangle = 'history_actor_triangle';
                                from_class_content = 'history_actor_content ui-corner-left ui-corner-br';

                            } else {
                                from_class = 'history_member';
                                history_triangle = 'history_member_triangle';
                                from_class_content = 'history_member_content ui-corner-right ui-corner-bl';
                                status = '&nbsp;&nbsp;';
                            }

                            jQuery("#chat_history_old").prepend(
                                "<div class=\"history_systemmessage ui-corner-all\" data-mess_timestamp=\""+mess_timestamp+"\">"+
                                    message+
                                    "<div class=\"history_status\">"+mess_time+"</div>"+
                                "</div>"
                            );

                        // Message
                        } else if (mess_type === 'message') {
                            var mess_id     = mess_ary[i]["mess_id"];
                            var mess_from   = mess_ary[i]["mess_from"];
                            var mess_time   = mess_ary[i]["mess_time"];
                            var mess_timestamp = mess_ary[i]["mess_timestamp"];
                            var status      = parseInt(mess_ary[i]["status"]);

                            if (mess_ary[i]["message_read"] === 1) {
                                var message_read = 1;
                            }

                            var from_class;
                            var from_class_content;
                            var history_actor_triangle;
                            var history_triangle;
                            if (mess_from === 'actor') {
                                from_class = 'history_actor';
                                history_triangle = 'history_actor_triangle';
                                from_class_content = 'history_actor_content ui-corner-left ui-corner-br';

                                if (status === 0) {
                                    status = '<span class="history_status_unread material-symbols-outlined" title="ungelesen">done_all</span>';
                                } else if (status === 2) {
                                    status = '<span class="history_status_unread material-symbols-outlined" title="gesendet">done</span>';
                                } else {
                                    status = '<span class="history_status_read material-symbols-outlined" title="gelesen">done_all</span>';
                                }

                            } else {
                                from_class = 'history_member';
                                history_triangle = 'history_member_triangle';
                                from_class_content = 'history_member_content ui-corner-right ui-corner-bl';
                                status = '&nbsp;&nbsp;';
                            }

                            jQuery("#chat_history_old").prepend(
                            "<div class=\""+from_class+"\" data-mess_timestamp=\""+mess_timestamp+"\">"+
                                "<div class=\""+history_triangle+"\"><div class=\""+history_triangle+"2\"></div></div>"+
                                "<div class=\"ui-widget-content "+from_class_content+"\">"+
                                    "<div style=\"padding:3px 5px; text-align:left;\">"+
                                        "<span style=\"line-height: 1.3;\">"+message+"</span>"+
                                    "</div>"+
                                    "<div class=\"history_status\">"+mess_time+" "+status+"</div>"+
                                "</div>"+
                            "</div>");

                        // Image
                        } else if (mess_type === 'image' || mess_type === 'erocloud_image') {
                            var mess_id     = mess_ary[i]["mess_id"];
                            var mess_from   = mess_ary[i]["mess_from"];
                            var mess_time   = mess_ary[i]["mess_time"];
                            var mess_timestamp = mess_ary[i]["mess_timestamp"];
                            var status      = parseInt(mess_ary[i]["status"]);

                            var from_class;
                            var from_class_content;
                            var history_actor_triangle;
                            var history_triangle;
                            if (mess_from === 'actor') {
                                from_class = 'history_actor';
                                history_triangle = 'history_actor_triangle';
                                from_class_content = 'history_actor_content ui-corner-left ui-corner-br';

                                if (status === 0) {
                                    status = '<span class="history_status_unread material-symbols-outlined" title="ungelesen">done_all</span>';
                                } else if (status === 2) {
                                    status = '<span class="history_status_unread material-symbols-outlined" title="gesendet">done</span>';
                                } else {
                                    status = '<span class="history_status_read material-symbols-outlined" title="gelesen">done_all</span>';
                                }

                            } else {
                                from_class = 'history_member';
                                history_triangle = 'history_member_triangle';
                                from_class_content = 'history_member_content ui-corner-right ui-corner-bl';
                                status = '&nbsp;&nbsp;';
                            }

                            if (mess_type === 'image') {
                                var image_url = "https://"+domain+"/"+message;
                                var messenger_image_info_url = api_url+"/MessengerImageInfo/&type=erocms&url="+domain+"/"+message;

                            } else if (mess_type === 'erocloud_image') {
                                var image_url = api_url+'/MessengerImage/'+message;
                                var messenger_image_info_url = api_url+"/MessengerImageInfo/"+message;
                            }


                            jQuery("#chat_history_old").prepend(
                            "<div class=\""+from_class+"\" data-mess_timestamp=\""+mess_timestamp+"\">"+
                                "<div class=\""+history_triangle+"\"><div class=\""+history_triangle+"2\"></div></div>"+
                                "<div class=\"ui-widget-content "+from_class_content+"\">"+
                                    "<table style=\"width:100%\">"+
                                        "<tr>"+
                                            "<td style=\"max-width:150px; height:auto;\">"+
                                                "<div class=\"ui-corner-left\" style=\"width:150px; height:120px; position:relative; overflow:hidden; line-height: 1.3;\" "+
                                                    "onclick=\"window.open('"+messenger_image_info_url+"', 'Bildinfos', 'width=350, height=500, location=no, locationbar=no, menubar=no, scrollbars=no, status=no, resizable=yes');\">"+
                                                    "<img style=\"width:100%; height:100%; object-fit: cover; cursor:pointer\" src=\""+image_url+"\" />"+
                                                "</div>"+
                                            "</td>"+
                                            "<td style=\"padding:5px; width:100%;\">"+
                                                "<div><strong>Hinweis:</strong></div>"+
                                                "Der User kann das Bild herunterladen.<br />"+
                                                "Mit einem Klick sieht er es in voller Gr&ouml;&szlig;e.<br />"+
                                                "Das Bild wird nach 30 Tagen gel&ouml;scht."+
                                                "<div style=\"box-sizing:border-box; width:100%; font-size:10px; padding-top:8px; text-align:right; color:#ccc;\">"+mess_time+" "+status+"</div>"+
                                            "</td>"+
                                        "</tr>"+
                                    "</table>"+
                                "</div>"+
                            "</div>");

                        }

                        // PDF
                        else if (mess_type === 'pdf' || mess_type === 'erocloud_pdf') {
                            var mess_id     = mess_ary[i]["mess_id"];
                            var mess_from   = mess_ary[i]["mess_from"];
                            var mess_time   = mess_ary[i]["mess_time"];
                            var mess_timestamp = mess_ary[i]["mess_timestamp"];
                            var status      = parseInt(mess_ary[i]["status"]);

                            var from_class;
                            var from_class_content;
                            var history_actor_triangle;
                            var history_triangle;
                            if (mess_from === 'actor') {
                                from_class = 'history_actor';
                                history_triangle = 'history_actor_triangle';
                                from_class_content = 'history_actor_content ui-corner-left ui-corner-br';

                                if (status === 0) {
                                    status = '<span class="history_status_unread material-symbols-outlined" title="ungelesen">done_all</span>';
                                } else if (status === 2) {
                                    status = '<span class="history_status_unread material-symbols-outlined" title="gesendet">done</span>';
                                } else {
                                    status = '<span class="history_status_read material-symbols-outlined" title="gelesen">done_all</span>';
                                }

                            } else {
                                from_class = 'history_member';
                                history_triangle = 'history_member_triangle';
                                from_class_content = 'history_member_content ui-corner-right ui-corner-bl';
                                status = '&nbsp;&nbsp;';
                            }

                            if (mess_type === 'pdf') {
                                var pdf_url = "https://"+domain+"/"+message;
                            } else if (mess_type === 'erocloud_pdf') {
                                var pdf_url = message;
                            }

                            var link = '<a target="_blank" href="'+pdf_url+'"><img src="https://demo.erocms.net/templates/default/images/icons/pdf-200.png" style="width:80px; height:80px;" title="Datei herunterladen" /></a>';

                            jQuery("#chat_history_old").prepend(
                            "<div class=\""+from_class+"\" data-mess_timestamp=\""+mess_timestamp+"\">"+
                                "<div class=\""+history_triangle+"\"><div class=\""+history_triangle+"2\"></div></div>"+
                                "<div class=\"ui-widget-content "+from_class_content+"\">"+
                                    "<table style=\"width:100%\">"+
                                        "<tr>"+
                                            "<td style=\"width:100px; height:auto;\">"+
                                                "<div class=\"ui-corner-left\" style=\"width:80px; height:80px;\">"+link+"</div>"+
                                            "</td>"+
                                            "<td style=\"padding:5px; vertical-align:middle; width:100%\">"+
                                                "<div><strong>Hinweis:</strong></div>"+
                                                "Der User kann die PDF mit einem Klick auf das Icon herunterladen. "+
                                                "Die PDF wird nach 30 Tagen automatisch gel&ouml;scht."+
                                                "<div style=\"box-sizing:border-box; width:100%; font-size:10px; padding-top:8px; text-align:right; color:#ccc;\">"+mess_time+" "+status+"</div>"+
                                            "</td>"+
                                        "</tr>"+
                                    "</table>"+
                                "</div>"+
                            "</div>");

                        }

                    }
                }
            }
        });
    }
    
    
    
})