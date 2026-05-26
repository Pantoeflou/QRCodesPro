/**
 * Pro Branding meta box JavaScript.
 *
 * Handles the media uploader for logo selection and removal.
 *
 * @package QRC_MS_Pro
 * @since   1.2.0
 */

/* global jQuery, wp */
(function ($) {
	'use strict';

	var frame;

	/**
	 * Open the WordPress media uploader for logo selection.
	 */
	$('#qrc-ms-pro-logo-upload').on('click', function (e) {
		e.preventDefault();

		if (frame) {
			frame.open();
			return;
		}

		frame = wp.media({
			title: 'Select Logo Image',
			button: { text: 'Use as Logo' },
			multiple: false,
			library: { type: 'image' }
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var thumbUrl = attachment.sizes && attachment.sizes.thumbnail
				? attachment.sizes.thumbnail.url
				: attachment.url;

			$('#qrc_ms_pro_logo_id').val(attachment.id);
			$('#qrc-ms-pro-logo-preview').html(
				'<img src="' + thumbUrl + '" style="max-width:150px;height:auto;" />'
			);
			$('#qrc-ms-pro-logo-remove').show();
		});

		frame.open();
	});

	/**
	 * Remove the selected logo.
	 */
	$('#qrc-ms-pro-logo-remove').on('click', function (e) {
		e.preventDefault();
		$('#qrc_ms_pro_logo_id').val('');
		$('#qrc-ms-pro-logo-preview').html('');
		$(this).hide();
	});

})(jQuery);
