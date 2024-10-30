// Javascript
jQuery(document).ready(function ($) {

    $("#gallery img").hover(function () {
        var imgWidth = $(this).width();
        var imgHeight = $(this).height();
        var position = $(this).position(),
            position_left = position.left,
            position_top = position.top;

        $(document).find(".caption").remove();

        $("<span class='caption' longdesc='" + $(this).attr("longdesc") + "'>" + $(this).attr("title") + "</span>").css({
            "width": imgWidth,
            "height": imgHeight,
            "left": position_left,
            "top": position_top
        }).insertAfter(this);
    });

    $(document).on("mouseleave", ".caption", function (e) {
        $(this).remove();
    });

    $(document).on("click", "#gallery img, .caption", function () {
        window.location.href = $(this).attr("longdesc");
    });


});

window.onload = function() {
    zoomwall.create(document.getElementById('gallery'));
};
