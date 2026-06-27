jQuery.noConflict();

jQuery(document).ready(function() {
    jQuery(".button").button();
    jQuery(".checkbox").checkboxradio();
    jQuery(".radio").checkboxradio();
    jQuery(".radiaset").controlgroup();
    jQuery("#tabs").tabs({
        activate: function (event, ui) {
            activeTabId = jQuery(this).tabs("option", "active");
            jQuery("#activeTabId").val(activeTabId);
        }
    });
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
    
    
    jQuery(".movie_metainfos").click(function() {
        jQuery("#overlay").show(function() {
            jQuery(".movie_metainfos_popup").show();
        });
    });
    
    jQuery(".close_overlay").click(function() {
        jQuery(".movie_metainfos_popup").hide(function() {
            jQuery("#overlay").hide();          
        });
    })
    
})