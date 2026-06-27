
jQuery(document).ready(function() {
  
    
    jQuery("#webcam_box").draggable({ cancel: "#webcam_box #livecam_content" });
    jQuery("#webcam_box #livecam_content").resizable({
        aspectRatio: 640 / 620,
        maxWidth:800
    });

    jQuery(".open_webcam").click(function(){
        if (jQuery("#webcam_box").is(":hidden")) { 

            var position = jQuery("#messenger_head .open_webcam").position();
            var left = position.left - 200;
            
            jQuery("#webcam_box").css({left: left, top: position.top})
            
            jQuery("#webcam_box .minimize_webcam").attr("data-livecam_minimize_status","max");

            jQuery("#webcam_box").show();
            jQuery("#webcam_box").animate({
                opacity: 1.0,
                left: 400,
                top: 70
            }, 300, function() {
                
                jQuery(this).load_group_actors();
                
                if (typeof update_user !== 'undefined') {clearInterval(update_user);};
                jQuery(this).update_user();
                update_user = setInterval("jQuery(this).update_user()", 5000); // 5 Sekunden

                jQuery(this).get_cam_user_auto_on();                
                
            });
        }
    })

    jQuery.fn.close_webcam = function() {
        var position = jQuery("#messenger_head .open_webcam").position();

        jQuery("#webcam_box").animate({
            opacity: 0.0,
            left: (position.left - 200),
            top: position.top
        }, 300, function() {
            jQuery("#webcam_box").hide();
            clearInterval(update_user);
            stop_button(event);
        });
    }
    
    jQuery.fn.disabled_options = function() { 
        jQuery("#publisher-inputs select, #publisher-inputs input").attr("disabled", true);
    }
    
    jQuery.fn.enabled_options = function() { 
        jQuery("#publisher-inputs select, #publisher-inputs input").removeAttr("disabled");
    }    

    jQuery(".close_webcam").click(function() {
        
        let stream_is_online = false;
        
        // Wenn Player eingebettet
        if (jQuery('#player').length) {
            let player = videojs("#player");
            if (player !== null) {
                stream_is_online = true;
            }
        }
        
        if (stream_is_online == true) {
            var is_confirmed = confirm(unescape("Soll die LiveCam-Konsole wirklich geschlossen werden? Die bestehende Session wird beendet!"));
            if (is_confirmed) {
                jQuery(this).close_webcam();
            }
        } else {        
            jQuery(this).close_webcam();
        }
        

    })

    jQuery(".minimize_webcam").click(function() {
        
        var status = jQuery("#webcam_box .minimize_webcam").attr("data-livecam_minimize_status");
        if (status == "max") {
            jQuery("#webcam_box #livecam_content").animate({
                height: 100,
                width: 550
            }, 500, function() {
                jQuery("#webcam_box .minimize_webcam").attr("data-livecam_minimize_status", "min").text("expand_more");
            })
            

        } else {
            jQuery("#webcam_box #livecam_content").animate({
                height: 620,
                width: 550
            }, 500, function() {
                jQuery("#webcam_box .minimize_webcam").attr("data-livecam_minimize_status", "max").text("expand_less");
            })
        }
        
        /*        
        jQuery("#webcam_box #livecam_content").animate({
            height: "toggle"
        }, 500, function() {
            var status = jQuery("#webcam_box .minimize_webcam").attr("data-livecam_minimize_status");
            if (status == "max") {
                jQuery("#webcam_box .minimize_webcam").attr("data-livecam_minimize_status", "min").text("expand_more");
                
            } else {
                jQuery("#webcam_box .minimize_webcam").attr("data-livecam_minimize_status", "max").text("expand_less");
            }
        });
        */
    })
    
    jQuery("#webcam_box .cam_user #cam_user_auto_on").click(function(){
        
        var icon = jQuery("#webcam_box .cam_user #cam_user_auto_on .material-symbols-outlined").text();
        var new_status = 1;
        
        if (icon === 'check_box') {
            var new_status = 0;
        }

        var actor_id = jQuery("#livecam_group_actors select").val();

        jQuery.post(mcp_url+"/Messenger/Cam/submit.php", {set_cam_user_auto_on: new_status,actor_id: actor_id})
        .done(function(status){
            jQuery(this).get_cam_user_auto_on();
        })
        
    })

    jQuery.fn.get_cam_user_auto_on = function() {
        var actor_id = jQuery("#livecam_group_actors select").val();
        
        jQuery.post(mcp_url+"/Messenger/Cam/submit.php", {get_cam_user_auto_on: 'true',actor_id: actor_id})
        .done(function(status){
            if (status === "1") {
                jQuery("#webcam_box .cam_user #cam_user_auto_on .material-symbols-outlined").text("check_box");
            } else {
                jQuery("#webcam_box .cam_user #cam_user_auto_on .material-symbols-outlined").text("check_box_outline_blank");
            }
        })
    }
    
    
    jQuery.fn.update_user = function() {
        var actor_id = jQuery("#livecam_group_actors select").val();
        
        jQuery.ajax({
            type: "POST",
            dataType: "html",
            url: mcp_url+"/Messenger/Cam/get_cam_users.php",
            data: "actor_id="+actor_id,
            async: true,
            success: function(data) {
                jQuery("#webcam_box #livecam_content #user").html(data);
            }
        });
    }
    
    jQuery.fn.cam_activate = function(chat_id, id) {
        jQuery.ajax({
            type: "POST",
            dataType: "html",
            url: mcp_url+"/Messenger/Cam/submit.php",
            data: "activate="+id+"&chat_id="+chat_id,
            async: true,
            success: function(data) {
                jQuery(this).update_user();
            }
        });
    }

    jQuery.fn.sound_on_off = function(chat_id, id) {
        jQuery.ajax({
            type: "POST",
            dataType: "html",
            url: mcp_url+"/Messenger/Cam/submit.php",
            data: "sound="+id+"&chat_id="+chat_id,
            async: true,
            success: function(data) {
                jQuery(this).update_user();
            }
        });
    }
    
    jQuery("#mic_off").click(function() {
        jQuery("#mic_on").show();
        jQuery("#mic_off").hide();
        publisher.setMicrophone(1);
    })

    jQuery("#mic_on").click(function() {
        jQuery("#mic_on").hide();
        jQuery("#mic_off").show();
        publisher.setMicrophone(0);
    })

    jQuery("#webcam_box #livecam_content .start-btn").on("click", function(event) {
        startPublishing(event);
        jQuery('#livecam_group_actors select').attr('disabled', true);
        jQuery(this).disabled_options();
    })
    
    
    jQuery("#webcam_box #livecam_content .preview-btn").on("click", function(event) {
        alert('kann weg! 1');
        /*
        event.preventDefault()

        var actor_id = jQuery("#livecam_group_actors select").val();

        jQuery.ajax({
            type: "post",
            dataType: "text",
            url: mcp_url+"/Messenger/Cam/submit.php",
            data: "cam_beenden=true&actor_id="+actor_id
        })			

        var swfVersionStr = "11.4.0"
        , xiSwfUrlStr = mcp_url+"/Messenger/Cam/playerProductInstall.swf"
        , flashvars = {}
        , params = {}
        , attributes = {}

        var embedHandler = function (e){
            if(e.success){
                alert("The embed was successful!");
            } else {
                alert("The embed failed!");
            }
        };

        params.allowscriptaccess = "sameDomain"
        params.allowfullscreen = "true"
        attributes.id = "publisher"
        attributes.name = "Publisher"
        attributes.align = "middle"
        attributes.wmode = "opaque"
        swfobject.embedSWF(
            mcp_url+"/Messenger/Cam/publisher.swf",
            "publisher",
            "auto",
            "auto",
            swfVersionStr,
            xiSwfUrlStr,
            flashvars,
            params,
            attributes,
            function(embedEvent) {
                if (embedEvent.success) {
                    // need to wait a bit until initialization finishes
                    setTimeout(function() {
                        embedEvent.ref.setOptions({
                            jsLogFunction: "console.log",
                            jsEmitFunction: "handleEmit"
                        })

                        var publisher = jQuery("#publisher")[0];
                        var options = getOptions();

                        publisher.setOptions(options);
                        publisher.preview();	

                    }, 1000)
                    updateStatus("Configure your stream.")
                }
            }
        )
        */
    })

/*
    jQuery("#webcam_box #livecam_content .stop-btn").click(function() {
        var publisher = jQuery("#publisher")[0]
        if (typeof publisher.stop === "function") {
            publisher.stop();
        }
        
        jQuery("#mic_on, #mic_off").hide();
        
        stop_button(event);
        jQuery('#livecam_group_actors select').attr('disabled', false);
        jQuery(this).enabled_options();
        
*/      
    jQuery("#webcam_box #livecam_content .stop-btn").on("click", function(event) {
        event.preventDefault();

        let actor_id = jQuery("#livecam_group_actors select").val();

        jQuery.ajax({
            type: "post",
            dataType: "text",
            url: mcp_url+"/Messenger/Cam/submit.php",
            data: "cam_beenden=true&actor_id="+actor_id
        })

        let player = videojs("#player");
        if (player !== null) {
            player.pause();
            player.dispose();
            player = null;
        }

        jQuery("#publisher_container").html("");

        jQuery("#status_cam_off, .start-btn").show();
        jQuery("#status_cam_on, .stop-btn").hide();

        updateStatus("Press Play button to stream your Webcam.")
        
    })
    
    jQuery.fn.load_group_actors = function() {
        
        xhr_member_infos = jQuery.ajax({
            url: mcp_url+"/Messenger/Cam/submit.php",
            data: "group_actors=get",
            method: "POST",
            dataType: "json",
            async: true,
            success: function (data) {                

                jQuery("#livecam_group_actors select").html("");

                var group_actors = data.group_actors;

                if (typeof group_actors === 'undefined') {
                    alert("Bitte waehle erst eine Gruppe mit der du online gehen willst.");
                    jQuery(this).close_webcam();
                } else {

                    if (group_actors.length > 1) {
                        jQuery("#livecam_group_actors").css({"display":"block"});
                    }

                    for(var i in group_actors) {
                        jQuery("#livecam_group_actors select").append(
                            '<option value="'+group_actors[i]["actor_id"]+'">'+group_actors[i]["actor_username"]+'</option>'
                        );
                    }
                }
            }
        })
    }

})

