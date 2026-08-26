// The text field stays the stored value and this only writes into it, so the
// setting keeps one authority and still works with scripting off.
(function () {
	'use strict';

	function ready(fn) {
		if ('loading' === document.readyState) {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	ready(function () {
		var field = document.getElementById('onlinesched_room_sort_priority');
		var ui = document.getElementById('onlinesched-room-order');
		if (!field || !ui) {
			return;
		}

		var ordered = ui.querySelector('.onlinesched-room-ordered');
		var pool = ui.querySelector('.onlinesched-room-pool');
		var empty = ui.querySelector('.onlinesched-room-empty');
		var dragging = null;

		function sync() {
			var slugs = [];
			Array.prototype.forEach.call(ordered.children, function (li) {
				slugs.push(li.getAttribute('data-slug'));
			});
			field.value = slugs.join(', ');
			if (empty) {
				empty.style.display = slugs.length ? 'none' : '';
			}
			Array.prototype.forEach.call(ui.querySelectorAll('li'), function (li) {
				var pinned = li.parentNode === ordered;
				var toggle = li.querySelector('.onlinesched-room-toggle');
				toggle.textContent = pinned ? 'Remove' : 'Pin';
				toggle.setAttribute(
					'aria-label',
					(pinned ? 'Remove ' : 'Pin ') + toggle.getAttribute('data-room')
				);
				Array.prototype.forEach.call(
					li.querySelectorAll('.onlinesched-room-nudge'),
					function (button) {
						button.hidden = !pinned;
					}
				);
			});
		}

		ui.addEventListener('click', function (event) {
			var button = event.target.closest('button');
			if (!button) {
				return;
			}
			event.preventDefault();
			var li = button.closest('li');

			if (button.classList.contains('onlinesched-room-toggle')) {
				(li.parentNode === ordered ? pool : ordered).appendChild(li);
			} else if ('up' === button.getAttribute('data-move')) {
				if (li.previousElementSibling) {
					ordered.insertBefore(li, li.previousElementSibling);
				}
			} else if (li.nextElementSibling) {
				ordered.insertBefore(li.nextElementSibling, li);
			}
			sync();
		});

		ui.addEventListener('dragstart', function (event) {
			dragging = event.target.closest('li');
			event.dataTransfer.effectAllowed = 'move';
		});

		ui.addEventListener('dragover', function (event) {
			if (!dragging) {
				return;
			}
			event.preventDefault();
			var list = event.target.closest('.onlinesched-room-list');
			if (!list) {
				return;
			}
			var over = event.target.closest('li');
			if (!over || over === dragging) {
				if (!over) {
					list.appendChild(dragging);
				}
				return;
			}
			var box = over.getBoundingClientRect();
			var after = event.clientY > box.top + box.height / 2;
			list.insertBefore(dragging, after ? over.nextElementSibling : over);
		});

		ui.addEventListener('drop', function (event) {
			event.preventDefault();
			dragging = null;
			sync();
		});

		ui.addEventListener('dragend', function () {
			dragging = null;
			sync();
		});

		var typed = ui.parentNode.querySelector('.onlinesched-room-typed');
		if (typed) {
			typed.hidden = true;
		}
		ui.hidden = false;
		sync();
	});
})();
