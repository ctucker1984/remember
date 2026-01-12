/**
 * All of the JavaScript for your admin-specific functionality should be
 * included in this file.
 */

(function($) {
	'use strict';
	
	// Settings page tabs
	$(document).ready(function() {
		// Handle tab clicks
		$('.nav-tab-wrapper a').on('click', function(e) {
			e.preventDefault();
			var target = $(this).attr('href');
			
			$('.nav-tab').removeClass('nav-tab-active');
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
			var $tab = $('.nav-tab-wrapper a[href="' + hash + '"]');
			if ($tab.length) {
				$tab.trigger('click');
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
	});
	
})(jQuery);