function stop_button(event) {
    //event.preventDefault();

    var actor_id = jQuery("#livecam_group_actors select").val();

    jQuery.ajax({
        type: "post",
        dataType: "text",
        url: mcp_url+"/Messenger/Cam/submit.php",
        data: "cam_beenden=true&actor_id="+actor_id
    })

    jQuery("#publisher_container").html("<div id=\"publisher\"></div>");
    updateStatus("Press Play button to stream your Webcam.");    

    jQuery("#status_cam_off, .start-btn").show();
    jQuery("#status_cam_on, .stop-btn").hide();

    if (typeof counting_online_minutes !== 'undefined') {
        clearInterval(counting_online_minutes);
    };
}

async function startPublishing(event){
    if(event){ event.preventDefault();}

    var actor_id = jQuery("#livecam_group_actors select").val();

    let streamname = jQuery("#streamName").val();

    jQuery.ajax({
        type: "post",
        dataType: "text",
        url: mcp_url+"/Messenger/Cam/submit.php",
        data: "cam_senden=true&actor_id="+actor_id,
        success: function(data) {

            if (data == 'actor_not_exist') {
                updateStatus("Bitte waehle ein Profil aus mit dem du online gehen moechtest.");    
            } else {

                if (typeof counting_online_minutes !== 'undefined') {clearInterval(counting_online_minutes);};
                counting_online_minutes = setInterval("fn_counting_online_minutes()", 60000);

                if (streamname == "") {
                    streamname = data;
                    jQuery("#streamName").val(streamname);
                }

                var stream = "https://stream.me-on.de/hls/"+streamname+".m3u8";

                jQuery("#publisher_container").html(
                    "<video id=\"player\" class=\"video-js vjs-default-skin\">"+
                         "<source src=\""+stream+"\" type=\"application/x-mpegURL\" />"+
                     "</video>"
                )

                jQuery("#streamName").val(streamname);
                jQuery("#status_cam_off, .start-btn").hide();
                jQuery("#status_cam_on, .stop-btn").show();

                const player = videojs("#player", {"controls": true, "autoplay": false, "preload": "auto" });
                
                player.src({
                    src: stream,
                    type: "application/x-mpegURL"
                });

                updateStatus("Verbindung zum Stream wird hergestellt...");
                player.play();
                player.muted(true);
                
                player.on('playing', function() {
                    updateStatus("Deine Webcam wird gesendet.");
                    setTimeout(function(){ 
                        jQuery("#status, #status_bar").hide();
                    }, 10000);
                })

                player.on("error", function(e) {
                    console.log("xxx", e)
                })
                
            }
        }
    })
}

