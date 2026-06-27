"use strict";

jQuery(document).ready(function() {
    
    function open_cambar() {
        let cambar_height = 100;
        let msg_height = jQuery("#messenger_main").height();
        let new_left_bar_height = msg_height - cambar_height;
        let new_content_height = msg_height - cambar_height;
        let new_chat_history_height = jQuery("#messenger_parent").outerHeight() - jQuery("#messenger_head").outerHeight() - jQuery("#chat_head").outerHeight() - jQuery("#chat_send_mess").outerHeight() - cambar_height;
        jQuery("#chat #chat_history").css({height: new_chat_history_height});
        jQuery("#messenger_left").animate({height: new_left_bar_height}, 500);
        jQuery("#chat #chat_history").animate({height: new_chat_history_height}, 500);
        jQuery("#messenger_right, #chat, #chat_infos").animate({height: new_content_height}, 500);
        jQuery("#cambar").show().animate({height: cambar_height}, 500);
    }
    
    function close_cambar() {
        var cambar_height = 0;
        var msg_height = jQuery("#messenger_main").height();
        let new_chat_history_height = jQuery("#messenger_parent").outerHeight() - jQuery("#messenger_head").outerHeight() - jQuery("#chat_head").outerHeight() - jQuery("#chat_send_mess").outerHeight();

        jQuery("#chat #chat_history").css({height: new_chat_history_height});
        jQuery("#messenger_left, #messenger_right, #chat, #chat_infos").animate({height: msg_height}, 500);
        jQuery("#cambar").animate({height: cambar_height}, 500).html("").hide();
    }
    
    jQuery.fn.cambar_user_sound_on = function(member_id) {
        
	var iFrameDOM = jQuery("#cambar [data-stream_member_id='"+member_id+"'] iframe").contents();
	var video = iFrameDOM.find("video");
        
        if(video.prop('muted') ) {
            video.prop('muted', false);
            jQuery("#cambar [data-stream_member_id='"+member_id+"'] .volume").text("volume_up").css({"color":"#4caf50",});
        } else {
            video.prop('muted', true);
            jQuery("#cambar [data-stream_member_id='"+member_id+"'] .volume").text("volume_off").css({"color":"#2779aa",});
        }        
    }
    
    jQuery.fn.open_cam2cam = function(member_id) {
        
        jQuery.post("user_cam/get_connections.php", {dataType: "json", "get_userinfos":member_id}, function(data){

        })
        .then(function(data){
            var cons_obj = JSON.parse(data);
            var username = cons_obj['username'];
            jQuery("#cam2cam #cam2cam_username").text(username);
            
        })
                
        jQuery("#cam2cam #livecam_konsole").attr("src", "livecam_mod_c2c/cam_konsole.php?site=konsole&member_id="+member_id);
        jQuery("#cam2cam").show();
        jQuery("#cam2cam").animate({
            opacity: 1.0,
            left: 400,
            top: 70
        }, 500, function() {
            jQuery("#cam2cam #livecam_content").animate({
                height: "show"
            }, 500, function() {
                jQuery("#cam2cam button#livecam_mini").button({
                    icons: {
                    primary: "ui-icon-arrowthickstop-1-n"
                },
                    text: false
                })
            });
        }); 
        
    }
    
    jQuery("#cam2cam #livecam_close").click(function() {
        var is_confirmed = confirm(unescape("Soll die bestehende Session wirklich beendet werden?"));
        if (is_confirmed) {
            jQuery("#cam2cam").hide();

            jQuery.ajax({
                url: "ajax/submit.php",
                global: false,
                type: "POST",
                data: "cam2cam_close=true",
                success: function(result) {
                    jQuery("#cam2cam #livecam_konsole").attr("src", "");                                   
                }
            })
        }
    })
    
    jQuery("#cam2cam #livecam_mini").click(function() {
        jQuery("#cam2cam #livecam_content").animate({
            height: "toggle"
        }, 500, function() {
            var status = jQuery("#cam2cam #livecam_mini").attr("data-livecam_mini_status");
            if (status == "max") {
                jQuery("#cam2cam button#livecam_mini").button({
                    icons: {
                        primary: "ui-icon-arrowthickstop-1-s"
                    },
                    text: false
                })
                jQuery("#cam2cam #livecam_mini").attr("data-livecam_mini_status", "min");
            } else {
                jQuery("#cam2cam button#livecam_mini").button({
                    icons: {
                        primary: "ui-icon-arrowthickstop-1-n"
                    },
                    text: false
                })
                jQuery("#cam2cam #livecam_mini").attr("data-livecam_mini_status", "max");   
            }
        });
    })

    /*
    setTimeout(function(){
        close_cambar();
    }, 5000);
    */

    /**
     * 
     * User-Streams in #cambar platzieren
     */
    var temp_checksum = '';    
    async function get_connections() {
        jQuery.post(mcp_url+"/Messenger/usercams/get_connections.php", {dataType: "json"})
        .then(function(data){
            let cons_obj = JSON.parse(data);            

            if (cons_obj.connection === 0) {
                if(jQuery('#cambar').is(':visible')) {
                    console.log(cons_obj);
                    close_cambar();
                    temp_checksum = 'offline';
                }
            }

            if (typeof cons_obj.connection !== 'undefined' && cons_obj.connection !== 0) {

                var connections_checksum = cons_obj.checksum;
                var connections = cons_obj.connection;
                
                // Wenn sich die Verbindungen geändert haben. aktualisieren
                if (connections_checksum !== temp_checksum) {
                    //console.log("connections eingelesen");
                    Object.keys(connections).forEach(key => {
                        
                        if (connections[key] == "connection_closed") {
                            jQuery("#container").find("#cambar .user_video_container[data-stream_member_id='"+key+"']").remove();
                            return;
                        }
                        
                        let iframe_url = connections[key]['iframe_url'];
                        let member_id =  connections[key]['member_id'];
                        let chat_id =  connections[key]['chat_id'];

                        // Prüfen ob der Stream noch nicht geöffnet wurde und öffne ihn
                        if (jQuery("#container").find("#cambar [data-stream_member_id='"+member_id+"']").length == 0) {

                            //console.log("open: "+member_id);
                            var height = 100;
                            var width = 177;
                            var new_width = 500;

                            jQuery.post(mcp_url+"/Messenger/usercams/get_connections.php", {dataType: "json", "get_userinfos":member_id})
                            .then(function(data){
                                var cons_obj = JSON.parse(data);
                                var username = cons_obj['username'];

                                var webcam = 
                                '<div class="user_video_container" data-stream_member_id="'+member_id+'" style="position:relative; width:'+width+'px; height:'+height+'px; display: inline-block; z-index: 1;" >'+
                                        '<div class="user_video_container_dragable" style="position:absolute; width:'+width+'px; height:'+height+'px; display: inline-block;" data-stream_member_id="'+member_id+'">'+
                                        '<iframe style="width:100%; height:100%" src="'+iframe_url+'" frameborder="0"></iframe>'+
                                        '<div class="ui-widget-header top">'+
                                            '<span class="material-symbols-outlined" title="Chat &ouml;ffnen" onclick="jQuery(this).open_chat(\''+chat_id+'\');">chat</span> '+username+
                                        '</div>'+
                                        '<div class="ui-widget-header left">'+
                                            '<div class="move material-symbols-outlined" title="Zum vergr&ouml;&szlig;ern in die Bildmitte ziehen.">open_with</div>'+
                                            '<div class="volume material-symbols-outlined" title="Wenn der User sein Mikro aktiviert hat, kannst du den Ton hier einschlten." onclick="jQuery(this).cambar_user_sound_on('+member_id+');">volume_off</div>'+
                                            //'<div class="open_cam2cam material-symbols-outlined" title="Klicken um Cam2Cam zu starten." onclick="jQuery(this).open_cam2cam('+member_id+');">videocam</div>'+
                                        '</div>'+
                                    '</div>'+
                                '</div>';

                                jQuery("#cambar").append(webcam);
                                jQuery("#cambar [data-stream_member_id='"+member_id+"']").ready(function() {
                                    var ratio = width / new_width;
                                    var new_height = Math.round(height / ratio);

                                    jQuery("#cambar [data-stream_member_id='"+member_id+"']").draggable({
                                        containment: '#container',
                                        scroll: false,
                                        stop: function() {
                                            var position = jQuery(this).position();
                                            if (jQuery(this).width() == width && position.top > -250) {
                                                jQuery(this).css({"top":"0", "left":"0"});
                                            }
                                        },

                                        drag: function( event, ui ) {
                                            var position = jQuery(this).position();
                                            // verkleinern wenn fenster Fenster maximiert ist
                                            if (jQuery(this).width() == new_width && position.top > -250) {
                                                //console.log('minimieren');
                                                jQuery(this).animate({
                                                    "width": width+"px",
                                                    "height": height+"px"
                                                }, 300, function(){
                                                    //console.log(position.top);
                                                    jQuery(this).css({"top":"0", "left":"0"});
                                                }).removeClass("box_shadow");

                                            // vergrößern wenn fenster Fenster minimiert ist    
                                            } else if (jQuery(this).width() == width && position.top < -251) {
                                                //console.log('maximieren');
                                                jQuery(this).animate({
                                                    "width": new_width+"px",
                                                    "height": new_height+"px"
                                                }, 300).addClass("box_shadow");

                                            }
                                        }                      
                                    });

                                })

                            })   
                            

                        }

                    });
                    
                    temp_checksum = connections_checksum;
                    
                    // CamBar geschlossen, dann öffnen
                    if(jQuery('#cambar').is(':hidden')) {
                        open_cambar();
                    }
               
                }     
            }
        })
    }
    
    // Alle 5 Sekunden prüfen ob User ihre Webcam senden
    get_connections(); // erster Aufruf, sofort ausführen
    setInterval(function(){get_connections();}, 5000);
    
    
})
