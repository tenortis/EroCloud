jQuery(document).ready(function() {
    jQuery('.tabs ul li').click(function(){
        var tab_id = jQuery(this).attr('data-tab');

        jQuery('.tabs ul li').removeClass('current');
        jQuery('.tabs .tab-content').removeClass('current');

        jQuery(this).addClass('current');
        jQuery(".tabs #"+tab_id).addClass('current');
    });
});