/*
function getOptions(){
    var $form		= jQuery("form#publisher-inputs");
    var fps 		= parseInt($form.find("#streamFPS").val());
    var intervalSecs    = parseInt($form.find("#keyFrameInterval").val());
    var bandwidth	= parseInt($form.find("#bandwidth").val());
    var videoQuality    = parseInt($form.find("#videoQuality").val());

    var resolution      = $form.find("#streamResolution").val();
    var resolution_split= resolution.split("x");
    var streamWidth     = resolution_split[0];
    var streamHeight    = resolution_split[1];

    var quality = $form.find("#quality").val();

    if (quality === "low") {

        // 2,8-3.1s
        if (streamWidth === "640") {
            fps 		= 7;
            bandwidth	= 3;
            videoQuality= 75; 
        }

        // 3.0s-3,1s
        else if (streamWidth === "800") {
            fps 		= 8;
            bandwidth	= 6;
            videoQuality= 75;
        }

        // 2.8s-3.1s
        else if (streamWidth === "1280") {
            fps 		= 10;
            bandwidth	= 8;
            videoQuality= 75; 
        }

        // 2,9s-4,4s
        else if (streamWidth === "1600") {
            fps 		= 20;
            bandwidth	= 15;
            videoQuality= 75; 
        }

        // 3,1s-4,2s
        else if (streamWidth === "1920") {
            fps 		= 25;
            bandwidth	= 15;
            videoQuality= 75; 
        }

    } else if (quality === "standard") {
        // 3,5s
        if (streamWidth === "640") {
            fps 		= 10;
            bandwidth	= 20;
            videoQuality= 100;
        }

        // 3,9-5,8s
        else if (streamWidth === "800") {
            fps 		= 15;
            bandwidth	= 30;
            videoQuality= 100;
        }

        // 3,5s-4,3
        else if (streamWidth === "1280") {
            fps 		= 25;
            bandwidth	= 40;
            videoQuality= 100;
        }

        // 3,5s-4,3s
        else if (streamWidth === "1600") {
            fps 		= 25;
            bandwidth	= 50;
            videoQuality= 100;
        }

        // 4,5s-5,5s
        else if (streamWidth === "1920") {
            fps 		= 30;
            bandwidth	= 60;
            videoQuality= 100;
        }
    }
    
    return {
        serverURL: $form.find("#serverUrl").val()
        , streamName: $form.find("#streamName").val()
        , streamKey: $form.find("#streamKey").val()
        , audioCodec: "NellyMoser"
        , videoCodec: "H264Avc"
        , h264Level: "2.2"
        , streamWidth: parseInt(streamWidth)
        , streamHeight: parseInt(streamHeight)
        , streamFPS: fps
        , keyFrameInterval: fps * intervalSecs
        , bandwidth: parseInt(bandwidth) * 1024 * 8
        , videoQuality: parseInt(videoQuality)
        , embedTimecode: true
        , timecodeFrequency: 1000
        , microphoneGain: parseInt($form.find("#microphoneGain").val())
    }
}

function handleEmit(event) {
    switch(event.kind) {
        case "connect":
        case "disconnect":
        case "publish":
        case "status":
        case "error":
            //console.log("got event", new Date, event)
            //updateStatus(event.message)
            break
            default:
            //console.log(new Date, event)
    }
    
    // Previewing
    if (event.code == "101"){
        updateStatus('Bitte klicke auf "Zulassen" um deine Kamera zu aktivieren.');
    } else if (event.code == "110"){
        jQuery("#status_cam_off, .start-btn").hide();
        jQuery("#status_cam_on, .stop-btn").show();
        
    // Publishing started
    } else if (event.code == "201"){
        jQuery("#mic_on").show();
        updateStatus("Deine Webcam wird gesendet.");
        
        if (typeof counting_online_minutes !== 'undefined') {clearInterval(counting_online_minutes);};
        counting_online_minutes = setInterval("fn_counting_online_minutes()", 60000);

    } else {
        jQuery("#status_cam_off, .start-btn").show();
        jQuery("#status_cam_on, .stop-btn").hide();
        jQuery("#mic_on").hide();
        //stop_button();
        updateStatus("Es schein ein Problem mit deiner Kamera zu geben.");
    }
}
*/
function fn_counting_online_minutes() {
    var actor_id = jQuery("#livecam_group_actors select").val();

    jQuery.ajax({
        type: "POST",
        dataType: "html",
        url: mcp_url+"/Messenger/Cam/counting_online_minutes.php",
        data: "actor_id="+actor_id,
        async: true,
        success: function(data) {

        }
    });
}

function updateStatus(message) {
    var $statusDiv = jQuery("#webcam_box #livecam_content #status");
    $statusDiv.text(message)
}
