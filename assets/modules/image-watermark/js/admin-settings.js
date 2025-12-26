(function ($) {

	// ready event
	$(function () {
		// enable watermark for
		$(document).on('change', '#df_option_everywhere, #df_option_cpt', function () {
			if ($('#cpt-specific input[type=radio]:checked').val() === 'everywhere')
				$('#cpt-select').fadeOut(300);
			else if ($('#cpt-specific input[type=radio]:checked').val() === 'specific')
				$('#cpt-select').fadeIn(300);
		});

		// watermark size
		$(document).on('change', '#watermark-type input[type=radio]', function () {
			var value = $('#watermark-type input[type=radio]:checked').val();

			if (value == 0) {
				$('.iw-watermark-size-custom').fadeOut(300);
				$('.iw-watermark-size-scaled').fadeOut(300);
			} else if (value == 1) {
				$('.iw-watermark-size-custom').fadeIn(300);
				$('.iw-watermark-size-scaled').fadeOut(300);
			} else {
				$('.iw-watermark-size-custom').fadeOut(300);
				$('.iw-watermark-size-scaled').fadeIn(300);
			}
		});

		// trigger change on page load to set initial state
		$('#watermark-type input[type=radio]:checked').trigger('change');

		$(document).on('click', '#reset_image_watermark_options', function () {
			return confirm(iwArgsSettings.resetToDefaults);
		});

	});

})(jQuery);