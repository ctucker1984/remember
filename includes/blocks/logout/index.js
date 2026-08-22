(function (blocks, element, i18n) {
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType('remember/logout', {
		edit: function () {
			return el(
				'span',
				{ className: 'wp-block-navigation-item__label' },
				__('Log out', 'remember')
			);
		},
		save: function () {
			return null;
		}
	});
})(window.wp.blocks, window.wp.element, window.wp.i18n);
