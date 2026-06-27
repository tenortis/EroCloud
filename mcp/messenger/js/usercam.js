
jQuery(document).ready(function() {
    
    jQuery("#usercam_box").draggable({ cancel: "#usercam_box #livecam_content" });

    jQuery.fn.get_all_usercams = function() {
        jQuery.ajax({
            url: mcp_url+"/Messenger/Ajax/submit.php",
            global: false,
            type: "POST",
            dataType: "json",
            data: "get_all_usercams"
        }).done(function(data) {
            var count_usercams = data['count'];
            var streams        = data['streams'];
            
            if (count_usercams > 0) {
                
                // Chat aktualisieren wenn sich die Chat-Checksum geändert hat
                if (typeof stream_checksum === "undefined" || stream_checksum !== data.stream_checksum) {
                    stream_checksum = data.stream_checksum;

                    jQuery("#all_usercams").html("");

                    for(var i in streams) {
                        var streamserver_url = streams[i]["streamserver_url"];
                        var stream_id   = streams[i]["stream_id"];
                        var username    = streams[i]["username"];
                        var member_id   = streams[i]["member_id"];

                        jQuery("#all_usercams").append(
                            "<div style=\"width:178px; display: inline-block; position:relative;\">"+
                            "<div class=\"ui-widget-header\">"+username+"</div>"+

                            "<video id=\"videoPlayer_"+stream_id+timestamp()+"\" class=\"video-js vjs-default-skin\" muted controls preload=\"none\">"+
                                "<source src=\""+streamserver_url+stream_id+".m3u8\" type=\"application/x-mpegURL\" />"+
                            "</video>"+
                            "<script>"+
                                "var player = videojs('#videoPlayer_"+stream_id+timestamp()+"');"+
                                "player.playsinline();"+
                                "player.play();"+
                            "<\/script>"+

                            "<div class=\"all_usercams_icons\">"+
                                "<i onclick=\"jQuery(this).open_usercam('"+streamserver_url+"', '"+stream_id+"', '"+username+"');\" class=\"material-symbols-outlined open_usercam\">open_in_new</i>"+
                            "</div>"+
                        "</div>"
                        );
                    }
                }
                
                jQuery(this).open_all_usercams();
            } else {
                jQuery(this).close_all_usercams();
                jQuery(this).close_usercam();
            }
        });
    };
    
    jQuery.fn.open_usercam = function(streamserver_url, stream_id, username) {
        
        jQuery("#usercam_box").show();
        jQuery("#usercam_box").animate({
            opacity: 1.0,
            left: 400,
            top: 70
        }, 300, function() {
            
        });

        jQuery("#usercam_box #username").text(username);
        jQuery("#usercam_box #livecam_content").html(
            '<video id="videoPlayer_'+stream_id+timestamp()+'_big" class="video-js vjs-default-skin" controls preload="none">'+
                '<source src="'+streamserver_url+stream_id+'.m3u8" type="application/x-mpegURL" />'+
            '</video>'+
            '<script>'+
                'var player = videojs("#videoPlayer_'+stream_id+timestamp()+'_big");'+
                "player.playsinline();"+
                'player.play();'+
            '</script>'
         );
       
    };
    
    jQuery.fn.open_all_usercams = function() {        
        jQuery("#all_usercams").show();
        jQuery("#chat_history").css({maxHeight: "calc(100vh - 470px)", height: "calc(100vh - 324px)"});
    };
   
    jQuery.fn.close_all_usercams = function() {
        
        jQuery('#all_usercams video').each(function(){
            var player_id = jQuery(this).attr('id');
            if (typeof player_id !== 'undefined') {
                videojs(player_id).dispose();
            }
        })
        
        jQuery("#all_usercams").hide().html();
        jQuery("#chat_history").css({maxHeight: "calc(100vh - 353px)", height: "calc(100vh - 153px)"});
    };

    jQuery.fn.close_usercam = function() {
        var position = jQuery("#all_usercams").position();

        var player_id = jQuery('#usercam_box #livecam_content video').attr('id');
        if (typeof player_id !== 'undefined') {
            videojs(player_id).dispose();
        }
       
        jQuery("#usercam_box").animate({
            opacity: 0.0,
            left: (position.left + 200),
            top: (position.top + 400)
        }, 300, function() {
            jQuery("#usercam_box #livecam_content").html("");
            jQuery("#usercam_box").hide();
        });
    };

    jQuery(".close_usercam").click(function() {
        jQuery(this).close_usercam();
    });
        
});