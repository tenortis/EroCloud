jQuery(document).ready(function() {

    function isFileTypeAllowed(allowTypes, fileName) {
        var fileExtensions = allowTypes.toLowerCase().split(/[\s,]+/g);
        var ext = fileName.split(".").pop().toLowerCase();
        if(allowTypes != "*" && jQuery.inArray(ext, fileExtensions) < 0) {
            return false;
        }
        return true;
    }

    var upload_banners_bar = jQuery("#upload_photos .bar");
    var upload_banners_percent = jQuery("#upload_photos .percent");
    var upload_banners_status = jQuery("#upload_photos .status");
    var upload_banners_progress = jQuery("#upload_photos .progress");
    var upload_banners_abort = jQuery("#upload_photos .abort_upload");
    var upload_banners_upload = jQuery("#upload_photos .upload_banners");
    var upload_banners_released = jQuery("#upload_photos #upload_banners_released");

    jQuery("#form_upload_banners").ajaxForm({
        clearForm: true,
        resetForm: true,
        forceSync: true,
        async: true,
        dataType:  "json",
        data: {released: upload_banners_released.is(":checked")},
        beforeSend: function(xhr) {
            jQuery(".upload_banners_error").hide().html("");
            upload_banners_progress.show();
            upload_banners_upload.hide();
            upload_banners_abort.show();
            upload_banners_released.attr("disabled", true);
            upload_banners_abort.click(function () {
                xhr.abort();
                jQuery("#upload_banners").val("");
                upload_banners_upload.show();
                upload_banners_abort.hide();
                upload_banners_released.attr("disabled", false);
                upload_banners_progress.hide();
            });

            upload_banners_status.empty();
            var percentVal = "0%";
            upload_banners_bar.width(percentVal)
            upload_banners_percent.html(percentVal);
        },
        uploadProgress: function(event, position, total, percentComplete) {
            var percentVal = percentComplete + "%";
            upload_banners_bar.width(percentVal)
            upload_banners_percent.html(percentVal);
        },
        success: function(data) {
            var percentVal = "100%";
            upload_banners_bar.width(percentVal)
            upload_banners_percent.html(percentVal);
            // If error
            if (data["jquery-upload-file-error"]) {
                var error = data["jquery-upload-file-error"];
                jQuery(".upload_banners_error").show().html(error);

            } else {
                jQuery("#ok-message").show();
            }
            
            jQuery("#upload_banners").val("");
            upload_banners_upload.show();
            upload_banners_abort.hide();
            upload_banners_released.attr("disabled", false);
            upload_banners_progress.hide();
            
        },
        complete: function(data) {

        }
    }); 


    //var allowedImagesTypes = "jpg,jpeg,png,gif"; // wird in webmasteR_my_banner_upload.php gesetzt
    var maxImageFileSize = 5242880; // 5 MB
    var maxFileUploads = 1;

    function uploadImages(f) {

        var files = f.files;
        
        var count_files = files.length;
                
        if (count_files > maxFileUploads) {
            error = "Du darfst nur maximal "+maxFileUploads+" Bild gleichzeitig hochladen.";
            return false;
        }
        
        for($i=0;$i<count_files;$i++) {
            var file = f.files[$i];
            var fileName = file.name;
            var fileSize = file.size;

            fileName = fileName.replace(/(<([^>]+)>)/ig,"");

            if(!isFileTypeAllowed(allowedImagesTypes, fileName)) {
                error = "Die hochzuladene Datei muss ebenfalls eine "+allowedImagesTypes+"-Datei sein.";
                return false;
            }

            if(fileSize > maxImageFileSize) {
                error = "Datei "+fileName+" zu gro&szlig;.";
                return false;
            }
            
            /*
            var img = new Image();
            img.src = window.URL.createObjectURL( file );
            
            img.onload = function() {
                var width  = this.width;
                var height = this.height;

                if (
                    // Banner
                    (width ==  80 && height ==  31) || // Micro Bar
                    (width == 120 && height ==  90) || // Button 1
                    (width == 120 && height ==  60) || // Button 2
                    (width == 120 && height == 240) || // Vertical Banner
                    (width == 125 && height == 125) || // Square Button
                    (width == 234 && height ==  60) || // Half Banner
                    (width == 468 && height ==  60) || // Full Banner
                    (width == 728 && height ==  90) || // Leaderboard / Superbanner / Supersize Banner

                    // Rectangle
                    (width == 180 && height == 150) || // Rectangle
                    (width == 300 && height == 250) || // Medium Rectangle
                    (width == 240 && height == 400) || // Square Pop-Up
                    (width == 250 && height == 250) || // Vertical Rectangle
                    (width == 400 && height == 400) || // Superstitial / Flying Layer / AdLayer / Interstitial

                    // Skyscraper
                    (width == 160 && height == 600) || // Wide Skyscraper
                    (width == 120 && height == 600) || // Skyscraper
                    (width == 200 && height == 600) || // Wide Skyscraper alternative
                    (width == 300 && height == 600) || // Half Page Ad
                    (width == 420 && height == 600)    // Expandable Skyscraper
                ) {
                    //
                } else {
                    jQuery("#dummy_for_dimensions").html("false");
                    return false;
                };
            };
            */
        }
        
        return true;
    };

    jQuery("#upload_banners").on("change", function(){        
        if (!uploadImages(this)) {
            jQuery(".upload_banners_error").show().html(error);
        } else {
            jQuery("#form_upload_banners").trigger("submit");
        }
    });

})
