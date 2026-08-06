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
	 */
	function initProfilePhotoCropper() {
		var $wrap = $('.remember-profile-photo-edit');
		if (!$wrap.length) {
			return;
		}

		var $form = $wrap.closest('form');
		var $file = $wrap.find('#photo_file');
		var $current = $wrap.find('.remember-profile-photo-current');
		var $cropper = $wrap.find('.remember-profile-photo-cropper');
		var $viewport = $wrap.find('.remember-profile-photo-cropper-viewport');
		var $img = $wrap.find('.remember-profile-photo-cropper-image');
		var $zoomRange = $wrap.find('.remember-photo-zoom-range');
		var $zoomIn = $wrap.find('.remember-photo-zoom-in');
		var $zoomOut = $wrap.find('.remember-photo-zoom-out');
		var $clear = $wrap.find('.remember-photo-clear');
		var outputSize = parseInt($wrap.data('output-size'), 10) || 800;

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
			$img.css({
				width: width + 'px',
				height: height + 'px',
				transform: 'translate(' + left + 'px, ' + top + 'px)'
			});
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
			$cropper.prop('hidden', true);
			if ($current.children().length) {
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

			$img.off('load.rememberPhoto').one('load.rememberPhoto', function() {
				naturalW = this.naturalWidth;
				naturalH = this.naturalHeight;
				ready = true;
				$current.prop('hidden', true);
				$cropper.prop('hidden', false);
				applyTransform();
			});
			$img.attr('src', objectUrl);
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

	$(function() {
		initDisplayNameNicknameSync();
		initProfilePhotoCropper();
	});

})(jQuery);
