jQuery(document).ready(function() {

    function isFileTypeAllowed(allowTypes, fileName) {

        var fileExtensions = allowTypes.toLowerCase().split(/[\s,]+/g);
        var ext = fileName.split(".").pop().toLowerCase();
        if(allowTypes != "*" && jQuery.inArray(ext, fileExtensions) < 0) {
            return false;
        }
        return true;
    }

    /**
     * Upload Previews
     */

    var upload_fsk16_bar = jQuery("#upload_fsk16 .bar");
    var upload_fsk16_percent = jQuery("#upload_fsk16 .percent");
    var upload_fsk16_status = jQuery("#upload_fsk16 .status");
    var upload_fsk16_progress = jQuery("#upload_fsk16 .progress");
    var upload_fsk16_abort = jQuery("#upload_fsk16 .abort_upload");
    var upload_fsk16_upload = jQuery("#upload_fsk16 .upload_image_fsk16");
    var upload_fsk16_released = jQuery("#upload_fsk16 #upload_image_fsk16_released");

    jQuery("#form_upload_image_fsk16").ajaxForm({
        clearForm: true,
        resetForm: true,
        forceSync: true,
        dataType:  "json",
        data: {released: upload_fsk16_released.is(":checked")},
        beforeSend: function(xhr) {
            jQuery(".upload_image_fsk16_error").hide().html("");
            upload_fsk16_progress.show();
            upload_fsk16_upload.hide();
            upload_fsk16_abort.show();
            upload_fsk16_released.attr("disabled", true);
            upload_fsk16_abort.click(function () {
                xhr.abort();
                jQuery("#upload_image_fsk16").val("");
                upload_fsk16_upload.show();
                upload_fsk16_abort.hide();
                upload_fsk16_released.attr("disabled", false);
                upload_fsk16_progress.hide();
            });

            upload_fsk16_status.empty();
            var percentVal = "0%";
            upload_fsk16_bar.width(percentVal)
            upload_fsk16_percent.html(percentVal);
        },
        uploadProgress: function(event, position, total, percentComplete) {
            var percentVal = percentComplete + "%";
            upload_fsk16_bar.width(percentVal)
            upload_fsk16_percent.html(percentVal);
        },
        success: function(data) {
            var percentVal = "100%";
            upload_fsk16_bar.width(percentVal)
            upload_fsk16_percent.html(percentVal);
            // If error
            if (data["jquery-upload-file-error"]) {
                var error = data["jquery-upload-file-error"];
                jQuery(".upload_image_fsk16_error").show().html(error);

                jQuery("#upload_image_fsk16").val("");
                upload_fsk16_upload.show();
                upload_fsk16_abort.hide();
                upload_fsk16_released.attr("disabled", false);
                upload_fsk16_progress.hide();

            } else {
                location.reload();
            }
        },
        complete: function(data) {

        }
    }); 

    var upload_fsk18_bar = jQuery("#upload_fsk18 .bar");
    var upload_fsk18_percent = jQuery("#upload_fsk18 .percent");
    var upload_fsk18_status = jQuery("#upload_fsk18 .status");
    var upload_fsk18_progress = jQuery("#upload_fsk18 .progress");
    var upload_fsk18_abort = jQuery("#upload_fsk18 .abort_upload");
    var upload_fsk18_upload = jQuery("#upload_fsk18 .upload_image_fsk18");
    var upload_fsk18_released = jQuery("#upload_fsk18 #upload_image_fsk18_released");

    jQuery("#form_upload_image_fsk18").ajaxForm({
        clearForm: true,
        resetForm: true,
        forceSync: true,
        dataType:  "json",
        data: {released: upload_fsk18_released.is(":checked")},
        beforeSend: function(xhr) {
            jQuery(".upload_image_fsk18_error").hide().html("");
            upload_fsk18_progress.show();
            upload_fsk18_upload.hide();
            upload_fsk18_abort.show();
            upload_fsk18_released.attr("disabled", true);
            upload_fsk18_abort.click(function () {
                xhr.abort();
                jQuery("#upload_image_fsk18").val("");
                upload_fsk18_upload.show();
                upload_fsk18_abort.hide();
                upload_fsk18_released.attr("disabled", false);
                upload_fsk18_progress.hide();
            });

            upload_fsk18_status.empty();
            var percentVal = "0%";
            upload_fsk18_bar.width(percentVal)
            upload_fsk18_percent.html(percentVal);
        },
        uploadProgress: function(event, position, total, percentComplete) {
            var percentVal = percentComplete + "%";
            upload_fsk18_bar.width(percentVal)
            upload_fsk18_percent.html(percentVal);
        },
        success: function(data) {
            var percentVal = "100%";
            upload_fsk18_bar.width(percentVal)
            upload_fsk18_percent.html(percentVal);
            // If error
            if (data["jquery-upload-file-error"]) {
                var error = data["jquery-upload-file-error"];
                jQuery(".upload_image_fsk18_error").show().html(error);

                jQuery("#upload_image_fsk18").val("");
                upload_fsk18_upload.show();
                upload_fsk18_abort.hide();
                upload_fsk18_released.attr("disabled", false);
                upload_fsk18_progress.hide();

            } else {
                location.reload();
            }
        },
        complete: function(data) {

        }
    }); 

    var upload_images_bar = jQuery("#upload_photos .bar");
    var upload_images_percent = jQuery("#upload_photos .percent");
    var upload_images_status = jQuery("#upload_photos .status");
    var upload_images_progress = jQuery("#upload_photos .progress");
    var upload_images_abort = jQuery("#upload_photos .abort_upload");
    var upload_images_upload = jQuery("#upload_photos .upload_images");
    var upload_images_released = jQuery("#upload_photos #upload_images_released");

    jQuery("#form_upload_images").ajaxForm({
        clearForm: true,
        resetForm: true,
        forceSync: true,
        dataType:  "json",
        data: {released: upload_images_released.is(":checked")},
        beforeSend: function(xhr) {
            jQuery(".upload_image_images_error").hide().html("");
            upload_images_progress.show();
            upload_images_upload.hide();
            upload_images_abort.show();
            upload_images_released.attr("disabled", true);
            upload_images_abort.click(function () {
                xhr.abort();
                jQuery("#upload_images").val("");
                upload_images_upload.show();
                upload_images_abort.hide();
                upload_images_released.attr("disabled", false);
                upload_images_progress.hide();
            });

            upload_images_status.empty();
            var percentVal = "0%";
            upload_images_bar.width(percentVal)
            upload_images_percent.html(percentVal);
        },
        uploadProgress: function(event, position, total, percentComplete) {
            var percentVal = percentComplete + "%";
            upload_images_bar.width(percentVal)
            upload_images_percent.html(percentVal);
        },
        success: function(data) {
            var percentVal = "100%";
            upload_images_bar.width(percentVal)
            upload_images_percent.html(percentVal);
            // If error
            if (data["jquery-upload-file-error"]) {
                var error = data["jquery-upload-file-error"];
                jQuery(".upload_images_error").show().html(error);

                jQuery("#upload_images").val("");
                upload_images_upload.show();
                upload_images_abort.hide();
                upload_images_released.attr("disabled", false);
                upload_images_progress.hide();

            } else {
                location.reload();
            }
        },
        complete: function(data) {

        }
    }); 


    var allowedImageTypes = "jpg,jpeg";
    var allowedImagesTypes = "jpg,jpeg,png";
    var maxImageFileSize = 6291456; // 5 MB = 5242880
    var maxFileUploads = 200;

    function uploadImage(f) {
        var file = f.files[0],
        fileName = file.name;
        fileSize = file.size;
        
        fileName = fileName.replace(/(<([^>]+)>)/ig,"");

        if(!isFileTypeAllowed(allowedImageTypes, fileName)) {
            error = "Es sind nur "+allowedImageTypes+" erlaubt.";
            return false;
        }

        if(fileSize > maxImageFileSize) {
            error = "Datei zu gro&szlig;.";
            return false;
        }
        return true;
    };
    
    function uploadImages(f) {

        var files = f.files;
        
        var count_files = files.length;
                
        if (count_files > maxFileUploads) {
            error = "Du darfst nur maximal "+maxFileUploads+" Bilder gleichzeitig hochladen.";
            return false;
        }
        
        for($i=0;$i<count_files;$i++) {
            var file = f.files[$i];
            var fileName = file.name;
            var fileSize = file.size;
            
            fileName = fileName.replace(/(<([^>]+)>)/ig,"");
    
            if(!isFileTypeAllowed(allowedImagesTypes, fileName)) {
                error = "Es sind nur "+allowedImagesTypes+" erlaubt.";
                return false;
            }

            if(fileSize > maxImageFileSize) {
                error = "Datei "+fileName+" zu gro&szlig;.";
                return false;
            }
        }
        return true;
    };
    
    jQuery("#upload_image_fsk16").on("change", function(){
        if (!uploadImage(this)) {
            jQuery(".upload_image_fsk16_error").show().html(error);
        } else {
            jQuery("#form_upload_image_fsk16").trigger("submit");
        }
    });

    jQuery("#upload_image_fsk18").on("change", function(){
        if (!uploadImage(this)) {
            jQuery(".upload_image_fsk18_error").show().html(error);
        } else {
            jQuery("#form_upload_image_fsk18").trigger("submit");
        }
    });
    
    jQuery("#upload_images").on("change", function(){
        if (!uploadImages(this)) {
            jQuery(".upload_images_error").show().html(error);
        } else {
            jQuery("#form_upload_images").trigger("submit");
        }
    });

})
