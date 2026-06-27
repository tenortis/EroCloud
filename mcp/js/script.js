jQuery.noConflict();

jQuery(document).ready(function() {
    
    window.name = "WinEroCloud";
    
    jQuery(".button").button();
    jQuery(".checkbox").checkboxradio();
    jQuery(".radio").checkboxradio();
    jQuery(".radiaset").controlgroup();
    jQuery("#tabs").tabs({
        activate: function (event, ui) {
            activeTabId = jQuery(this).tabs('option', 'active');
            jQuery("#activeTabId").val(activeTabId);
        }
    });
    
    jQuery("#livecam_tabs").tabs();
    

    jQuery(document).tooltip({
        items: "[data-tooltip]",
        content: function() {
            var element = jQuery(this);
            if (element.is("[data-tooltip]")) {
                var text = element.text();
                return element.attr("data-tooltip");
            }
        }
    });
    
    jQuery("#play_cam_sound").click(function() {
        var sound = jQuery("#cam_sound").val();
        
        jQuery("#new_webcam_user_sound_player").html(
            '<audio>'+
                '<source src="'+mcp_url+'/Messenger/Sound/'+sound+'.ogg" type="audio/ogg">'+
                '<source src="'+mcp_url+'/Messenger/Sound/'+sound+'.mp3" type="audio/mpeg">'+
            '</audio>'
        );

        jQuery('#new_webcam_user_sound_player audio').trigger('play');
    });
    
    jQuery("#headline_apps").click(function(){
        jQuery("#partner_sites").toggle();
    });
    
    /** Close Overlay-Boxes **/
    jQuery("#partner_sites").hover(function(){ 
        // inside 
    }, function(){ 
        jQuery("body").mouseup(function(){ 
            jQuery("#partner_sites").hide();
        });
    });
    
    jQuery(".api_key").click(function() {
        jQuery("#overlay").show(function() {
            jQuery(".api_key_popup").show();
        });
    });
    
    jQuery(".contact_footer").click(function() {
        jQuery("#overlay").show(function() {
            jQuery(".help_popup").show();
        });
    });
	
    jQuery(".imprint").click(function() {
        jQuery("#overlay").show(function() {
            jQuery(".imprint_popup").show();
        });
    });

    jQuery(".movie_tips").click(function() {
        jQuery("#overlay").show(function() {
            jQuery(".movie_tips_popup").show();
        });
    });
    
    jQuery(".movie_rendering_tips").click(function() {
        jQuery("#overlay").show(function() {
            jQuery(".movie_rendering_tips_popup").show();
        });
    });
    
    jQuery(".photo_album_tips").click(function() {
        jQuery("#overlay").show(function() {
            jQuery(".album_tips_popup").show();
        });
    });
	
    jQuery(".close_overlay").click(function() {
        jQuery(".api_key_popup").hide();        
        jQuery(".imprint_popup").hide();		
        jQuery(".help_popup").hide();		
        jQuery(".movie_tips_popup").hide();
        jQuery(".movie_rendering_tips_popup").hide();        
        jQuery(".album_tips_popup").hide();        
        jQuery(".clicks_popup").hide();
        jQuery(".impressions_popup").hide();
        jQuery(".my_banners_popup").hide();
        jQuery(".reply_comment_popup").hide();
        jQuery(".reply_message_popup").hide();
        
        jQuery("#overlay").hide();
    }); 
    
    // Prüfden ob Messenger geöffnet ist, wenn nicht, öffnen
    jQuery.fn.checkMessengerIsOpen = function(url) {
        if (typeof EroCloudMessenger == 'undefined' || EroCloudMessenger.closed) {
            EroCloudMessenger = window.open(url, "EroCloudMessenger");
        } else {
            if (!EroCloudMessenger.focus()) {
                EroCloudMessenger = window.open(url, "EroCloudMessenger");
            }
        }
    }
	


})