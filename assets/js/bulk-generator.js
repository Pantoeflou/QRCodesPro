/**
 * Bulk Generator admin scripts.
 *
 * Handles CSV upload, WooCommerce bulk generation, and ZIP download.
 *
 * @package QRC_MS_Pro
 * @since   1.2.0
 */

/* global jQuery, qrcMsProBulk */
(function ($) {
	'use strict';

	var BulkGenerator = {
		init: function () {
			this.bindEvents();
		},

		bindEvents: function () {
			$('#qrc-ms-pro-bulk-csv-form').on('submit', this.handleCsvUpload.bind(this));
			$('#qrc-ms-pro-bulk-woo-btn').on('click', this.handleWooGenerate.bind(this));
			$(document).on('click', '.qrc-ms-pro-download-zip', this.handleDownloadZip.bind(this));
		},

		handleCsvUpload: function (e) {
			e.preventDefault();

			var form = e.target;
			var formData = new FormData(form);
			formData.append('action', 'qrc_ms_pro_bulk_process_csv');
			formData.append('nonce', qrcMsProBulk.nonce);

			this.setStatus(qrcMsProBulk.i18n.processing);
			this.toggleButtons(true);

			$.ajax({
				url: qrcMsProBulk.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function (response) {
					if (response.success) {
						BulkGenerator.setStatus(response.data.message, 'success');
						if (response.data.batch_key) {
							BulkGenerator.showDownloadButton(response.data.batch_key);
						}
						if (response.data.errors && response.data.errors.length > 0) {
							BulkGenerator.showErrors(response.data.errors);
						}
					} else {
						BulkGenerator.setStatus(response.data.message || qrcMsProBulk.i18n.error, 'error');
					}
				},
				error: function () {
					BulkGenerator.setStatus(qrcMsProBulk.i18n.error, 'error');
				},
				complete: function () {
					BulkGenerator.toggleButtons(false);
				}
			});
		},

		handleWooGenerate: function (e) {
			e.preventDefault();

			var templateId = $('#qrc-ms-pro-bulk-template').val() || 0;
			var format = $('#qrc-ms-pro-bulk-format').val() || 'svg';
			var size = $('#qrc-ms-pro-bulk-size').val() || 300;

			this.setStatus(qrcMsProBulk.i18n.processing);
			this.toggleButtons(true);
			this.processWooBatch(0, '', templateId, format, size);
		},

		processWooBatch: function (offset, batchKey, templateId, format, size) {
			$.ajax({
				url: qrcMsProBulk.ajaxUrl,
				type: 'POST',
				data: {
					action: 'qrc_ms_pro_bulk_woo',
					nonce: qrcMsProBulk.nonce,
					offset: offset,
					batch_key: batchKey,
					template_id: templateId,
					format: format,
					size: size
				},
				success: function (response) {
					if (response.success) {
						BulkGenerator.setStatus(response.data.message);
						if (response.data.has_more) {
							BulkGenerator.processWooBatch(
								response.data.next_offset,
								response.data.batch_key,
								templateId,
								format,
								size
							);
						} else {
							BulkGenerator.setStatus(qrcMsProBulk.i18n.complete, 'success');
							if (response.data.batch_key) {
								BulkGenerator.showDownloadButton(response.data.batch_key);
							}
							BulkGenerator.toggleButtons(false);
						}
					} else {
						BulkGenerator.setStatus(response.data.message || qrcMsProBulk.i18n.error, 'error');
						BulkGenerator.toggleButtons(false);
					}
				},
				error: function () {
					BulkGenerator.setStatus(qrcMsProBulk.i18n.error, 'error');
					BulkGenerator.toggleButtons(false);
				}
			});
		},

		handleDownloadZip: function (e) {
			e.preventDefault();
			var batchKey = $(e.currentTarget).data('batch-key');

			this.setStatus(qrcMsProBulk.i18n.downloading);

			$.ajax({
				url: qrcMsProBulk.ajaxUrl,
				type: 'POST',
				data: {
					action: 'qrc_ms_pro_bulk_download_zip',
					nonce: qrcMsProBulk.nonce,
					batch_key: batchKey
				},
				success: function (response) {
					if (response.success && response.data.download_url) {
						window.location.href = response.data.download_url;
						BulkGenerator.setStatus(response.data.message, 'success');
					} else {
						BulkGenerator.setStatus(response.data.message || qrcMsProBulk.i18n.error, 'error');
					}
				},
				error: function () {
					BulkGenerator.setStatus(qrcMsProBulk.i18n.error, 'error');
				}
			});
		},

		setStatus: function (message, type) {
			var $status = $('#qrc-ms-pro-bulk-status');
			$status.text(message).removeClass('notice-success notice-error notice-info').show();
			if (type === 'success') {
				$status.addClass('notice-success');
			} else if (type === 'error') {
				$status.addClass('notice-error');
			} else {
				$status.addClass('notice-info');
			}
		},

		showDownloadButton: function (batchKey) {
			$('#qrc-ms-pro-bulk-download').html(
				'<button type="button" class="button button-primary qrc-ms-pro-download-zip" data-batch-key="' + batchKey + '">' +
				'<span class="dashicons dashicons-download" style="vertical-align:middle;"></span> Download ZIP' +
				'</button>'
			).show();
		},

		showErrors: function (errors) {
			var html = '<div class="notice notice-warning"><ul>';
			errors.forEach(function (err) {
				html += '<li>' + err + '</li>';
			});
			html += '</ul></div>';
			$('#qrc-ms-pro-bulk-errors').html(html).show();
		},

		toggleButtons: function (disabled) {
			$('#qrc-ms-pro-bulk-csv-form button, #qrc-ms-pro-bulk-woo-btn').prop('disabled', disabled);
		}
	};

	$(document).ready(function () {
		BulkGenerator.init();
	});
})(jQuery);
