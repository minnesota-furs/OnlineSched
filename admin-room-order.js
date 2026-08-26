// The text field stays the stored value and this only writes into it, so the
// setting keeps one authority and still works with scripting off.
(function ($) {
	'use strict';

	$(function () {
		var field = $('#onlinesched_room_sort_priority');
		var ui = $('#onlinesched-room-order');
		if (!field.length || !ui.length) {
			return;
		}

		var ordered = ui.find('.onlinesched-room-ordered');
		var pool = ui.find('.onlinesched-room-pool');

		function sync() {
			var slugs = ordered
				.find('li')
				.map(function () {
					return $(this).data('slug');
				})
				.get();
			field.val(slugs.join(', '));
			ordered
				.siblings('.onlinesched-room-empty')
				.toggle(slugs.length === 0);
		}

		ordered.add(pool).sortable({
			connectWith: '.onlinesched-room-list',
			placeholder: 'onlinesched-room-placeholder',
			forcePlaceholderSize: true,
			update: sync,
		});

		// A click moves a room too, because dragging is hard with a trackpad and
		// impossible with a keyboard alone.
		ui.on('click', '.onlinesched-room-move', function (event) {
			event.preventDefault();
			var item = $(this).closest('li');
			item.appendTo(item.closest('.onlinesched-room-ordered').length ? pool : ordered);
			sync();
		});

		field.closest('td').find('.onlinesched-room-typed').hide();
		ui.show();
		sync();
	});
})(jQuery);
