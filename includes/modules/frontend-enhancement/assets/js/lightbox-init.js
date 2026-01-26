// Disable Magnific Popup on article images immediately
(function ($) {
    "use strict";

    // Capture click events before theme lightbox
    document.addEventListener("click", function (e) {
        var target = e.target;

        // Check if clicked element is an image
        if (target.tagName === "IMG") {
            var $img = $(target);
            // Priority 1: Check custom container
            var inCustomContainer = $img.closest("#w2p-post-content").length > 0;

            if (inCustomContainer) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                // Manually trigger WP Genius Lightbox after preventing theme lightbox
                if (window.wpgLightbox && window.wpgLightbox.open) {
                    var index = window.wpgLightbox.images.findIndex(function (img) {
                        return img.element === target;
                    });
                    if (index >= 0) {
                        window.wpgLightbox.open(index);
                    }
                }

                return false;
            }
        }
    }, true); // Use capture phase (runs before bubble phase)
})(jQuery);
