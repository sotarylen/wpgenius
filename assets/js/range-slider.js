/**
 * Range Slider Component
 * 
 * Provides an interactive range slider with real-time value display
 * 
 * @package WP_Genius
 */

(function ($) {
    'use strict';

    /**
     * Initialize all range sliders on the page
     */
    function initRangeSliders() {
        $('.w2p-range-slider').each(function () {
            const $slider = $(this);
            const $valueDisplay = $slider.siblings('.w2p-range-header').find('.w2p-range-value');

            // Update progress bar and value display
            function updateSlider() {
                const min = parseFloat($slider.attr('min')) || 0;
                const max = parseFloat($slider.attr('max')) || 100;
                const value = parseFloat($slider.val());
                const percentage = ((value - min) / (max - min)) * 100;

                // Update CSS variable for gradient
                $slider.css('--range-progress', percentage + '%');

                // Update value display
                if ($valueDisplay.length) {
                    const suffix = $slider.data('suffix') || '';
                    const prefix = $slider.data('prefix') || '';
                    $valueDisplay.text(prefix + value + suffix);
                }
            }

            // Initialize
            updateSlider();

            // Update on input
            $slider.on('input', updateSlider);
        });
    }

    /**
     * Create a range slider from a number input
     * 
     * @param {jQuery} $input - The number input element
     * @param {Object} options - Configuration options
     */
    window.w2pCreateRangeSlider = function ($input, options) {
        options = $.extend({
            min: $input.attr('min') || 0,
            max: $input.attr('max') || 100,
            step: $input.attr('step') || 1,
            value: $input.val() || 0,
            label: '',
            suffix: '',
            prefix: '',
            showMarks: false,
            marks: []
        }, options);

        // Create wrapper
        const $wrapper = $('<div class="w2p-range-group"></div>');

        // Create header with label and value
        const $header = $('<div class="w2p-range-header"></div>');
        if (options.label) {
            $header.append('<span class="w2p-range-label">' + options.label + '</span>');
        }
        $header.append('<span class="w2p-range-value">' + options.prefix + options.value + options.suffix + '</span>');

        // Create slider
        const $slider = $('<input type="range" class="w2p-range-slider" />')
            .attr('min', options.min)
            .attr('max', options.max)
            .attr('step', options.step)
            .attr('value', options.value)
            .attr('name', $input.attr('name'))
            .attr('id', $input.attr('id'))
            .data('suffix', options.suffix)
            .data('prefix', options.prefix);

        // Create marks if needed
        let $marks = null;
        if (options.showMarks && options.marks.length > 0) {
            $marks = $('<div class="w2p-range-marks"></div>');
            options.marks.forEach(mark => {
                $marks.append('<span class="w2p-range-mark">' + mark + '</span>');
            });
        }

        // Assemble
        $wrapper.append($header);
        $wrapper.append($slider);
        if ($marks) {
            $wrapper.append($marks);
        }

        // Replace original input
        $input.replaceWith($wrapper);

        // Initialize the new slider
        initRangeSliders();

        return $slider;
    };

    // Auto-initialize on document ready
    $(document).ready(function () {
        initRangeSliders();
    });

})(jQuery);
