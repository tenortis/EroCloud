jQuery(document).ready(function() {
    
    jQuery(".submenu").click(function(){
        var submenu = jQuery(this).attr("data-submenu");
        var arrow = jQuery(".arrow", this);
        
        if (arrow.html() == 'arrow_right') {
            jQuery("."+submenu).slideDown("fast");
            arrow.html("arrow_drop_down");
        } else {
            jQuery("."+submenu).slideUp("fast");
            arrow.html("arrow_right");            
        }
    })

    jQuery.fn.open_submenu = function(submenu) {
        jQuery("."+submenu).slideDown("fast");
        jQuery('[data-submenu="'+submenu+'"] .arrow').html("arrow_drop_down");
    }
    
})