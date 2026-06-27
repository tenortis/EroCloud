<?php

if (!defined('SAFE_INC')) {
    die ("Hacking attempt...");
}

$site .= '
    
<link rel="stylesheet" type="text/css" href="https://vjs.zencdn.net/6.6.3/video-js.css" />

<style type="text/css">
<!--

#all_actor_cams {
    border-left: none;
    border-right: none;
    border-bottom: none;
    position: relative;
}


#all_actor_cams video {
    height:100px;
    width: 178px;
}

#all_actor_cams .video-js {
    width:178px !important;
    height:100px !important;
}

#all_actor_cams .vjs-control-bar {
    display:none;
}

#all_actor_cams .ui-widget-header {
    text-align:center;
}

#all_actor_cams .all_actor_cams_icons {
    position:absolute;
    top:18px;
    right:0;
    background-color: rgba(0,0,0,0.4);
    color:#ffffff;
    cursor:pointer;
}


#actor_cam_box {
    padding:0;
    top:70px;
    left:400px;
    position:absolute;
    display:none;
    z-index:1;
    width: fit-content;
    max-height: 800px;
}

#actor_cam_box #livecam_header {
    min-width:620px;
    padding:5px 5px 5px 10px;
    text-align:center;
    cursor:-webkit-grab;
    border-bottom:none;
}

#actor_cam_box #livecam_header table,
#actor_cam_box #livecam_content #controls table {
    width:100%;
}

#actor_cam_box #livecam_header table .material-symbols-outlined {
    cursor:pointer;
}

#actor_cam_box #livecam_header,
#actor_cam_box #livecam_content {
    -webkit-box-shadow: 5px 5px 5px 0px rgba(170,170,170,0.7);
    -moz-box-shadow: 5px 5px 5px 0px rgba(170,170,170,0.7);
    box-shadow: 5px 5px 5px 0px rgba(170,170,170,0.7);
}

#actor_cam_box #livecam_content {
    min-width:640px;
    height: inherit;
    overflow:hidden;
}

#actor_cam_box #livecam_content video {
    display: block;
    width: -moz-available;
    width: -webkit-fill-available;
    width: fill-available;
}

#actor_cam_box .ui-widget-header td {vertical-align:middle}
#actor_cam_box .ui-widget-header td:first-child {text-align:left;}

#actor_cam_box .ui-widget-header td:nth-child(2) {
    text-align:right;
    width:25px;
}

#actor_cam_box .ui-widget-header td i {
    vertical-align: sub;
    font-size:20px;
}


-->
</style>

<script>

jQuery(document).ready(function() {

    jQuery("#actor_cam_box").draggable({ cancel: "#actor_cam_box #livecam_content" });

    jQuery.fn.open_amateur_cam = function(streamserver_url, stream_id, username) {
        
        jQuery("#actor_cam_box").show();
        jQuery("#actor_cam_box").animate({
            opacity: 1.0,
            left: 400,
            top: 70
        }, 300, function() {
            
        });

        jQuery("#actor_cam_box #username").text(username);
        jQuery("#actor_cam_box #livecam_content").html(
            "<video id=\"videoPlayer_"+stream_id+"'.time().'_big\" class=\"video-js vjs-default-skin\" controls preload=\"none\">"+
                "<source src=\""+streamserver_url+stream_id+".m3u8\" type=\"application/x-mpegURL\" />"+
            "</video>"+
            "<script>"+
                "var player = videojs(\"#videoPlayer_"+stream_id+"'.time().'_big\");"+
                "player.playsinline();"+
                "player.play();"+
            "<\/script>"
         );
       
    };
    
    jQuery.fn.close_amateur_cam = function() {
        var position = jQuery("#all_actor_cams").position();

        var player_id = jQuery("#actor_cam_box #livecam_content video").attr("id");
        if (typeof player_id !== "undefined") {
            videojs(player_id).dispose();
        }
       
        jQuery("#actor_cam_box").animate({
            opacity: 0.0,
            left: (position.left + 200),
            top: (position.top + 400)
        }, 300, function() {
            jQuery("#actor_cam_box #livecam_content").html("");
            jQuery("#actor_cam_box").hide();
        });
    };

    jQuery(".close_amateur_cam").click(function() {
        jQuery(this).close_amateur_cam();
    });

})
</script>


<div id="actor_cams">
    <div id="all_actor_cams">';

        while($cam_obj = p4c_fetch_object($rs_actor_cams)) {
            $site .= ' 
            <div style="width:178px; display: inline-block; position:relative;">
                <div class="ui-widget-header">'.$cam_obj->username.'</div>

                <video id="videoPlayer_'.$cam_obj->stream_id.time().'" class="video-js vjs-default-skin" muted controls preload="none">
                    <source src="'.$cam_obj->streamserver_url.$cam_obj->stream_id.'.m3u8" type="application/x-mpegURL" />
                </video>
                
                <script>
                    var player = videojs("videoPlayer_'.$cam_obj->stream_id.time().'");
                    player.playsinline();
                    player.play();
                </script>

                <div class="all_actor_cams_icons">
                    <i onclick="jQuery(this).open_amateur_cam(\''.$cam_obj->streamserver_url.'\', \''.$cam_obj->stream_id.'\', \''.$cam_obj->username.'\');" class="material-symbols-outlined open_usercam">open_in_new</i>
                </div>
            </div>';
        }
        $site .= ' 
    </div>
    

    <div id="actor_cam_box">
        <div class="ui-widget-header" id="livecam_header">
            <table cellspacing="0" cellpadding="0">
                <tr>
                    <td>Webcam vom User <span id="username"></span></td>
                    <td><i data-tooltip="Fenster schlie&szlig;en" class="material-symbols-outlined close_amateur_cam">close</i></td>
                </tr>
            </table>
        </div>
        <div class="ui-widget-content" id="livecam_content"></div>
    </div>

</div>'; 
