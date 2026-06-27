
CKEDITOR.disableAutoInline = true;

// Timestamp Date.now(); 
if (!Date.now) {
    Date.now = function() { return new Date().getTime(); };
}    
timestamp = function() {return Math.round(Date.now()/1000);}

var xhr_chat_history;
var xhr_member_infos;

function member_infos() {
   
    if (xhr_member_infos !== undefined) {
        if (xhr_member_infos.readyState > 0 && xhr_member_infos.readyState < 4) {
            xhr_member_infos.abort();
        }
    }

    xhr_member_infos = jQuery.ajax({
        url: mcp_url+"/Messenger/Ajax/member_infos.php",
        data: "remote_member_id="+chat_id,
        method: "POST",
        dataType: "json",
        async: true,
        success: function (data) {
            if (data.error) {
                if (data.error == 'reload') {
                    location.reload();
                    //console.log(data.error);
                }
            } else {
                var member_ary = data.user;

                if (member_ary["birthday"] === '') {
                    var birthday = '-';
                } else {
                    var birthday = member_ary["birthday"];
                }

                if (member_ary["online_device"] === 'mobile') {
                    var device = "phone_android";
                } else {
                    var device = "desktop_windows";
                }

                if (member_ary["online"] === 1) {
                    jQuery("#chat #chat_head #member_infos .device, #chat #chat_head #member_infos #lastonline").addClass("device_online");
                    var lastonline = 'Jetzt online.';

                } else {
                    jQuery("#chat #chat_head #member_infos .device, #chat #chat_head #member_infos #lastonline").removeClass("device_online");
                    var lastonline = member_ary["lastonline"]+" Uhr";
                }

                marked_as_unanswered = parseInt(member_ary["marked_as_unanswered"]);

                if (marked_as_unanswered === 1) {
                    jQuery("#markunanswered").addClass("markunanswered").removeClass("markanswered");
                    jQuery("#markunanswered i").attr("title", "Jetzt antworten oder hier den Chat als beantwortet markieren.").text("mail");
                } else {
                    jQuery("#markunanswered").removeClass("markunanswered").addClass("markanswered");
                    jQuery("#markunanswered i").attr("title", "Als unbeantwortet markieren.").text("mail_outline");                
                }

                jQuery("#markunanswered").hover(
                    function() {
                        if (marked_as_unanswered === 1) {
                            jQuery("#markunanswered").removeClass("markunanswered").addClass("markanswered");
                            jQuery("#markunanswered i").attr("title", "Jetzt antworten oder hier den Chat als beantwortet markieren.").text("mail_outline");
                        } else {
                            jQuery("#markunanswered").addClass("markunanswered").removeClass("markanswered");
                            jQuery("#markunanswered i").attr("title", "Als unbeantwortet markieren.").text("mail");                
                        }
                    }, function() {
                        if (marked_as_unanswered === 1) {
                            jQuery("#markunanswered").addClass("markunanswered").removeClass("markanswered");
                            jQuery("#markunanswered i").attr("title", "Jetzt antworten oder hier den Chat als beantwortet markieren.").text("mail");
                        } else {
                            jQuery("#markunanswered").removeClass("markunanswered").addClass("markanswered");
                            jQuery("#markunanswered i").attr("title", "Als unbeantwortet markieren.").text("mail_outline");                
                        }
                    }
                );

                jQuery("#chat #chat_head #member_avatar img").attr("src",member_ary["member_avatar"]);
                jQuery("#chat .username, #chat_infos .username").text(member_ary["username"]);
                jQuery("#chat #chat_head #member_infos #domain").text(member_ary["domain"]);
                jQuery("#chat #chat_head #member_infos #count_mess_from_member").text(member_ary["count_mess_from_member"]);
                jQuery("#chat #chat_head #member_infos #count_mess_from_actor").text(member_ary["count_mess_from_actor"]);
                jQuery("#chat #chat_head #member_infos #birthday").html(birthday);
                jQuery("#chat #chat_head #member_infos .device").text(device);
                jQuery("#chat #chat_head #member_infos #lastonline").text(lastonline);
                
                   
                if (typeof pn_amount == "undefined" || pn_amount != member_ary["pn_amount"]) {
                    pn_amount = member_ary["pn_amount"];
                    jQuery("#chat_infos #user_settings #pn_amount").val(pn_amount);
                }
                
                if (typeof cam_amount == "undefined" || cam_amount != member_ary["cam_amount"]) {
                    cam_amount = member_ary["cam_amount"];
                    jQuery("#chat_infos #user_settings #cam_amount").val(cam_amount);
                }
                   
                if (typeof cam2cam_amount == "undefined" || cam2cam_amount != member_ary["cam2cam_amount"]) {
                    cam2cam_amount = member_ary["cam2cam_amount"];
                    jQuery("#chat_infos #user_settings #cam2cam_amount").val(cam2cam_amount);
                }
                
                if (typeof user_notes == "undefined" || user_notes != member_ary["user_notes"]) {
                    user_notes = member_ary["user_notes"];
                    CKEDITOR.instances.edit_user_notes.setData(user_notes);
                }
                
                var actor_ary = data.actor;

                jQuery("#chat_infos #my_profil #actor_avatar img").attr("src",actor_ary["actor_avatar"]);
                jQuery("#chat_infos #my_profil #actorname .actorname").text(actor_ary["username"]);
                jQuery("#chat_infos #my_profil #actor_age").text(actor_ary["actor_age"]);
                jQuery("#chat_infos #my_profil #actor_marital_status").text(actor_ary["actor_marital_status"]);
                jQuery("#chat_infos #my_profil #actor_looking_for").html(actor_ary["actor_looking_for"]);
                jQuery("#chat_infos #my_profil #actor_interests").html(actor_ary["actor_interests"]);
                jQuery("#chat_infos #my_profil .pause, #chat_infos #my_profil .online_status").attr('data-actor-id',actor_ary["actor_id"]);
                pause_status(actor_ary["takes_a_break"],actor_ary["actor_id"]);
                online_status(actor_ary["online_status"],actor_ary["actor_id"]);
            }
        }
    });
}

