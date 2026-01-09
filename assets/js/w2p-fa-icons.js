(function($) {
    if (typeof w2p === 'undefined') {
        window.w2p = {};
    }

    // List of common FontAwesome 6 Free Solid icons
    window.w2p.faIcons = [
        "fa-solid fa-house", "fa-solid fa-user", "fa-solid fa-check", "fa-solid fa-download", "fa-solid fa-image",
        "fa-solid fa-phone", "fa-solid fa-bars", "fa-solid fa-envelope", "fa-solid fa-star", "fa-solid fa-location-dot",
        "fa-solid fa-music", "fa-solid fa-wand-magic-sparkles", "fa-solid fa-heart", "fa-solid fa-arrow-right", "fa-solid fa-circle-xmark",
        "fa-solid fa-bomb", "fa-solid fa-poo", "fa-solid fa-camera-retro", "fa-solid fa-cloud", "fa-solid fa-comment",
        "fa-solid fa-pen-nib", "fa-solid fa-gear", "fa-solid fa-video", "fa-solid fa-trash", "fa-solid fa-share-nodes",
        "fa-solid fa-paper-plane", "fa-solid fa-calendar-days", "fa-solid fa-file", "fa-solid fa-magnifying-glass", "fa-solid fa-bell",
        "fa-solid fa-cart-shopping", "fa-solid fa-clipboard", "fa-solid fa-circle-info", "fa-solid fa-arrow-rotate-right", "fa-solid fa-truck",
        "fa-solid fa-money-bill", "fa-solid fa-gift", "fa-solid fa-globe", "fa-solid fa-wifi", "fa-solid fa-thumbs-up",
        "fa-solid fa-thumbs-down", "fa-solid fa-palette", "fa-solid fa-layer-group", "fa-solid fa-file-pdf", "fa-solid fa-file-word",
        "fa-solid fa-file-excel", "fa-solid fa-file-powerpoint", "fa-solid fa-file-archive", "fa-solid fa-file-code", "fa-solid fa-file-audio",
        "fa-solid fa-file-video", "fa-solid fa-file-image", "fa-solid fa-file-lines", "fa-solid fa-folder", "fa-solid fa-folder-open",
        "fa-solid fa-key", "fa-solid fa-lock", "fa-solid fa-lock-open", "fa-solid fa-shield-halved", "fa-solid fa-eye",
        "fa-solid fa-eye-slash", "fa-solid fa-database", "fa-solid fa-server", "fa-solid fa-code", "fa-solid fa-code-branch",
        "fa-solid fa-terminal", "fa-solid fa-keyboard", "fa-solid fa-gamepad", "fa-solid fa-ghost", "fa-solid fa-mug-hot",
        "fa-solid fa-umbrella", "fa-solid fa-cloud-sun", "fa-solid fa-bolt", "fa-solid fa-snowflake", "fa-solid fa-wind",
        "fa-solid fa-sun", "fa-solid fa-moon", "fa-solid fa-circle-half-stroke", "fa-solid fa-droplet", "fa-solid fa-fire",
        "fa-solid fa-tree", "fa-solid fa-leaf", "fa-solid fa-seedling", "fa-solid fa-plant-wilt", "fa-solid fa-paw",
        "fa-solid fa-cat", "fa-solid fa-dog", "fa-solid fa-fish", "fa-solid fa-bug", "fa-solid fa-spider",
        "fa-solid fa-anchor", "fa-solid fa-ship", "fa-solid fa-plane", "fa-solid fa-rocket", "fa-solid fa-train",
        "fa-solid fa-subway", "fa-solid fa-bus", "fa-solid fa-car", "fa-solid fa-bicycle", "fa-solid fa-person-walking",
        "fa-solid fa-person-running", "fa-solid fa-person-swimming", "fa-solid fa-person-biking", "fa-solid fa-person-hiking", "fa-solid fa-trophy",
        "fa-solid fa-medal", "fa-solid fa-lightbulb", "fa-solid fa-flask", "fa-solid fa-vial", "fa-solid fa-microscope",
        "fa-solid fa-stethoscope", "fa-solid fa-user-doctor", "fa-solid fa-briefcase-medical", "fa-solid fa-pills", "fa-solid fa-syringe",
        "fa-solid fa-bandage", "fa-solid fa-truck-medical", "fa-solid fa-hospital", "fa-solid fa-book", "fa-solid fa-book-open",
        "fa-solid fa-graduation-cap", "fa-solid fa-school", "fa-solid fa-building-columns", "fa-solid fa-pen-to-square", "fa-solid fa-eraser",
        "fa-solid fa-circle-question", "fa-solid fa-circle-exclamation", "fa-solid fa-triangle-exclamation", "fa-solid fa-info", "fa-solid fa-question",
        "fa-solid fa-exclamation", "fa-solid fa-plus", "fa-solid fa-minus", "fa-solid fa-xmark", "fa-solid fa-divide",
        "fa-solid fa-equals", "fa-solid fa-percent", "fa-solid fa-less-than", "fa-solid fa-greater-than", "fa-solid fa-infinity",
        "fa-solid fa-arrow-left", "fa-solid fa-arrow-up", "fa-solid fa-arrow-down", "fa-solid fa-arrows-left-right", "fa-solid fa-arrows-up-down",
        "fa-solid fa-rotate", "fa-solid fa-repeat", "fa-solid fa-list", "fa-solid fa-list-ol", "fa-solid fa-list-ul",
        "fa-solid fa-list-check", "fa-solid fa-indent", "fa-solid fa-outdent", "fa-solid fa-align-left", "fa-solid fa-align-center",
        "fa-solid fa-align-right", "fa-solid fa-align-justify", "fa-solid fa-font", "fa-solid fa-text-height", "fa-solid fa-text-width",
        "fa-solid fa-bold", "fa-solid fa-italic", "fa-solid fa-underline", "fa-solid fa-strikethrough", "fa-solid fa-link",
        "fa-solid fa-unlink", "fa-solid fa-quote-left", "fa-solid fa-quote-right", "fa-solid fa-code-commit", "fa-solid fa-cube",
        "fa-solid fa-cubes", "fa-solid fa-puzzle-piece", "fa-solid fa-filter", "fa-solid fa-sort", "fa-solid fa-sort-up",
        "fa-solid fa-sort-down", "fa-solid fa-sliders", "fa-solid fa-timeline", "fa-solid fa-newspaper", "fa-solid fa-rss"
    ];

})(jQuery);
