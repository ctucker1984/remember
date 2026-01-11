/**
 * All of the JavaScript for your admin-specific functionality should be
 * included in this file.
 */

(function($) {
	'use strict';
	
	// Settings page tabs
	$(document).ready(function() {
		$('.nav-tab-wrapper a').on('click', function(e) {
			e.preventDefault();
			var target = $(this).attr('href');
			
			$('.nav-tab').removeClass('nav-tab-active');
			$(this).addClass('nav-tab-active');
			
			$('.remember-settings-tab').hide();
			$(target).show();
		});
	});
	
})(jQuery);