function chat_history() { 
    
    if (xhr_chat_history !== undefined) {
        if (xhr_chat_history.readyState > 0 && xhr_chat_history.readyState < 4) {
            xhr_chat_history.abort();
        }
    }
    
    xhr_chat_history = jQuery.ajax({
        url: mcp_url+"/Messenger/Ajax/chat.php",
        data: "remote_member_id="+chat_id,
        method: "POST",
        dataType: "json",
        async: true,
        success: function (data) {
                        
            // Chat aktualisieren wenn sich die Chat-Checksum geändert hat
            if (typeof chat_checksum == "undefined" || chat_checksum != data.chat_checksum) {
                chat_checksum = data.chat_checksum;

                var domain = jQuery("#chat #chat_head #member_infos #domain").text();
                var chat_inhalt = jQuery("#chat_history #chat_history_new").html();
                
                jQuery("#chat_history #chat_history_new").html("");
            
                var mess_ary = data.message;
                            
                for(var i in mess_ary) {
                    var mess_type   = mess_ary[i]["mess_type"];
                    var message     = mess_ary[i]["message"];

                    message = wdtEmojiBundle.render(message);

                    // Date
                    if (mess_type === 'date') {
                        jQuery("#chat_history #chat_history_new").append("<div class=\"history_date ui-widget-header ui-corner-all\">"+message+"</div>");

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

                        jQuery("#chat_history #chat_history_new").append(
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

                        jQuery("#chat_history #chat_history_new").append(
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

                        if (mess_type === 'image') {
                            var image_url = "https://"+domain+"/"+message;
                            var messenger_image_info_url = api_url+"/MessengerImageInfo/&type=erocms&url="+domain+"/"+message;
                            
                        } else if (mess_type === 'erocloud_image') {
                            var image_url = api_url+'/MessengerImage/'+message;
                            var messenger_image_info_url = api_url+"/MessengerImageInfo/"+message;
                        }


                        jQuery("#chat_history #chat_history_new").append(
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

                        if (mess_type === 'pdf') {
                            var pdf_url = "https://"+domain+"/"+message;
                        } else if (mess_type === 'erocloud_pdf') {
                            var pdf_url = message;
                        }

                        var link = '<a target="_blank" href="'+pdf_url+'"><img src="https://demo.erocms.net/templates/default/images/icons/pdf-200.png" style="width:80px; height:80px;" title="Datei herunterladen" /></a>';
                        
                        jQuery("#chat_history #chat_history_new").append(
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

                if (typeof(message_read) !== 'undefined' && message_read === 1) {
                    jQuery(this).message_read();
                }

                // Chat erst scrollen wenn sich Inhalt verändert
                if (chat_inhalt != jQuery("#chat_history #chat_history_new").html()) {
                    var iScrollHeight = jQuery("#chat_history").prop("scrollHeight");
                    jQuery("#chat_history").prop("scrollTop", iScrollHeight);
                    var chat_inhalt = jQuery("#chat_history #chat_history_new").html();

                    jQuery('#chat_history #chat_history_new img').load(function () {
                        var iScrollHeight = jQuery("#chat_history").prop("scrollHeight");
                        jQuery("#chat_history").prop("scrollTop", iScrollHeight);
                        var chat_inhalt = jQuery("#chat_history #chat_history_new").html();
                    });
                }
            }
        }
    });
}

function pause_status(status,actor_id) {
    if (parseInt(status) === 1) {
        jQuery('.pause[data-actor-id='+actor_id+'] .pause_text').html("Pause beenden");
        jQuery('.pause[data-actor-id='+actor_id+']').removeClass("ui-state-highlight ui-state-error").
            addClass("ui-state-error").
            attr("data-pausestatus", 1).
            attr("title", "User sehen, dass du eine Pause machst. Klicke hier um deine Pause zu beenden.");
    } else {
        jQuery('.pause[data-actor-id='+actor_id+'] .pause_text').html("Pause machen");
        jQuery('.pause[data-actor-id='+actor_id+']').removeClass("ui-state-error ui-state-highlight").
            addClass("ui-state-highlight").
            attr("data-pausestatus", 0).
            attr("title", "Klicke hier, wenn du eine kurze Pause machen möchtest. Der User siehst einen Hinweis, dass du gleich zurück bist.");
    }
}

function online_status(status,actor_id) {
    if (parseInt(status) === 1) {
        jQuery('.online_status[data-actor-id='+actor_id+'] .online_status_text').html("Du bist online");
        jQuery('.online_status[data-actor-id='+actor_id+']').removeClass("ui-state-highlight ui-state-error").
            addClass("ui-state-highlight").
            attr("data-online_status", 1).
            attr("title", "Du wirst für User als online angezeigt! Klicke hier, um offline angezeigt zu werden.");
    } else {
        jQuery('.online_status[data-actor-id='+actor_id+'] .online_status_text').html("Du bist offline!");
        jQuery('.online_status[data-actor-id='+actor_id+']').removeClass("ui-state-highlight ui-state-error").
            addClass("ui-state-error").
            attr("data-online_status", 0).
            attr("title", "Du wirst für User als offline angezeigt! Klicke hier, um online angezeigt zu werden.");
    }
}

function placeCaretAtEnd(el) {
    el.focus();
    if (typeof window.getSelection != "undefined"
            && typeof document.createRange != "undefined") {
        var range = document.createRange();
        range.selectNodeContents(el);
        range.collapse(false);
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    } else if (typeof document.body.createTextRange != "undefined") {
        var textRange = document.body.createTextRange();
        textRange.moveToElementText(el);
        textRange.collapse(false);
        textRange.select();
    }
}

function getCaretPosition() {
    if (window.getSelection) {
        sel = window.getSelection();
        if (sel.getRangeAt) {
            return sel.getRangeAt(0).startOffset;
        }
    }
    return null;
}


function close_messenger_info() {
    var cssClass = ".messenger_info";
    var marginBottom = jQuery(cssClass).height();
    jQuery(cssClass).fadeIn().animate({marginBottom:-marginBottom}, 800);
    jQuery(".messenger_info_box").show();
};

function get_notification_sound() {
    jQuery.post(mcp_url+"/Messenger/Ajax/submit.php", {get_messenger_sound: 'true'})
    .done(function(status){
        if (status === "1") {
            jQuery(".notification_sound").attr("data-notification-sound-status","1").attr("title","Messenger-Sound für die aktuelle Gruppe ausschalten.");
            jQuery(".notification_sound i").html("notifications_active");
        } else {
            jQuery(".notification_sound").attr("data-notification-sound-status","0").attr("title","Messenger-Sound für die aktuelle Gruppe einschalten.");
            jQuery(".notification_sound i").html("notifications_off");
        }
    })
}

get_notification_sound();

jQuery(document).ready(function() {
       
    jQuery(".button").button();

    // jQuery-UI Tootltip
    jQuery(document).tooltip({
        tooltipClass: "mytooltip",
        items: "[data-tooltip]",
        content: function() {
            var element = jQuery(this);
            if (element.is("[data-tooltip]")) {
                var text = element.text();
                return element.attr("data-tooltip");
            }
        }
    });

    jQuery.fn.message_read = function() {
        if ( document.hasFocus() ) {
            jQuery.post(mcp_url+"/Messenger/Ajax/submit.php", {message_read: 'true', chat_id: chat_id})
            .done(function(data){
                userlist();
            })
            /*
            setTimeout( function() {

            }, 1000);
            */
        }
    };
    
    jQuery(".notification_sound").click(function(){
        var status = jQuery(this).attr("data-notification-sound-status");
       
        jQuery.ajax({
            url: mcp_url+"/Messenger/Ajax/submit.php",
            data: "set_messenger_sound="+status,
            method: "POST",
            dataType: "text",
            success: function() {
                get_notification_sound();
            }
        });
    });
    
    jQuery("#chat_infos #my_profil .ui-widget-content #edit_profil").click(function(){
        var actor_id = jQuery("#my_profil .pause").attr("data-actor-id");
        window.open(mcp_url+"/Actor/"+actor_id, '_blank');
    })
    
    jQuery(".open_home").click(function(){
        jQuery(this).close_chat();
    })
    
    jQuery.fn.close_chat = function(){
        if (typeof update_chat_history !== 'undefined') {clearInterval(update_chat_history);};
        if (typeof update_member_infos !== 'undefined') {clearInterval(update_member_infos);};

        jQuery(".userlist").removeClass("userlist_active");
        jQuery("#welcome").show();
        jQuery("#chat, #chat_infos").hide();
        
        //jQuery(this).close_all_usercams();
    }
    
    jQuery.fn.open_chat = function(e_chat_id) {
        
        jQuery(".userlist").removeClass("userlist_active");
        jQuery(this).addClass("userlist_active");
        
        jQuery("#welcome").hide();
        jQuery("#chat, #chat_infos").show();
        
        jQuery("#chat #chat_head #member_avatar img, #chat_infos #my_profil #actor_avatar img").attr("src",mcp_url+"/images/movie_poster_nopic.jpg");
        jQuery("#chat .username, #chat .actorname").text("...");
        
        jQuery("#chat_history #chat_history_old").html("");
        jQuery("#chat_history #chat_history_new").html("");

        jQuery("#chat_send_mess #message_box #textarea").focus();
        
        wdtEmojiBundle.defaults.emojiSheets = {
            "emojione": mcp_url+"/Messenger/emojiarea/sheets/sheet_emojione_64_indexed_128.png",
            "facebook": mcp_url+"/Messenger/emojiarea/sheets/sheet_fascebook_64_indexed_128.png",
            "messenger": mcp_url+"/Messenger/emojiarea/sheets/sheet_messenger_64_indexed_128.png",
            "apple"   : mcp_url+"/Messenger/emojiarea/sheets/sheet_apple_64_indexed_128.png",
            "google"  : mcp_url+"/Messenger/emojiarea/sheets/sheet_google_64_indexed_128.png",
            "twitter" : mcp_url+"/Messenger/emojiarea/sheets/sheet_twitter_64_indexed_128.png"
        };

        wdtEmojiBundle.init(".wdt-emoji-bundle-enabled");

        if (typeof e_chat_id === 'undefined' || e_chat_id === '') {
            chat_id = jQuery(this).attr("data-user-id");
        } else {
            chat_id = e_chat_id;
        }

        userlist_active = chat_id;
      
        delete chat_checksum;
      
        member_infos();
        chat_history();
        
        if (typeof update_chat_history !== 'undefined') {clearInterval(update_chat_history);};
        update_chat_history = setInterval(function() {chat_history();}, 5000);
        
        if (typeof update_member_infos !== 'undefined') {clearInterval(update_member_infos);};
        update_member_infos = setInterval(function() {member_infos();}, 10000);

        /*
        jQuery(this).get_all_usercams();
        
        if (typeof update_all_usercams !== 'undefined') {clearInterval(update_all_usercams);};
        update_all_usercams = setInterval(function() {
            jQuery(this).get_all_usercams();
        }, 5000);
        */
        /* 
         * folgendes in wdt-emoji-bundle.js, bei "wdtEmojiBundle.bindEvents = function () {" einfügen
         * */
        /*
        // Nach Einfügen von Smiley, Cursor an das Ende vom Text
        var textarea = jQuery("#chat_send_mess #message_box #textarea");
        var message = textarea.html();
        message = emojione.shortnameToImage(message);
        textarea.html(message);
        placeCaretAtEnd(document.getElementById("textarea"));
        */
       
        // send mess
        jQuery("#chat_send_mess #message_box #textarea").keyup(function(e) {

            if (typeof actor_is_typing == "undefined" || (typeof actor_is_typing != "undefined" && (parseInt(actor_is_typing)+7) < timestamp()) ) {
                actor_is_typing = timestamp();
                jQuery.post(mcp_url+"/Messenger/Ajax/submit.php", {actor_is_typing: (parseInt(actor_is_typing)+10), chat_id: chat_id});
            }

            e = e || window.event;
            var key = e.keyCode || e.which || e.charCode, shift = e.modifiers ? e.modifiers & Event.SHIFT_MASK : e.shiftKey;
            if(key === 13 && !shift){
                var message = jQuery("#chat_send_mess #message_box #textarea").html();
                message = message.replace(/<img.*?class="emojione".*?alt="(.*?)".*?>/g, '$1');
                message = emojione.toShort(message);
                
                jQuery("#chat_send_mess #message_box #textarea").html("").focus();
                // textarea leeren					
                e.preventDefault();

                jQuery.ajax({
                    url: mcp_url+"/Messenger/Ajax/send_chat_message.php",
                    global: false,
                    type: "POST",
                    data: "chat_id="+chat_id+"&message="+encodeURIComponent(message)
                }).done(function(msg) {
                    chat_history();
                }); 
                
                chat_history();
                
            }
        });
        
        jQuery("#markunanswered").click(function(){
            if (marked_as_unanswered === 1) {
                jQuery.post(mcp_url+"/Messenger/Ajax/submit.php", {marked_as_unanswered: 0, remote_member_id: chat_id}).done(function(data){
                    location.reload();
                });
            } else {
                jQuery.post(mcp_url+"/Messenger/Ajax/submit.php", {marked_as_unanswered: 1, remote_member_id: chat_id}).done(function(data){
                    location.reload();
                });               
            }
        })
        
        if(jQuery('#cambar').is(':visible')) {
            let cambar_height = 100;
            let new_chat_history_height = jQuery("#messenger_parent").outerHeight() - jQuery("#messenger_head").outerHeight() - jQuery("#chat_head").outerHeight() - jQuery("#chat_send_mess").outerHeight() - cambar_height;
            jQuery("#chat #chat_history").css({height: new_chat_history_height});
        }
    };
    
    
    jQuery("#send_file_box").draggable();
    
    jQuery("#send_file_title i").click(function() {
        jQuery("#send_file_box").hide();
    });
    
    jQuery("#send_file").click(function() {
        jQuery("#send_file_box").css({top:"50%", left:"50%"}).show();
    })

    jQuery("#submit_check_url_connect").click(function() {
        var domain = jQuery("#check_url_connect").val();
        var username = jQuery("#check_url_username").val();
        var password = jQuery("#check_url_password").val();
        var group = jQuery("#check_url_group").val();

        jQuery.ajax({
            url: mcp_url+"/Messenger/Ajax/submit.php",
            data: "check_url_connect="+domain+"&group_id="+group+"&username="+username+"&password="+password,
            method: "POST",
            dataType: "text",
            beforeSend: function( xhr ) {
                jQuery("#check_url_connect_info").show().html("<b>Pr&uuml;fe Verbindung. Bitte warten...</b>");
            }
        })
        .done(function(data) {
            jQuery("#check_url_connect_info").show().html(data);
        });
    });
    
    jQuery("#save_user_notes").click(function() {
        var text = CKEDITOR.instances.edit_user_notes.getData();

        jQuery.ajax({
            url: mcp_url+"/Messenger/Ajax/submit.php",
            data: "save_user_notes="+escape(text)+"&chat_id="+chat_id,
            method: "POST",
            dataType: "text",
            success: function(result) {
                if (result=="ok") {
                    jQuery(this).messenger_info({
                        title: "Gespeichert",
                        content: "Die Notizen zum User wurden gespeichert.",
                        autoClose: true
                    })
                }
            }
        });
    });
    
    jQuery("#save_user_settings").click(function() {
        var pn_amount   = jQuery("#chat_infos #user_settings #pn_amount").val();
        var cam_amount  = jQuery("#chat_infos #user_settings #cam_amount").val();
        var cam2cam_amount  = jQuery("#chat_infos #user_settings #cam2cam_amount").val();

        jQuery.ajax({
            url: mcp_url+"/Messenger/Ajax/submit.php",
            data: "save_user_settings="+chat_id+"&pn_amount="+pn_amount+"&cam_amount="+cam_amount+"&cam2cam_amount="+cam2cam_amount,
            method: "POST",
            dataType: "text",
            success: function(result) {
                if (result==="ok") {
                    jQuery(this).messenger_info({
                        title: "Gespeichert",
                        content: "Die Einstellungen zum User wurden gespeichert.",
                        autoClose: true
                    })
                } else {
                    jQuery(this).messenger_info({
                        title: "ACHTUNG!",
                        content: "Die Einstellungen zum User konnten nicht gespeichert werden.<br />Bitte probiere es noch einmal.",
                        autoClose: false,
                        is_error: true
                    })
                }
            }
        });
    });
    
    jQuery(".pause").click(function(){
        var actor_id = jQuery(this).attr('data-actor-id');
        var status = jQuery(this).attr("data-pausestatus");
        jQuery.ajax({
            url: mcp_url+"/Messenger/Ajax/submit.php",
            type: "POST",
            data: "pause="+status+"&actor_id="+actor_id,
            success: function(result) {
                pause_status(result,actor_id);
            }
        })
    })
    
    jQuery(".online_status").click(function(){
        var actor_id = jQuery(this).attr('data-actor-id');
        var status = jQuery(this).attr("data-online_status");
        jQuery.ajax({
            url: mcp_url+"/Messenger/Ajax/submit.php",
            type: "POST",
            data: "online_status="+status+"&actor_id="+actor_id,
            success: function(result) {
                online_status(result,actor_id);
            }
        })
    })

    
    jQuery.fn.messenger_info = function(options) {
        
        var cssClass = ".messenger_info";
        
        var title = '';
        var content = '';
        var autoClose = true;
        var is_error = false; 
        
        if (options.title !== '') {title = options.title;}
        if (options.content !== '') {content = options.content;}
        if (options.content !== '') {content = options.content;}
        if (options.autoClose === false) {autoClose = false;}
        if (options.is_error === true) {is_error = true;}
        
        jQuery(cssClass+" #messenger_info_title span").text(title);
        
        if (content != '') {
            jQuery(cssClass+" #messenger_info_content").show().html(content);
        }
        
        if (is_error === true) {
            jQuery("#messenger_info_content").addClass("error_color");
        } else {
            jQuery("#messenger_info_content").removeClass("error_color");
        }

        jQuery(".messenger_info_box").show();

        var marginBottom = jQuery(cssClass).height();

        jQuery(cssClass).fadeIn().css({marginBottom:-marginBottom}).animate({marginBottom:0}, 800, function() {
            if (autoClose !== false) {
                setTimeout( function() {
                    close_messenger_info();
                }, 5000);
            }
        });
        
        jQuery(cssClass+" #messenger_info_title i").click(function(){
            close_messenger_info();
        })
    }
    
    jQuery(window).bind("load resize", function() {
        var iScrollHeight = jQuery("#chat_history").prop("scrollHeight");
        jQuery("#chat_history").prop("scrollTop", iScrollHeight);
    });
    
    // F5 und Reload unterbinden
    function disableF5(e) {
        if ((e.which || e.keyCode) == 116) {
            e.preventDefault();
        }
    };
    jQuery(document).on("keydown", disableF5);

    jQuery.fn.checkParentIsOpen = function(url) {
        if (typeof WinEroCLoud == 'undefined' || WinEroCLoud.closed) {
            WinEroCLoud = window.open(mcp_url+"/Startseite", "WinEroCloud");
        } else {
            if (!WinEroCLoud.focus()) {
                WinEroCLoud = window.open(mcp_url+"/Startseite", "WinEroCloud");
            }
        }
    }

    jQuery(".splitcam_info").click(function() {
        jQuery("#overlay").show(function() {
            jQuery(".splitcam_info_popup").show();
        });
    });
	
    jQuery(".close_overlay").click(function() {
        jQuery(".splitcam_info_popup").hide(function() {
            jQuery("#overlay").hide();          
        });
    });
    
    
    jQuery.fn.get_actor_commision = function() {
        jQuery.post(mcp_url+"/Messenger/Ajax/submit.php", {get_commision: 'true'})
        .done(function(data){
            jQuery(".commision span").html(data);
        })
    }

    jQuery(this).get_actor_commision();
    setInterval(function() {jQuery(this).get_actor_commision();}, 30000);
});