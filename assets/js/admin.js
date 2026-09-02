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

		function notificationCategoryFromHash(hash) {
			var match = /^#notifications(?:-(.+))?$/.exec(hash || '');
			if (!match) {
				return null;
			}
			return match[1] || '';
		}

		function showNotificationSubtab(category) {
			var $links = $('.remember-notification-subtab-link');
			if (!$links.length) {
				return '';
			}
			var $link = category ? $links.filter('[data-category="' + category + '"]') : $();
			if (!$link.length) {
				$link = $links.first();
			}
			var activeCategory = $link.data('category');
			$links.removeClass('nav-tab-active');
			$link.addClass('nav-tab-active');
			$('.remember-notification-panel').hide();
			$('#notification-panel-' + activeCategory).show();
			$('#remember-notification-category').val(activeCategory);
			return activeCategory ? '#notifications-' + activeCategory : '#notifications';
		}

		function activateSettingsTab(target) {
			var parentTarget = target;
			var notifCategory = notificationCategoryFromHash(target);
			if (notifCategory !== null) {
				parentTarget = '#notifications';
			}

			$settingsTabs.removeClass('nav-tab-active');
			$settingsTabs.filter('[href="' + parentTarget + '"]').addClass('nav-tab-active');

			$('.remember-settings-tab').hide();
			$(parentTarget).show();

			var hash = parentTarget;
			if (parentTarget === '#notifications') {
				hash = showNotificationSubtab(notifCategory);
			}

			if (history.pushState) {
				history.pushState(null, null, hash);
			}
		}

		$settingsTabs.on('click', function(e) {
			e.preventDefault();
			var target = $(this).attr('href');
			if (target === '#notifications') {
				var currentNotif = notificationCategoryFromHash(window.location.hash);
				if (currentNotif) {
					target = '#notifications-' + currentNotif;
				} else {
					var $first = $('.remember-notification-subtab-link').first();
					target = $first.length ? $first.attr('href') : '#notifications';
				}
			}
			activateSettingsTab(target);
		});

		$(document).on('click', '.remember-notification-subtab-link', function(e) {
			e.preventDefault();
			activateSettingsTab($(this).attr('href'));
		});

		// Handle URL hash on page load
		if (window.location.hash) {
			var hash = window.location.hash;
			// Combined Platforms tab replaced separate Social Media / IM tabs.
			if (hash === '#social-media' || hash === '#im-platforms') {
				hash = '#platforms';
			}
			if (notificationCategoryFromHash(hash) !== null) {
				activateSettingsTab(hash);
			} else {
				var $tab = $('.remember-settings #remember-main-settings > .nav-tab-wrapper a[href="' + hash + '"]');
				if ($tab.length) {
					$tab.trigger('click');
				}
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

		// Member profile print formats. Caps gate which modes appear; Ctrl/Cmd+P
		// is coerced to an allowed mode (or denied) so a menu is not required.
		var $printDetail = $('.remember-member-detail');
		if ($printDetail.length) {
			var $printMenu = $('.remember-print-menu');
			var $printToggle = $printMenu.find('.remember-print-menu__toggle');
			var $printList = $printMenu.find('.remember-print-menu__list');
			var defaultPrintMode = $printDetail.attr('data-print-mode') || 'denied';
			var originalDocumentTitle = document.title;

			var canPrintMode = function(mode) {
				if (mode === 'confidential') {
					return $printDetail.attr('data-print-can-confidential') === '1';
				}
				if (mode === 'event') {
					return $printDetail.attr('data-print-can-event') === '1';
				}
				return false;
			};

			var resolveAllowedPrintMode = function(preferred) {
				if (canPrintMode(preferred)) {
					return preferred;
				}
				if (canPrintMode('confidential')) {
					return 'confidential';
				}
				if (canPrintMode('event')) {
					return 'event';
				}
				return 'denied';
			};

			var closePrintMenu = function() {
				if (!$printList.length) {
					return;
				}
				$printList.prop('hidden', true);
				$printToggle.attr('aria-expanded', 'false');
			};

			var sanitizePrintFilenamePart = function(value) {
				return String(value || 'member')
					.trim()
					.replace(/\s+/g, '_')
					.replace(/[\\/:*?"<>|]+/g, '')
					.replace(/_+/g, '_')
					.replace(/^_|_$/g, '') || 'member';
			};

			var printDateStamp = function() {
				var now = new Date();
				var yy = String(now.getFullYear()).slice(-2);
				var mm = String(now.getMonth() + 1).padStart(2, '0');
				var dd = String(now.getDate()).padStart(2, '0');
				return yy + mm + dd;
			};

			var setPrintDocumentTitle = function(mode) {
				var name = sanitizePrintFilenamePart($printDetail.attr('data-print-display-name'));
				var stamp = printDateStamp();
				if (mode === 'event') {
					document.title = name + '_event_card_' + stamp;
				} else if (mode === 'confidential') {
					document.title = name + '_member_full_profile_' + stamp;
				} else {
					document.title = originalDocumentTitle;
				}
			};

			var runPrint = function(mode) {
				mode = resolveAllowedPrintMode(mode);
				$printDetail.attr('data-print-mode', mode);
				closePrintMenu();
				setPrintDocumentTitle(mode);
				// Yield once so the new mode is styled before the print snapshot.
				window.setTimeout(function() {
					window.print();
				}, 0);
			};

			if ($printMenu.length) {
				$printToggle.on('click', function(e) {
					e.preventDefault();
					if ($printToggle.attr('aria-expanded') === 'true') {
						closePrintMenu();
						return;
					}
					$printList.prop('hidden', false);
					$printToggle.attr('aria-expanded', 'true');
				});

				$printMenu.on('click', 'button[data-print-mode]', function(e) {
					e.preventDefault();
					runPrint($(this).attr('data-print-mode'));
				});

				$(document).on('click', function(e) {
					if (!$(e.target).closest('.remember-print-menu').length) {
						closePrintMenu();
					}
				});

				$(document).on('keydown', function(e) {
					if (e.key === 'Escape') {
						closePrintMenu();
					}
				});
			}

			// Ctrl/Cmd+P: keep an allowed mode, or blank the sheet when neither cap is held.
			$(window).on('beforeprint', function() {
				var mode = resolveAllowedPrintMode($printDetail.attr('data-print-mode') || defaultPrintMode);
				$printDetail.attr('data-print-mode', mode);
				setPrintDocumentTitle(mode);
			});

			$(window).on('afterprint', function() {
				$printDetail.attr('data-print-mode', defaultPrintMode);
				document.title = originalDocumentTitle;
			});
		}

		if (typeof window.rememberInitTimezoneComboboxes === 'function') {
			window.rememberInitTimezoneComboboxes();
		}
	});
	
})(jQuery);
