// Javascript

jQuery(document).ready(function ($) {

    $(document).on("click", ".timepicker", function(){
        $(this).timepicker({
            'timeFormat': 'g:ia',
            step: 60
        });
    });

    if ($(".automation").is(":checked"))
        $(".time").show();
    else
        $(".time").hide();

    $(document).on("click", ".automation", function () {
        if ($(this).is(":checked"))
            $(".time").show();
        else
            $(".time").hide();
    });

    // create new gallery
    $(document).on("submit", "#snippet-settings, .update-snippet-settings", function (e) {

        e.preventDefault();
        var formId = $(this).attr("id");
        var directory = $("#" + formId + "-directory").val(),
            username = $("#" + formId + "-username").val(),
            property = $("#" + formId + "-property1").val(),
            object = $("#" + formId + "-object1").val(),
            button_id = $(this).attr("id");

        if (directory == "" || directory == null)
            alert("Please enter directory name");
        else if (property == "" || property == null)
            alert("Please enter your property");
        else if (object == "" || object == null)
            alert("Please enter your object");
        else {
            $(".btn-disabled").attr("disabled", true);
            if (button_id == "snippet-settings") {
                $("#generate").val("Generating...");
            } else {
                $("#update").val("Updating...");
            }
            var data = {
                "action": "snippet"
            };
            data = $(this).serialize() + "&" + $.param(data);
            $.ajax({
                type: "POST",
                url: snippetrequest.ajaxurl,
                data: data,
                dataType: "json",
                success: function (data) {
                    if (data.Status == "Success") {
                        $("#autoload").load(location.href + " #autoload>*", "");
                        $("#status_generate").html("");
                        $("#status_generate").html('<div class="status status_success"><p>' + data.Message + '</p>');
                    } else {
                        $("#status_generate").html("");
                        $("#status_generate").html('<div class="status status_error"><p>' + data.Message + '</p>');
                    }
                    if (button_id == "snippet-settings") {
                        $("#snippet-settings")[0].reset();
                        $(".time").css("display","none");
                        $("#generate").val("Generate");
                    } else {
                        $("#update").val("Update");
                    }
                    $(".btn-disabled").attr("disabled", false);
                }
            });
            return false;
        }
    });

    // regenerate gallery from front end page
    $(".regenerate-gallery").click(function (e) {
        $(this).text("Regenerating...");
        $(this).attr("disabled", true);
        var data = {
            "action": "regeneratesnippet",
            "update_id": $(this).attr("id")
        };
        data = $.param(data);
        $.ajax({
            type: "POST",
            url: snippetrequest.ajaxurl,
            data: data,
            dataType: "json",
            success: function (data) {
                if (data.Status == "Success") {
                    $("#regenerate-gallery").text("Regenerate Gallery");
                    $("#regenerate-gallery").attr("disabled", false);
                    $("#notice").text("Your gallery has been regenerated").delay(2000).fadeOut(1000, function () {
                        window.location.href = data.Url;
                    });
                }
            }
        });
    });

    // regenerate gallery in plugin page
    $(document).on("click", ".btn-regenerate-gallery", function (e) {
        $(".btn-disabled").attr("disabled", true);
        $(this).text("Regenerating...");
        var data = {
            "action": "regeneratesnippet",
            "update_id": $(this).attr("id")
        };
        data = $.param(data);
        $.ajax({
            type: "POST",
            url: snippetrequest.ajaxurl,
            data: data,
            dataType: "json",
            success: function (data) {
                if (data.Status == "Success") {
                    $("#autoload").load(location.href + " #autoload>*", "");
                    $("#status_update, #status_generate").html("");
                    $("#status_update").html('<div class="status status_success"><p>' + data.Message + '</p>');
                } else {
                    $("#status_update, #status_generate").html("");
                    $("#status_update").html('<div class="status status_error"><p>' + data.Message + '</p>');
                }
                $(".btn-regenerate-gallery").text("Regenerate");
                $(".btn-disabled").attr("disabled", false);
            }
        });
    });

    // delete gallery from plugin page
    $(document).on("click", ".btn-delete-gallery", function (e) {
        $(".btn-disabled").attr("disabled", true);
        $(this).text("Deleting...");
        var data = {
            "action": "delete_settings",
            "delete_id": $(this).attr("id"),
            "delete_name": $(this).attr("data-name")
        };
        data = $.param(data);
        $.ajax({
            type: "POST",
            url: snippetrequest.ajaxurl,
            data: data,
            dataType: "json",
            success: function (data) {
                if (data.Status == "Success") {
                    $("#autoload").load(location.href + " #autoload>*", "");
                    $("#status_update, #status_generate").html("");
                    $("#status_update").html('<div class="status status_success"><p>' + data.Message + '</p>');
                } else {
                    $("#status_update, #status_generate").html("");
                    $("#status_update").html('<div class="status status_error"><p>' + data.Message + '</p>');
                }
                $(".btn-delete-gallery").text("Delete");
                $(".btn-disabled").attr("disabled", false);
            }
        });
    });

    // show/hide settings update form in galleries page
    $(document).on("click", ".toggle-form", function () {
        var id = $(this).attr("id");
        $("#toggle-form-container-" + id).slideToggle();
    });
    
    // save cron settings
    $(document).on("submit", "#cron-settings", function(e){
        
        e.preventDefault();

        var cron_key = $("#cron_key").val(),
            cron_name = $("#cron_name").val();

        if (cron_key == "" || cron_key == null)
            alert("Please enter your API key");
        else if (cron_name == "" || cron_name == null)
            alert("Please enter your cron name");
        else {
            $(".btn-disabled").attr("disabled", true);
            $("#cron_save").val("Saving...");
            var data = {
                "action": "save_cron"
            };
            data = $(this).serialize() + "&" + $.param(data);
            $.ajax({
                type: "POST",
                url: snippetrequest.ajaxurl,
                data: data,
                dataType: "json",
                success: function (data) {
                    if (data.Status == "Success") {
                        $("#cron-settings").load(location.href + " #cron-settings>*", "");
                        $("#status_generate").html("");
                        $("#status_generate").html('<div class="status status_success"><p>' + data.Message + '</p>');
                    } else {
                        $("#status_generate").html("");
                        $("#status_generate").html('<div class="status status_error"><p>' + data.Message + '</p>');
                    }
                    $(".btn-disabled").attr("disabled", false);
                    $("#cron_save").val("Save");
                }
            });
            return false;
        }
    });

});