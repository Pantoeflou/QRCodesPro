/**
 * Dynamic QR Code admin panel JavaScript.
 *
 * Handles the dynamic QR code toggle, destination update via AJAX,
 * and copy-to-clipboard functionality.
 *
 * @package QRC_MS_Pro
 * @since   1.0.0
 */

/* global jQuery, qrcMsProDynamic */
(function ($) {
	'use strict';

	const panel = $('#qrc-ms-pro-dynamic-panel');
	if (!panel.length) {
		return;
	}

	const postId = panel.data('post-id');
	const details = $('#qrc-ms-pro-dynamic-details');
	const toggle = $('#qrc_ms_pro_is_dynamic');
	const destinationInput = $('#qrc_ms_pro_redirect_url');
	const updateBtn = $('#qrc-ms-pro-update-destination-btn');
	const messageBox = $('#qrc-ms-pro-ajax-message');

	/**
	 * Show/hide the dynamic details panel based on toggle state.
	 */
	toggle.on('change', function () {
		if (this.checked) {
			details.slideDown(200);
		} else {
			// Confirm before disabling if there's a short code.
			const shortCode = panel.find('.qrc-ms-pro-short-code').text();
			if (shortCode && !confirm(qrcMsProDynamic.i18n.confirmToggle)) {
				this.checked = true;
				return;
			}
			details.slideUp(200);
		}
	});

	/**
	 * Update destination URL via AJAX.
	 */
	if (updateBtn.length) {
		updateBtn.on('click', function () {
			const newUrl = destinationInput.val().trim();

			if (!newUrl) {
				showMessage(qrcMsProDynamic.i18n.error, 'error');
				return;
			}

			updateBtn.prop('disabled', true).text(qrcMsProDynamic.i18n.updating);

			$.post(qrcMsProDynamic.ajaxUrl, {
				action: 'qrc_ms_pro_update_destination',
				nonce: qrcMsProDynamic.nonce,
				post_id: postId,
				redirect_url: newUrl,
			})
				.done(function (response) {
					if (response.success) {
						showMessage(response.data.message, 'success');
					} else {
						showMessage(response.data.message || qrcMsProDynamic.i18n.error, 'error');
					}
				})
				.fail(function () {
					showMessage(qrcMsProDynamic.i18n.error, 'error');
				})
				.always(function () {
					updateBtn.prop('disabled', false).text(
						// Restore button text.
						wp && wp.i18n ? wp.i18n.__('Update Destination', 'qrc-ms-pro') : 'Update Destination'
					);
				});
		});
	}

	/**
	 * Copy URL to clipboard.
	 */
	panel.on('click', '.qrc-ms-pro-copy-url', function () {
		const targetId = $(this).data('target');
		const input = document.getElementById(targetId);

		if (input && navigator.clipboard) {
			navigator.clipboard.writeText(input.value).then(function () {
				const btn = $(input).siblings('.qrc-ms-pro-copy-url');
				const originalText = btn.text();
				btn.text('✓');
				setTimeout(function () {
					btn.text(originalText);
				}, 1500);
			});
		} else if (input) {
			// Fallback for older browsers.
			input.select();
			document.execCommand('copy');
		}
	});

	/**
	 * Display a message in the AJAX message box.
	 *
	 * @param {string} message The message text.
	 * @param {string} type    'success' or 'error'.
	 */
	function showMessage(message, type) {
		messageBox
			.removeClass('qrc-ms-pro-message-success qrc-ms-pro-message-error')
			.addClass('qrc-ms-pro-message-' + type)
			.text(message)
			.fadeIn(200);

		setTimeout(function () {
			messageBox.fadeOut(200);
		}, 4000);
	}
})(jQuery);
