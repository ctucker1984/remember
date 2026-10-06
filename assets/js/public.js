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

	/**
	 * Circular photo preview with drag-to-recenter and zoom.
	 * On form submit, crops the framed square into the file input.
	 * Works for profile edit and member registration (same markup class).
	 */
	function initOnePhotoCropper($wrap) {
		var $form = $wrap.closest('form');
		var $file = $wrap.find('input[type="file"][name="photo_file"]');
		var $current = $wrap.find('.remember-profile-photo-current');
		var $cropper = $wrap.find('.remember-profile-photo-cropper');
		var $viewport = $wrap.find('.remember-profile-photo-cropper-viewport');
		var $img = $wrap.find('.remember-profile-photo-cropper-image');
		var $zoomRange = $wrap.find('.remember-photo-zoom-range');
		var $zoomIn = $wrap.find('.remember-photo-zoom-in');
		var $zoomOut = $wrap.find('.remember-photo-zoom-out');
		var $clear = $wrap.find('.remember-photo-clear');
		var outputSize = parseInt($wrap.data('output-size'), 10) || 800;

		if (!$file.length || !$cropper.length || !$viewport.length || !$img.length) {
			return;
		}

		var objectUrl = null;
		var naturalW = 0;
		var naturalH = 0;
		var zoom = 1;
		var tx = 0;
		var ty = 0;
		var dragging = false;
		var dragStartX = 0;
		var dragStartY = 0;
		var dragOriginTx = 0;
		var dragOriginTy = 0;
		var ready = false;

		function viewportSize() {
			return $viewport.innerWidth() || 200;
		}

		function baseScale() {
			var v = viewportSize();
			if (!naturalW || !naturalH) {
				return 1;
			}
			return v / Math.min(naturalW, naturalH);
		}

		function maxOffset() {
			var v = viewportSize();
			var scale = baseScale() * zoom;
			return {
				x: Math.max(0, (naturalW * scale - v) / 2),
				y: Math.max(0, (naturalH * scale - v) / 2)
			};
		}

		function clampOffsets() {
			var max = maxOffset();
			tx = Math.max(-max.x, Math.min(max.x, tx));
			ty = Math.max(-max.y, Math.min(max.y, ty));
		}

		function applyTransform() {
			clampOffsets();
			var v = viewportSize();
			var scale = baseScale() * zoom;
			var width = naturalW * scale;
			var height = naturalH * scale;
			var left = (v / 2) - (width / 2) + tx;
			var top = (v / 2) - (height / 2) + ty;
			var el = $img[0];
			// left/top — not transform. iOS Safari clips transform children
			// incorrectly when the parent has overflow:hidden and border-radius.
			el.style.setProperty('width', width + 'px', 'important');
			el.style.setProperty('height', height + 'px', 'important');
			el.style.setProperty('max-width', 'none', 'important');
			el.style.setProperty('max-height', 'none', 'important');
			el.style.setProperty('left', left + 'px', 'important');
			el.style.setProperty('top', top + 'px', 'important');
			el.style.setProperty('transform', 'none', 'important');
		}

		function setZoom(next) {
			zoom = Math.max(1, Math.min(3, next));
			$zoomRange.val(zoom.toFixed(2));
			applyTransform();
		}

		function revokeObjectUrl() {
			if (objectUrl) {
				URL.revokeObjectURL(objectUrl);
				objectUrl = null;
			}
		}

		function hideCropper() {
			ready = false;
			revokeObjectUrl();
			$img.attr('src', '');
			$img.removeAttr('style');
			$cropper.prop('hidden', true);
			if ($current.length && $current.children().length) {
				$current.prop('hidden', false);
			}
		}

		function showCropper(file) {
			if (!file || !file.type || file.type.indexOf('image/') !== 0) {
				hideCropper();
				return;
			}

			revokeObjectUrl();
			objectUrl = URL.createObjectURL(file);
			ready = false;
			zoom = 1;
			tx = 0;
			ty = 0;
			$zoomRange.val('1');

			function layoutPreview() {
				if (!naturalW || !naturalH) {
					return;
				}
				ready = true;
				if ($current.length) {
					$current.prop('hidden', true);
				}
				$cropper.prop('hidden', false);
				window.requestAnimationFrame(function() {
					window.requestAnimationFrame(applyTransform);
				});
			}

			function onDecoded(img) {
				naturalW = img.naturalWidth || img.width;
				naturalH = img.naturalHeight || img.height;
				if (!naturalW || !naturalH) {
					hideCropper();
					return;
				}
				$img.off('load.rememberPhoto').one('load.rememberPhoto', layoutPreview);
				$img.attr('src', objectUrl);
				if ($img[0].complete) {
					layoutPreview();
				}
			}

			var probe = new Image();
			probe.onload = function() {
				onDecoded(probe);
			};
			probe.onerror = function() {
				hideCropper();
			};
			probe.src = objectUrl;
		}

		function clearSelectedFile() {
			$file.val('');
			hideCropper();
		}

		function cropToFile(callback) {
			if (!ready || !$img[0].complete || !naturalW || !naturalH) {
				callback(null);
				return;
			}

			var v = viewportSize();
			var scale = baseScale() * zoom;
			var imgLeft = (v / 2) - (naturalW * scale / 2) + tx;
			var imgTop = (v / 2) - (naturalH * scale / 2) + ty;
			var sx = (0 - imgLeft) / scale;
			var sy = (0 - imgTop) / scale;
			var sSize = v / scale;

			sx = Math.max(0, Math.min(naturalW - sSize, sx));
			sy = Math.max(0, Math.min(naturalH - sSize, sy));
			sSize = Math.min(sSize, naturalW, naturalH);

			var canvas = document.createElement('canvas');
			canvas.width = outputSize;
			canvas.height = outputSize;
			var ctx = canvas.getContext('2d');
			if (!ctx) {
				callback(null);
				return;
			}

			ctx.imageSmoothingEnabled = true;
			ctx.imageSmoothingQuality = 'high';
			ctx.drawImage($img[0], sx, sy, sSize, sSize, 0, 0, outputSize, outputSize);

			canvas.toBlob(function(blob) {
				if (!blob) {
					callback(null);
					return;
				}
				var name = 'profile-photo.jpg';
				var original = $file[0].files && $file[0].files[0] ? $file[0].files[0].name : '';
				if (original) {
					name = original.replace(/\.[^.]+$/, '') + '-cropped.jpg';
				}
				callback(new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() }));
			}, 'image/jpeg', 0.92);
		}

		$file.on('change', function() {
			var file = this.files && this.files[0] ? this.files[0] : null;
			if (!file) {
				hideCropper();
				return;
			}
			showCropper(file);
		});

		$clear.on('click', function(e) {
			e.preventDefault();
			clearSelectedFile();
		});

		$zoomRange.on('input change', function() {
			setZoom(parseFloat(this.value) || 1);
		});

		$zoomIn.on('click', function(e) {
			e.preventDefault();
			setZoom(zoom + 0.1);
		});

		$zoomOut.on('click', function(e) {
			e.preventDefault();
			setZoom(zoom - 0.1);
		});

		$viewport.on('wheel', function(e) {
			if ($cropper.prop('hidden')) {
				return;
			}
			e.preventDefault();
			var delta = e.originalEvent.deltaY > 0 ? -0.08 : 0.08;
			setZoom(zoom + delta);
		});

		$viewport.on('pointerdown', function(e) {
			if ($cropper.prop('hidden') || !ready) {
				return;
			}
			dragging = true;
			dragStartX = e.clientX;
			dragStartY = e.clientY;
			dragOriginTx = tx;
			dragOriginTy = ty;
			$viewport.addClass('is-dragging');
			if (this.setPointerCapture) {
				this.setPointerCapture(e.pointerId);
			}
		});

		$viewport.on('pointermove', function(e) {
			if (!dragging) {
				return;
			}
			tx = dragOriginTx + (e.clientX - dragStartX);
			ty = dragOriginTy + (e.clientY - dragStartY);
			applyTransform();
		});

		$viewport.on('pointerup pointercancel', function() {
			dragging = false;
			$viewport.removeClass('is-dragging');
		});

		$(window).on('resize.rememberPhotoCrop orientationchange.rememberPhotoCrop', function() {
			if (ready && !$cropper.prop('hidden')) {
				applyTransform();
			}
		});

		$form.on('submit', function(e) {
			if ($cropper.prop('hidden') || !ready || !$file[0].files || !$file[0].files.length) {
				return;
			}

			// Avoid double-handling after we swap in the cropped file.
			if ($form.data('rememberPhotoCropped')) {
				$form.removeData('rememberPhotoCropped');
				return;
			}

			e.preventDefault();
			$form.addClass('remember-photo-cropping');

			cropToFile(function(file) {
				$form.removeClass('remember-photo-cropping');
				if (file && typeof DataTransfer !== 'undefined') {
					var dt = new DataTransfer();
					dt.items.add(file);
					$file[0].files = dt.files;
				}
				$form.data('rememberPhotoCropped', true);
				// Native submit after replacing the file.
				if (typeof $form[0].requestSubmit === 'function') {
					$form[0].requestSubmit();
				} else {
					$form[0].submit();
				}
			});
		});
	}

	function initProfilePhotoCropper() {
		$('.remember-profile-photo-edit').each(function() {
			initOnePhotoCropper($(this));
		});
	}

	/**
	 * Require at least one checkbox in dietary / medical / allergy groups.
	 * Selecting "None" clears other options in the same group (and vice versa).
	 */
	function initRequireOneCheckboxGroups() {
		$('[data-remember-require-one]').each(function() {
			var $group = $(this);
			var $boxes = $group.find('input[type="checkbox"]');
			if (!$boxes.length) {
				return;
			}

			function syncRequired() {
				var anyChecked = $boxes.filter(':checked').length > 0;
				$boxes.prop('required', false);
				if (!anyChecked) {
					$boxes.first().prop('required', true);
				}
			}

			$boxes.on('change', function() {
				var $changed = $(this);
				if ($changed.is('[data-remember-none]') && $changed.is(':checked')) {
					$boxes.not($changed).prop('checked', false);
				} else if (!$changed.is('[data-remember-none]') && $changed.is(':checked')) {
					$boxes.filter('[data-remember-none]').prop('checked', false);
				}
				syncRequired();
			});

			syncRequired();
		});
	}

	/**
	 * Conditional custom profile fields: show/hide + required when a gate field matches.
	 */
	function initConditionalProfileQuestions() {
		var $fields = $('.remember-pq-field[data-remember-pq-when]');
		if (!$fields.length) {
			return;
		}

		function getFieldValues($field) {
			var type = $field.data('remember-pq-type');
			if (type === 'multiselect') {
				return $field.find('input[type="checkbox"]:checked').map(function() {
					return $(this).val();
				}).get();
			}
			var $input = $field.find('select, input[type="text"]').first();
			var val = $input.length ? String($input.val() || '') : '';
			return val ? [val] : [];
		}

		function gateMatches(rule) {
			if (!rule || !rule.field_key || !rule.values || !rule.values.length) {
				return false;
			}
			var $gate = $('.remember-pq-field[data-remember-pq-key="' + rule.field_key + '"]');
			if (!$gate.length || $gate.prop('hidden')) {
				return false;
			}
			var picked = getFieldValues($gate);
			for (var i = 0; i < picked.length; i++) {
				if (rule.values.indexOf(picked[i]) !== -1) {
					return true;
				}
			}
			return false;
		}

		function syncMultiRequire($field, required) {
			var $group = $field.find('[role="group"]').first();
			var $boxes = $field.find('input[type="checkbox"]');
			if (!$boxes.length) {
				return;
			}
			if (required) {
				$group.attr('data-remember-pq-require-one', '1');
				var any = $boxes.filter(':checked').length > 0;
				$boxes.prop('required', false);
				if (!any) {
					$boxes.first().prop('required', true);
				}
			} else {
				$group.removeAttr('data-remember-pq-require-one');
				$boxes.prop('required', false);
			}
		}

		function syncField($field) {
			var raw = $field.attr('data-remember-pq-when');
			var rule = null;
			try {
				rule = raw ? JSON.parse(raw) : null;
			} catch (e) {
				rule = null;
			}
			var active = gateMatches(rule);
			$field.prop('hidden', !active);
			$field.find('.remember-pq-req-mark').prop('hidden', !active);

			var type = $field.data('remember-pq-type');
			if (type === 'multiselect') {
				syncMultiRequire($field, active);
			} else {
				$field.find('select, input[type="text"]').prop('required', !!active);
			}
		}

		function syncAll() {
			// Two passes so nested conditionals settle when a mid-chain gate hides.
			$fields.each(function() {
				syncField($(this));
			});
			$fields.each(function() {
				syncField($(this));
			});
		}

		$(document).on('change', '.remember-pq-field select, .remember-pq-field input[type="checkbox"], .remember-pq-field input[type="text"]', function() {
			syncAll();
		});

		syncAll();
	}

	function normalizeProfileConfirmPhrase(value) {
		return String(value || '')
			.toLowerCase()
			.replace(/\s+/g, ' ')
			.trim()
			.replace(/[.,!?;:'"\s]+$/g, '')
			.trim();
	}

	function initProfileCurrencyConfirm() {
		var ajaxUrl = (typeof rememberPublic !== 'undefined' && rememberPublic.ajaxurl) ? rememberPublic.ajaxurl : '';
		var nonce = (typeof rememberPublic !== 'undefined' && rememberPublic.profileCurrencyNonce) ? rememberPublic.profileCurrencyNonce : '';
		var staleMsg = (typeof rememberPublic !== 'undefined' && rememberPublic.i18n && rememberPublic.i18n.profileStale)
			? rememberPublic.i18n.profileStale
			: 'Save your profile first (within the last 24 hours), then type the confirmation phrase.';

		$('.remember-profile-currency-confirm').each(function() {
			var $wrap = $(this);
			var $input = $wrap.find('.remember-profile-currency-confirm__input');
			var $status = $wrap.find('.remember-profile-currency-confirm__status');
			var $form = $wrap.closest('form');
			if (!$input.length || !$form.length) {
				return;
			}
			var $submit = $form.find('button[type="submit"].remember-button-primary, button[type="submit"]').first();
			var expected = normalizeProfileConfirmPhrase($input.data('remember-confirm-phrase') || 'my profile is current');
			var fresh = $wrap.attr('data-remember-profile-fresh') === '1';
			var checking = false;

			function setStatus(message) {
				if (!message) {
					$status.prop('hidden', true).text('');
					return;
				}
				$status.prop('hidden', false).text(message);
			}

			function sync() {
				var phraseOk = normalizeProfileConfirmPhrase($input.val()) === expected;
				var unlocked = phraseOk && fresh;
				$submit.prop('disabled', !unlocked);
				$submit.toggleClass('remember-button-disabled', !unlocked);
				if (phraseOk && !fresh) {
					setStatus(staleMsg);
				} else {
					setStatus('');
				}
			}

			function refreshFreshness() {
				if (!ajaxUrl || !nonce || checking) {
					sync();
					return;
				}
				checking = true;
				$.post(ajaxUrl, {
					action: 'remember_profile_currency_status',
					nonce: nonce
				}).done(function(resp) {
					if (resp && resp.success && resp.data) {
						fresh = !!resp.data.fresh;
						$wrap.attr('data-remember-profile-fresh', fresh ? '1' : '0');
						if (resp.data.updated_at) {
							$wrap.attr('data-remember-profile-updated-at', resp.data.updated_at);
						}
					}
				}).always(function() {
					checking = false;
					sync();
				});
			}

			$input.on('input change keyup', sync);
			$(window).on('focus', refreshFreshness);
			$(document).on('visibilitychange', function() {
				if (!document.hidden) {
					refreshFreshness();
				}
			});
			$wrap.find('a[target="_blank"]').on('click', function() {
				// After returning from profile edit, re-check freshness.
				window.setTimeout(refreshFreshness, 500);
			});

			refreshFreshness();
		});
	}

	function applyGateI18n(key, fallback) {
		if (typeof rememberPublic !== 'undefined' && rememberPublic.i18n && rememberPublic.i18n[key]) {
			return rememberPublic.i18n[key];
		}
		return fallback;
	}

	function closeApplyGate($dialog) {
		if (!$dialog || !$dialog.length) {
			return;
		}
		$dialog.removeAttr('open');
		$dialog.find('[data-remember-apply-continue]').off('click.rememberApplyGate');
		$dialog.find('[data-remember-apply-cancel]').off('click.rememberApplyGate');
	}

	function initApplyGate() {
		$(document).on('click', 'a.remember-apply-gate', function(e) {
			var $link = $(this);
			var profileUrl = $link.attr('data-profile-url') || '';
			if (!profileUrl) {
				return;
			}
			e.preventDefault();

			var $dialog = $('#remember-apply-gate-dialog');
			if (!$dialog.length) {
				$dialog = $('<dialog>', {
					id: 'remember-apply-gate-dialog',
					class: 'remember-apply-gate-dialog',
					'aria-labelledby': 'remember-apply-gate-title'
				});
				$dialog.append(
					$('<h2>', { id: 'remember-apply-gate-title', class: 'remember-apply-gate-title' }).text(applyGateI18n('applyGateTitle', 'Confirm your profile first')),
					$('<p>', { class: 'remember-apply-gate-body' }).text(applyGateI18n('applyGateBody', '')),
					$('<div>', { class: 'remember-apply-gate-actions' }).append(
						$('<button>', {
							type: 'button',
							class: 'remember-button remember-button-primary',
							'data-remember-apply-continue': '1'
						}).text(applyGateI18n('applyGateContinue', 'Review profile')),
						$('<button>', {
							type: 'button',
							class: 'remember-button remember-button-secondary',
							'data-remember-apply-cancel': '1'
						}).text(applyGateI18n('applyGateCancel', 'Cancel'))
					)
				);
				$('body').append($dialog);
			}

			$dialog.find('[data-remember-apply-continue]').off('click.rememberApplyGate').on('click.rememberApplyGate', function() {
				window.location.href = profileUrl;
			});
			$dialog.find('[data-remember-apply-cancel]').off('click.rememberApplyGate').on('click.rememberApplyGate', function() {
				if (typeof $dialog[0].close === 'function') {
					$dialog[0].close();
				} else {
					closeApplyGate($dialog);
				}
			});

			if (typeof $dialog[0].showModal === 'function') {
				$dialog[0].showModal();
			} else {
				$dialog.attr('open', 'open');
			}
			$dialog.find('[data-remember-apply-continue]').trigger('focus');
		});

		$(document).on('cancel', '#remember-apply-gate-dialog', function() {
			closeApplyGate($(this));
		});
	}

	$(function() {
		initDisplayNameNicknameSync();
		initProfilePhotoCropper();
		initRequireOneCheckboxGroups();
		initConditionalProfileQuestions();
		initProfileCurrencyConfirm();
		initApplyGate();
		initInterestsLimitFallback();
		hookTinymceAddEditor();
		bindKnownInterestsEditors();
		var tries = 0;
		var timer = window.setInterval(function() {
			tries += 1;
			hookTinymceAddEditor();
			bindKnownInterestsEditors();
			if (tries >= 40) {
				window.clearInterval(timer);
			}
		}, 250);
		if (typeof window.rememberInitTimezoneComboboxes === 'function') {
			window.rememberInitTimezoneComboboxes();
		}
	});

	/**
	 * Count Unicode code points (matches PHP mb_strlen for typical Interests text).
	 *
	 * @param {string} text
	 * @return {number}
	 */
	function rememberInterestsCodepoints(text) {
		if (!text) {
			return 0;
		}
		if (typeof Array.from === 'function') {
			return Array.from(text).length;
		}
		return text.length;
	}

	/**
	 * Format "current / max characters" without depending on wp.i18n.
	 *
	 * @param {number} count
	 * @param {number} max
	 * @return {string}
	 */
	function rememberInterestsCountLabel(count, max, template) {
		var label = template || '%1$s / %2$s characters';
		return label.replace('%1$s', String(count)).replace('%2$s', String(max));
	}

	/**
	 * Interests keep only b, i, em, u, ul, ol, li, p, and br, with no attributes.
	 * Paste is reduced to that set, then posted as HTML in the same block
	 * layout TinyMCE uses when the text was typed in the editor.
	 *
	 * @param {string} mode html.
	 * @param {string} tag  b, i, em, u, ul, ol, li, p, or br.
	 * @return {string}
	 */
	function rememberOpenTag(mode, tag) {
		return tag === 'br' ? '<br />' : '<' + tag + '>';
	}

	/**
	 * @param {string} mode html or markers.
	 * @param {string} tag  Tag name.
	 * @return {string}
	 */
	function rememberCloseTag(mode, tag) {
		if (tag === 'br') {
			return '';
		}
		return '</' + tag + '>';
	}

	/**
	 * @param {string} mode  html or markers.
	 * @param {string} tag   Tag name.
	 * @param {string} inner Inner markup.
	 * @return {string}
	 */
	function rememberWrapTag(mode, tag, inner) {
		if (tag === 'br') {
			return rememberOpenTag(mode, 'br');
		}
		if (!String(inner || '').replace(/\s+/g, '')) {
			return '';
		}
		return rememberOpenTag(mode, tag) + inner + rememberCloseTag(mode, tag);
	}

	/**
	 * @param {string} text Visible text.
	 * @param {string} mode html or markers.
	 * @return {string}
	 */
	function rememberTextToken(text, mode) {
		text = String(text || '');
		text = text.replace(/\u00a0/g, ' ');
		text = text.replace(/[\u200B-\u200D\u2060\uFEFF\u200E\u200F]/g, '');
		text = text.replace(/\u0000/g, '');
		return text
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	/**
	 * @param {string} text Plain text with newlines.
	 * @param {string} mode html or markers.
	 * @return {string}
	 */
	function rememberPlainBlocks(text, mode) {
		text = String(text || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
		text = text.replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n');
		text = text.replace(/^\s+|\s+$/g, '');
		if (!text) {
			return '';
		}
		var parts = text.split(/\n{2,}/);
		var out = [];
		var i;
		for (i = 0; i < parts.length; i++) {
			var part = parts[i].replace(/^\s+|\s+$/g, '');
			if (!part) {
				continue;
			}
			var lines = part.split('\n');
			var inner = '';
			var j;
			for (j = 0; j < lines.length; j++) {
				if (j) {
					inner += rememberOpenTag(mode, 'br');
				}
				inner += rememberTextToken(lines[j], mode);
			}
			out.push(rememberWrapTag(mode, 'p', inner));
		}
		return out.join('');
	}

	/**
	 * Drop Office/Docs/Pages clipboard chrome before the HTML parser runs.
	 * Unclosed style/xml blocks must not swallow the paragraph text.
	 *
	 * @param {string} html
	 * @return {string}
	 */
	function rememberStripClipboardWrappers(html) {
		html = String(html || '');
		html = html.replace(/\u0000/g, '');
		html = html.replace(/<!--[\s\S]*?-->/g, '');
		html = html.replace(/<!\[if[\s\S]*?<!\[endif\]>/gi, '');
		html = html.replace(/<!\[if[^\]]*\]>/gi, '');
		html = html.replace(/<!\[endif\]>/gi, '');
		html = html.replace(/<\?[\s\S]*?\?>/g, '');
		html = html.replace(/<!DOCTYPE[^>]*>/gi, '');
		html = html.replace(/<\/?o:p\b[^>]*>/gi, '');
		html = html.replace(/<(script|style|xml|noscript)\b[^>]*>[\s\S]*?<\/\1>/gi, '');
		html = html.replace(/<(script|style|xml|noscript)\b[^>]*>[\s\S]*?(?=<\/head\b|<body\b|<p\b|<div\b|$)/gi, '');
		html = html.replace(/<\/?(meta|link|title|base|head)\b[^>]*>/gi, '');
		return html;
	}

	/**
	 * @param {string} mode html or markers.
	 * @param {string} markup Fragment.
	 * @return {boolean}
	 */
	function rememberHasBlock(mode, markup) {
		return /<(p|ul|ol)\b/i.test(markup);
	}

	/**
	 * Word and some web editors fake lists with paragraphs instead of ul/ol.
	 *
	 * @param {Element} node Element.
	 * @return {boolean}
	 */
	function rememberIsWordList(node) {
		if (!node || node.nodeType !== 1) {
			return false;
		}
		var style = node.getAttribute('style') || '';
		var cls = node.getAttribute('class') || '';
		return /mso-list\s*:/i.test(style) || /MsoListParagraph/i.test(cls);
	}

	/**
	 * @param {Element} node Element.
	 * @return {string[]} b, em, and/or u from inline CSS.
	 */
	function rememberStyleTags(node) {
		var style = ((node.getAttribute && node.getAttribute('style')) || '').toLowerCase();
		var tags = [];
		if (/font-weight\s*:\s*(bold|[6-9]00)\b/.test(style)) {
			tags.push('b');
		}
		if (/font-style\s*:\s*italic\b/.test(style)) {
			tags.push('em');
		}
		if (/text-decoration\s*:[^;]*underline/.test(style)) {
			tags.push('u');
		}
		return tags;
	}

	/**
	 * @param {string} href Link target.
	 * @return {string}
	 */
	function rememberSafeHref(href) {
		href = String(href || '');
		var googleQ = href.match(/[?&]q=([^&]+)/i);
		if (googleQ && /google\.[^/]+\/url/i.test(href)) {
			try {
				href = decodeURIComponent(googleQ[1]);
			} catch (err) {
				href = googleQ[1];
			}
		}
		if (/^(https?:|mailto:)/i.test(href)) {
			return href;
		}
		return '';
	}

	/**
	 * @param {Node} node Node.
	 * @param {string} mode html or markers.
	 * @return {string}
	 */
	function rememberSerializeInterests(node, mode) {
		if (!node) {
			return '';
		}
		if (node.nodeType === 3) {
			return rememberTextToken(node.nodeValue || '', mode);
		}
		if (node.nodeType !== 1) {
			return '';
		}
		var tag = node.tagName;
		if (tag === 'IMG') {
			return rememberTextToken(node.getAttribute('alt') || '', mode);
		}
		if (
			tag === 'SCRIPT' ||
			tag === 'STYLE' ||
			tag === 'IFRAME' ||
			tag === 'OBJECT' ||
			tag === 'EMBED' ||
			tag === 'SVG' ||
			tag === 'MATH' ||
			tag === 'NOSCRIPT' ||
			tag === 'FORM' ||
			tag === 'TEXTAREA' ||
			tag === 'INPUT' ||
			tag === 'BUTTON' ||
			tag === 'SELECT' ||
			tag === 'HEAD' ||
			tag === 'META' ||
			tag === 'LINK' ||
			tag === 'TITLE' ||
			tag === 'VIDEO' ||
			tag === 'AUDIO' ||
			tag === 'SOURCE' ||
			tag === 'CANVAS' ||
			tag === 'TEMPLATE' ||
			tag === 'FRAME' ||
			tag === 'FRAMESET' ||
			tag === 'APPLET' ||
			tag === 'BASE' ||
			tag.indexOf(':') !== -1
		) {
			return '';
		}
		if (tag === 'BR') {
			var brClass = node.getAttribute('class') || '';
			if (brClass.indexOf('Apple-interchange-newline') !== -1) {
				return '';
			}
			return rememberOpenTag(mode, 'br');
		}
		if (tag === 'UL' || tag === 'OL') {
			return rememberSerializeList(node, mode, tag.toLowerCase());
		}
		var inner = rememberSerializeChildren(node, mode);
		if (tag === 'A') {
			var href = rememberSafeHref(node.getAttribute('href') || '');
			if (href && inner.indexOf(href) === -1) {
				inner = inner.replace(/\s+$/, '') + rememberTextToken(' (' + href + ')', mode);
			}
		}
		if (tag === 'TD' || tag === 'TH') {
			return inner.replace(/\s+$/, '') + rememberTextToken(' ', mode);
		}
		var inlineMap = { STRONG: 'b', B: 'b', EM: 'em', I: 'em', U: 'u' };
		if (inlineMap[tag]) {
			var inlineStyle = (node.getAttribute('style') || '').toLowerCase();
			var neutralBold = (tag === 'B' || tag === 'STRONG') && /font-weight\s*:\s*(normal|400)\b/.test(inlineStyle);
			var neutralItalic = (tag === 'I' || tag === 'EM') && /font-style\s*:\s*normal\b/.test(inlineStyle);
			if ((neutralBold || neutralItalic) && rememberHasBlock(mode, inner)) {
				return inner;
			}
			if (neutralBold || neutralItalic) {
				var skipped = {};
				skipped[inlineMap[tag]] = true;
				return rememberApplyStyleTags(node, mode, inner, skipped);
			}
			var alreadyInline = {};
			alreadyInline[inlineMap[tag]] = true;
			return rememberApplyStyleTags(node, mode, rememberWrapTag(mode, inlineMap[tag], inner), alreadyInline);
		}
		if (tag === 'LI') {
			return rememberWrapTag(mode, 'li', inner);
		}
		if (
			tag === 'P' ||
			tag === 'DIV' ||
			tag === 'H1' ||
			tag === 'H2' ||
			tag === 'H3' ||
			tag === 'H4' ||
			tag === 'H5' ||
			tag === 'H6' ||
			tag === 'BLOCKQUOTE' ||
			tag === 'PRE' ||
			tag === 'SECTION' ||
			tag === 'ARTICLE' ||
			tag === 'HEADER' ||
			tag === 'FOOTER' ||
			tag === 'TR' ||
			tag === 'SPAN' ||
			tag === 'FONT'
		) {
			inner = rememberApplyStyleTags(node, mode, inner, {});
			if (tag === 'SPAN' || tag === 'FONT') {
				return inner;
			}
			if (rememberHasBlock(mode, inner)) {
				return inner;
			}
			return rememberWrapTag(mode, 'p', inner);
		}
		return rememberApplyStyleTags(node, mode, inner, {});
	}

	/**
	 * @param {Element} node Element.
	 * @param {string} mode html or markers.
	 * @param {string} inner Already serialized children.
	 * @param {Object<string, boolean>} already Tags already opened by the element itself.
	 * @return {string}
	 */
	function rememberApplyStyleTags(node, mode, inner, already) {
		var tags = rememberStyleTags(node);
		var i;
		for (i = tags.length - 1; i >= 0; i--) {
			if (already && already[tags[i]]) {
				continue;
			}
			inner = rememberWrapTag(mode, tags[i], inner);
		}
		return inner;
	}

	/**
	 * @param {Element} node List element.
	 * @param {string} mode html or markers.
	 * @param {string} listTag ul or ol.
	 * @return {string}
	 */
	function rememberSerializeList(node, mode, listTag) {
		var items = [];
		var child = node.firstChild;
		while (child) {
			if (child.nodeType === 1 && child.tagName === 'LI') {
				var liInner = rememberSerializeChildren(child, mode);
				if (String(liInner).replace(/\s+/g, '')) {
					items.push(rememberWrapTag(mode, 'li', liInner));
				}
			} else {
				var extra = rememberSerializeInterests(child, mode);
				if (String(extra).replace(/\s+/g, '')) {
					items.push(rememberWrapTag(mode, 'li', extra));
				}
			}
			child = child.nextSibling;
		}
		if (!items.length) {
			return '';
		}
		return rememberOpenTag(mode, listTag) + items.join('') + rememberCloseTag(mode, listTag);
	}

	/**
	 * @param {Node} node Parent.
	 * @param {string} mode html or markers.
	 * @return {string}
	 */
	function rememberSerializeChildren(node, mode) {
		var parts = [];
		var list = [];
		function flushList() {
			if (!list.length) {
				return;
			}
			parts.push(rememberOpenTag(mode, 'ul') + list.join('') + rememberCloseTag(mode, 'ul'));
			list = [];
		}
		var child = node.firstChild;
		while (child) {
			if (rememberIsWordList(child)) {
				var item = rememberSerializeChildren(child, mode);
				if (String(item).replace(/\s+/g, '')) {
					list.push(rememberWrapTag(mode, 'li', item));
				}
				child = child.nextSibling;
				continue;
			}
			flushList();
			parts.push(rememberSerializeInterests(child, mode));
			child = child.nextSibling;
		}
		flushList();
		return parts.join('');
	}

	/**
	 * @param {string} markup Fragment.
	 * @param {string} mode html or markers.
	 * @return {string}
	 */
	function rememberFinalizeInterests(markup, mode) {
		var allowed = { p: 1, br: 1, b: 1, em: 1, u: 1, ul: 1, ol: 1, li: 1 };
		var prev;
		markup = String(markup || '').replace(/<\/?([a-z0-9]+)(?:\s[^>]*)?\s*\/?>/gi, function(match, name) {
			name = name.toLowerCase();
			if (!allowed[name]) {
				return '';
			}
			if (name === 'br') {
				return '<br />';
			}
			if (match.charAt(1) === '/') {
				return '</' + name + '>';
			}
			return '<' + name + '>';
		});
		do {
			prev = markup;
			markup = markup.replace(/<(b|em|u|p|li|ul|ol)>\s*<\/\1>/gi, '');
		} while (markup !== prev);
		markup = markup.replace(/<\/(p|ul|ol|li)>/gi, '</$1>\n');
		markup = markup.replace(/<(ul|ol|li)>/gi, '\n<$1>');
		markup = markup.replace(/\n{2,}/g, '\n');
		return markup.replace(/^\s+|\s+$/g, '');
	}

	/**
	 * @param {string} html Clipboard or editor HTML.
	 * @param {string} mode html or markers.
	 * @return {string}
	 */
	function rememberInterestsMarkup(html, mode) {
		html = rememberStripClipboardWrappers(html);
		mode = 'html';
		if (!/<\/?\s*[a-z]/i.test(html)) {
			return rememberPlainBlocks(html, mode);
		}
		var wrap = document.createElement('div');
		wrap.innerHTML = html;
		return rememberFinalizeInterests(rememberSerializeChildren(wrap, mode), mode);
	}

	/**
	 * Drop every tag except b, i, em, u, br, p, ul, ol, and li, and drop every attribute.
	 * Other elements are unwrapped so the text stays and the markup does not.
	 *
	 * @param {string} html Clipboard or editor HTML.
	 * @return {string}
	 */
	function rememberCleanInterestsHtml(html) {
		var allowed = { P: 1, BR: 1, B: 1, I: 1, EM: 1, U: 1, UL: 1, OL: 1, LI: 1 };
		var wrap = document.createElement('div');
		wrap.innerHTML = rememberStripClipboardWrappers(html || '');

		var blocks = { DIV: 1, H1: 1, H2: 1, H3: 1, H4: 1, H5: 1, H6: 1, BLOCKQUOTE: 1, PRE: 1, SECTION: 1, ARTICLE: 1 };

		function stripAttrs(el) {
			while (el.attributes && el.attributes.length) {
				el.removeAttribute(el.attributes[0].name);
			}
		}

		function walk(node) {
			var children = Array.prototype.slice.call(node.childNodes);
			var index;
			for (index = 0; index < children.length; index++) {
				var child = children[index];
				if (child.nodeType !== 1) {
					if (child.nodeType !== 3) {
						node.removeChild(child);
					}
					continue;
				}
				walk(child);
				if (child.tagName === 'STRONG') {
					var bold = document.createElement('b');
					while (child.firstChild) {
						bold.appendChild(child.firstChild);
					}
					node.replaceChild(bold, child);
					stripAttrs(bold);
				} else if (blocks[child.tagName] && !child.querySelector('p,ul,ol')) {
					var para = document.createElement('p');
					while (child.firstChild) {
						para.appendChild(child.firstChild);
					}
					node.replaceChild(para, child);
					stripAttrs(para);
				} else if (!allowed[child.tagName]) {
					while (child.firstChild) {
						node.insertBefore(child.firstChild, child);
					}
					node.removeChild(child);
				} else {
					stripAttrs(child);
				}
			}
		}

		walk(wrap);
		return wrap.innerHTML;
	}

	/**
	 * Allowlist HTML for the POST, in TinyMCE's block layout.
	 *
	 * @param {string} html Clipboard or editor HTML.
	 * @return {string}
	 */
	function rememberInterestsForPost(html) {
		return rememberCleanInterestsHtml(html);
	}

	/**
	 * Live character limit for Interests TinyMCE editors.
	 *
	 * @param {object} editor TinyMCE editor instance.
	 * @return {void}
	 */
	function initInterestsLimitEditor(editor) {
		if (!editor || !editor.id) {
			return;
		}
		if (editor.id !== 'interests' && editor.id !== 'remember_reg_interests') {
			return;
		}
		if (editor.rememberInterestsLimited) {
			return;
		}
		editor.rememberInterestsLimited = true;

		function useUnderlineTag() {
			if (editor.formatter) {
				editor.formatter.register('underline', { inline: 'u', exact: true });
			}
		}
		editor.on('init', useUnderlineTag);
		if (editor.initialized) {
			useUnderlineTag();
		}

		var $counter = $('.remember-interests-count[data-remember-interests-editor="' + editor.id + '"]');
		var max = parseInt($counter.attr('data-remember-interests-max'), 10) || 2000;
		var template = $counter.attr('data-remember-interests-template') || '';

		function plainLen() {
			return rememberInterestsCodepoints(editor.getContent({ format: 'text' }) || '');
		}

		function updateCounter() {
			var len = plainLen();
			$counter.text(rememberInterestsCountLabel(len, max, template));
			$counter.toggleClass('is-over', len > max);
		}

		function isEditNav(e) {
			if (!e) {
				return false;
			}
			if (e.ctrlKey || e.metaKey || e.altKey) {
				return true;
			}
			var key = e.key || '';
			return (
				key === 'Backspace' ||
				key === 'Delete' ||
				key === 'ArrowLeft' ||
				key === 'ArrowRight' ||
				key === 'ArrowUp' ||
				key === 'ArrowDown' ||
				key === 'Home' ||
				key === 'End' ||
				key === 'Tab' ||
				key === 'Escape'
			);
		}

		editor.on('keydown', function(e) {
			if (isEditNav(e)) {
				return;
			}
			if (plainLen() >= max) {
				e.preventDefault();
			}
		});

		editor.on('keyup', updateCounter);
		editor.on('change', updateCounter);
		editor.on('undo', updateCounter);
		editor.on('redo', updateCounter);
		editor.on('input', updateCounter);
		editor.on('NodeChange', updateCounter);
		editor.on('SetContent', updateCounter);

		var postingPlain = false;
		editor.on('GetContent', function(e) {
			if (!postingPlain || !e) {
				return;
			}
			if (e.format && e.format !== 'html' && e.format !== 'raw') {
				return;
			}
			if (e.rememberInterestsPlain) {
				return;
			}
			e.rememberInterestsPlain = true;
			e.content = rememberInterestsForPost(e.content || '');
		});

		// TinyMCE writes editor.getElement().value from this event inside save().
		// WordPress calls save() on submit before the browser encodes the POST.
		editor.on('SaveContent', function(e) {
			if (!e) {
				return;
			}
			e.content = rememberInterestsForPost(e.content || '');
			var field = editor.getElement();
			if (field) {
				field.value = e.content;
			}
		});

		if (typeof editor.save === 'function' && !editor.rememberInterestsSavePatched) {
			editor.rememberInterestsSavePatched = true;
			var originalSave = editor.save.bind(editor);
			editor.save = function() {
				postingPlain = true;
				try {
					return originalSave();
				} finally {
					postingPlain = false;
				}
			};
		}

		function flattenInterestsEditor() {
			var html = editor.getContent({ format: 'html' }) || '';
			var cleaned = rememberCleanInterestsHtml(html);
			if (cleaned !== html) {
				editor.setContent(cleaned);
			}
			editor.save();
		}

		// Leave the field already stripped, before Save is pressed.
		editor.on('blur', flattenInterestsEditor);

		editor.on('paste', function() {
			window.setTimeout(function() {
				if (plainLen() > max) {
					if (editor.undoManager) {
						editor.undoManager.undo();
					}
					updateCounter();
					return;
				}
				flattenInterestsEditor();
				updateCounter();
			}, 0);
		});

		editor.on('PastePreProcess', function(e) {
			if (e && e.content) {
				e.content = rememberCleanInterestsHtml(e.content);
			}
		});

		var formEl = editor.getElement() && editor.getElement().form;
		if (formEl && !formEl.rememberInterestsPlainSubmit) {
			formEl.rememberInterestsPlainSubmit = true;
			formEl.addEventListener('submit', function() {
				editor.save();
			}, true);
		}

		editor.on('init', updateCounter);
		updateCounter();
	}

	window.rememberInitInterestsLimitEditor = initInterestsLimitEditor;

	function hookTinymceAddEditor() {
		if (!window.tinymce || typeof tinymce.on !== 'function') {
			return false;
		}
		if (tinymce.rememberInterestsHooked) {
			return true;
		}
		tinymce.rememberInterestsHooked = true;
		tinymce.on('AddEditor', function(e) {
			if (e && e.editor) {
				initInterestsLimitEditor(e.editor);
			}
		});
		return true;
	}

	function bindKnownInterestsEditors() {
		if (!window.tinymce || typeof tinymce.get !== 'function') {
			return;
		}
		['interests', 'remember_reg_interests'].forEach(function(id) {
			var existing = tinymce.get(id);
			if (existing) {
				initInterestsLimitEditor(existing);
			}
		});
	}

	hookTinymceAddEditor();
	$(document).on('tinymce-editor-init', function(event, editor) {
		initInterestsLimitEditor(editor);
	});

	/**
	 * Textarea fallback when TinyMCE is not active.
	 *
	 * @return {void}
	 */
	function initInterestsLimitFallback() {
		$('textarea#interests, textarea#remember_reg_interests').each(function() {
			var $area = $(this);
			if ($area.closest('.wp-editor-wrap').find('.mce-tinymce').length) {
				return;
			}
			var id = $area.attr('id');
			var $counter = $('.remember-interests-count[data-remember-interests-editor="' + id + '"]');
			if (!$counter.length) {
				return;
			}
			var max = parseInt($counter.attr('data-remember-interests-max'), 10) || 2000;
			var template = $counter.attr('data-remember-interests-template') || '';

			function sync(fromUser) {
				var value = $area.val() || '';
				var len = rememberInterestsCodepoints(value);
				if (fromUser && len > max) {
					if (typeof Array.from === 'function') {
						value = Array.from(value).slice(0, max).join('');
					} else {
						value = value.substring(0, max);
					}
					$area.val(value);
					len = max;
				}
				$counter.text(rememberInterestsCountLabel(len, max, template));
				$counter.toggleClass('is-over', len > max);
			}

			$area.on('input change', function() {
				sync(true);
			});
			$area.closest('form').on('submit', function() {
				if (window.tinymce && typeof tinymce.get === 'function' && tinymce.get(id)) {
					return;
				}
				$area.val(rememberInterestsForPost($area.val() || ''));
			});
			sync(false);
		});
	}

})(jQuery);
