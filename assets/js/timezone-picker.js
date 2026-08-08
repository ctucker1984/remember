/**
 * Single-control timezone combobox: type in the field to jump to matches (e.g. "Chicago").
 * No separate filter input — the control itself is the typeahead.
 */
(function($) {
	'use strict';

	function selectedLabel($select) {
		var $opt = $select.find('option').filter(function() {
			return this.selected && this.value;
		}).first();
		if ($opt.length) {
			return $.trim($opt.text());
		}
		return $select.val() || '';
	}

	function collectEntries($select) {
		var entries = [];
		$select.find('optgroup').each(function() {
			var group = $(this).attr('label') || '';
			$(this).children('option').each(function() {
				if (!this.value) {
					return;
				}
				entries.push({
					value: this.value,
					label: $.trim($(this).text()),
					group: group
				});
			});
		});
		$select.children('option').each(function() {
			if (!this.value) {
				return;
			}
			entries.push({
				value: this.value,
				label: $.trim($(this).text()),
				group: ''
			});
		});
		return entries;
	}

	function initOne($wrap) {
		if ($wrap.data('rememberTzCombo')) {
			return;
		}
		$wrap.data('rememberTzCombo', true);

		var $select = $wrap.find('select.remember-timezone-select');
		if (!$select.length) {
			return;
		}

		// Remove legacy separate filter field if present.
		$wrap.find('.remember-timezone-search').remove();

		var entries = collectEntries($select);
		var $input = $('<input type="text" class="remember-timezone-combobox-input" autocomplete="off" spellcheck="false" />');
		var $list = $('<ul class="remember-timezone-combobox-list" role="listbox" hidden></ul>');
		var activeIndex = -1;
		var open = false;

		if ($select.prop('required')) {
			$input.attr('required', 'required');
		}
		$input.attr('id', $select.attr('id') + '_combo');
		$input.attr('placeholder', $select.find('option:selected').length ? '' : 'Type a city or region…');
		$input.val(selectedLabel($select));

		$select.addClass('remember-timezone-select--sr');
		$select.attr('tabindex', '-1');
		$select.attr('aria-hidden', 'true');

		$wrap.append($input);
		$wrap.append($list);

		function closeList() {
			open = false;
			activeIndex = -1;
			$list.attr('hidden', 'hidden').empty();
			$input.removeAttr('aria-activedescendant');
		}

		function setValue(value, label) {
			$select.val(value).trigger('change');
			$input.val(label);
			closeList();
		}

		function filtered(query) {
			var q = $.trim(query).toLowerCase();
			if (!q) {
				return entries.slice(0, 80);
			}
			return entries.filter(function(e) {
				return (
					e.label.toLowerCase().indexOf(q) !== -1 ||
					e.value.toLowerCase().indexOf(q) !== -1 ||
					(e.group && e.group.toLowerCase().indexOf(q) !== -1)
				);
			}).slice(0, 80);
		}

		function renderList(query) {
			var items = filtered(query);
			$list.empty();
			if (!items.length) {
				$list.append($('<li class="remember-timezone-combobox-empty"></li>').text('No matches'));
				$list.removeAttr('hidden');
				open = true;
				activeIndex = -1;
				return;
			}

			var lastGroup = null;
			items.forEach(function(item, idx) {
				if (item.group && item.group !== lastGroup) {
					lastGroup = item.group;
					$list.append(
						$('<li class="remember-timezone-combobox-group" role="presentation"></li>').text(item.group)
					);
				}
				var $li = $('<li class="remember-timezone-combobox-option" role="option"></li>');
				$li.attr('data-value', item.value);
				$li.attr('data-index', String(idx));
				$li.attr('id', $select.attr('id') + '_opt_' + idx);
				$li.text(item.label);
				if (item.value === $select.val()) {
					$li.addClass('is-selected');
				}
				$list.append($li);
			});
			$list.data('items', items);
			$list.removeAttr('hidden');
			open = true;
			activeIndex = 0;
			highlightActive();
		}

		function highlightActive() {
			var $opts = $list.find('.remember-timezone-combobox-option');
			$opts.removeClass('is-active');
			if (activeIndex < 0 || activeIndex >= $opts.length) {
				return;
			}
			var $active = $opts.eq(activeIndex);
			$active.addClass('is-active');
			$input.attr('aria-activedescendant', $active.attr('id'));
			if ($active[0] && $active[0].scrollIntoView) {
				$active[0].scrollIntoView({ block: 'nearest' });
			}
		}

		$input.on('focus', function() {
			renderList($input.val());
			$input.select();
		});

		$input.on('input', function() {
			renderList($input.val());
		});

		$input.on('keydown', function(e) {
			if (!open && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
				renderList($input.val());
			}
			var $opts = $list.find('.remember-timezone-combobox-option');
			if (e.key === 'ArrowDown') {
				e.preventDefault();
				if (!$opts.length) {
					return;
				}
				activeIndex = Math.min(activeIndex + 1, $opts.length - 1);
				highlightActive();
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				if (!$opts.length) {
					return;
				}
				activeIndex = Math.max(activeIndex - 1, 0);
				highlightActive();
			} else if (e.key === 'Enter') {
				if (open && activeIndex >= 0) {
					e.preventDefault();
					var items = $list.data('items') || [];
					if (items[activeIndex]) {
						setValue(items[activeIndex].value, items[activeIndex].label);
					}
				}
			} else if (e.key === 'Escape') {
				closeList();
				$input.val(selectedLabel($select));
			}
		});

		$list.on('mousedown', '.remember-timezone-combobox-option', function(e) {
			e.preventDefault();
			var value = $(this).attr('data-value');
			var label = $.trim($(this).text());
			setValue(value, label);
		});

		$input.on('blur', function() {
			window.setTimeout(function() {
				closeList();
				// Snap display back to the committed select value if typed text isn't an exact pick.
				var typed = $.trim($input.val()).toLowerCase();
				var match = entries.filter(function(e) {
					return e.label.toLowerCase() === typed || e.value.toLowerCase() === typed;
				})[0];
				if (match) {
					setValue(match.value, match.label);
				} else {
					$input.val(selectedLabel($select));
				}
			}, 150);
		});
	}

	function initTimezoneComboboxes() {
		$('.remember-timezone-picker').each(function() {
			initOne($(this));
		});
	}

	window.rememberInitTimezoneComboboxes = initTimezoneComboboxes;

	$(function() {
		initTimezoneComboboxes();
	});
})(jQuery);
