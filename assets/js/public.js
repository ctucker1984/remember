/**
 * All of the JavaScript for your public-facing functionality should be
 * included in this file.
 */

(function($) {
	'use strict';

	/**
	 * Keep the "Display name publicly as" nickname option in sync while typing
	 * (same idea as WordPress core user profile).
	 */
	function initDisplayNameNicknameSync() {
		var $nickname = $('#nickname');
		var $display = $('#display_name');
		if (!$nickname.length || !$display.length) {
			return;
		}

		var previousNickname = $.trim($nickname.val());

		function syncNicknameOption() {
			var nextNickname = $.trim($nickname.val());
			if (!nextNickname) {
				return;
			}

			var $options = $display.find('option');
			var $match = $options.filter(function() {
				return $(this).val() === previousNickname;
			}).first();

			if ($match.length) {
				$match.val(nextNickname).text(nextNickname);
			} else {
				var exists = $options.filter(function() {
					return $(this).val() === nextNickname;
				}).length > 0;
				if (!exists) {
					$display.prepend($('<option></option>').val(nextNickname).text(nextNickname));
				}
			}

			if ($display.val() === previousNickname || !$display.val()) {
				$display.val(nextNickname);
			}

			previousNickname = nextNickname;
		}

		$nickname.on('input change', syncNicknameOption);
	}

	$(function() {
		initDisplayNameNicknameSync();
	});

})(jQuery);
