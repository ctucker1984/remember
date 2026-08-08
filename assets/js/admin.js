/**
 * All of the JavaScript for your admin-specific functionality should be
 * included in this file.
 */

(function($) {
	'use strict';
	
	// Settings page tabs
	$(document).ready(function() {
		// Handle tab clicks (Settings page only — do not intercept other nav-tab-wrappers).
		var $settingsTabs = $('.remember-settings #remember-main-settings > .nav-tab-wrapper a');
		$settingsTabs.on('click', function(e) {
			e.preventDefault();
			var target = $(this).attr('href');
			
			$settingsTabs.removeClass('nav-tab-active');
			$(this).addClass('nav-tab-active');
			
			$('.remember-settings-tab').hide();
			$(target).show();
			
			// Update URL hash without scrolling
			if (history.pushState) {
				history.pushState(null, null, target);
			}
		});
		
		// Handle URL hash on page load
		if (window.location.hash) {
			var hash = window.location.hash;
			var $tab = $('.remember-settings #remember-main-settings > .nav-tab-wrapper a[href="' + hash + '"]');
			if ($tab.length) {
				$tab.trigger('click');
			}
		} else {
			// Support ?tab=xero (and similar) from OAuth redirect URIs.
			var params = new URLSearchParams(window.location.search);
			var tabParam = params.get('tab');
			if (tabParam) {
				var $tabFromQuery = $('.remember-settings #remember-main-settings > .nav-tab-wrapper a[href="#' + tabParam + '"]');
				if ($tabFromQuery.length) {
					$tabFromQuery.trigger('click');
				}
			}
		}
		
		// Copy shortcode to clipboard
		$('.remember-copy-shortcode').on('click', function(e) {
			e.preventDefault();
			var shortcode = $(this).data('shortcode');
			var $button = $(this);
			
			// Create temporary textarea to copy from
			var $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(shortcode).select();
			document.execCommand('copy');
			$temp.remove();
			
			// Show feedback
			var originalText = $button.text();
			$button.text('✓ Copied!').prop('disabled', true);
			setTimeout(function() {
				$button.text(originalText).prop('disabled', false);
			}, 2000);
		});

		// Ticket logo override media picker
		var ticketLogoFrame;
		$('#remember-ticket-logo-select').on('click', function(e) {
			e.preventDefault();
			if (typeof wp === 'undefined' || !wp.media) {
				return;
			}
			if (ticketLogoFrame) {
				ticketLogoFrame.open();
				return;
			}
			ticketLogoFrame = wp.media({
				title: 'Select ticket logo',
				button: { text: 'Use as ticket logo' },
				multiple: false
			});
			ticketLogoFrame.on('select', function() {
				var attachment = ticketLogoFrame.state().get('selection').first().toJSON();
				$('#ticket_logo_id').val(attachment.id);
				var url = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;
				$('#remember-ticket-logo-preview').html('<img src="' + url + '" alt="" style="max-height: 72px; width: auto;">');
				$('#remember-ticket-logo-clear').prop('disabled', false);
			});
			ticketLogoFrame.open();
		});
		$('#remember-ticket-logo-clear').on('click', function(e) {
			e.preventDefault();
			$('#ticket_logo_id').val('0');
			$('#remember-ticket-logo-preview').empty();
			$(this).prop('disabled', true);
		});

		if (typeof window.rememberInitTimezoneComboboxes === 'function') {
			window.rememberInitTimezoneComboboxes();
		}
	});
	
})(jQuery);
