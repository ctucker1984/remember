/**
 * Nest setup pages in a Settings flyout on the reMember admin menu.
 */
(function () {
	'use strict';

	function currentPageSlug() {
		try {
			return new URLSearchParams(window.location.search).get('page') || '';
		} catch (e) {
			return '';
		}
	}

	function init() {
		if (!window.rememberSettingsFlyout || !rememberSettingsFlyout.items || !rememberSettingsFlyout.items.length) {
			return;
		}

		var submenu = document.querySelector('#toplevel_page_remember ul.wp-submenu');
		if (!submenu) {
			return;
		}

		var settingsAnchor = submenu.querySelector('a[href*="page=remember-settings"]');
		if (!settingsAnchor) {
			return;
		}

		var settingsItem = settingsAnchor.closest('li');
		if (!settingsItem || settingsItem.classList.contains('remember-has-flyout')) {
			return;
		}

		settingsItem.classList.add('remember-has-flyout');

		var caret = document.createElement('span');
		caret.className = 'remember-flyout-caret';
		caret.setAttribute('aria-hidden', 'true');
		settingsAnchor.appendChild(caret);

		var flyout = document.createElement('ul');
		flyout.className = 'remember-settings-flyout';
		flyout.setAttribute('role', 'menu');
		flyout.setAttribute('aria-label', rememberSettingsFlyout.label || 'Settings');

		var active = currentPageSlug();
		rememberSettingsFlyout.items.forEach(function (item) {
			var li = document.createElement('li');
			li.setAttribute('role', 'none');
			if (item.slug && item.slug === active) {
				li.className = 'current';
			}
			var a = document.createElement('a');
			a.href = item.url;
			a.textContent = item.label;
			a.setAttribute('role', 'menuitem');
			if (item.slug && item.slug === active) {
				a.setAttribute('aria-current', 'page');
			}
			li.appendChild(a);
			flyout.appendChild(li);
		});

		settingsItem.appendChild(flyout);

		// Touch / narrow: tap caret toggles flyout without following Settings.
		caret.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			settingsItem.classList.toggle('is-flyout-open');
		});

		document.addEventListener('click', function (e) {
			if (!settingsItem.contains(e.target)) {
				settingsItem.classList.remove('is-flyout-open');
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
