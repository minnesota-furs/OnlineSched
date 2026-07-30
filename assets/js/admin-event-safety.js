(function () {
	'use strict';

	function initialize() {
		var config = window.OnlineSchedEventSafety;
		if (!config || !config.confirmMessage) {
			return;
		}

		function confirmRemoval(event) {
			if (!window.confirm(config.confirmMessage)) {
				event.preventDefault();
				event.stopImmediatePropagation();
				return false;
			}
			return true;
		}

		function isCancellationTag(element) {
			var button = element.matches('button[aria-label]')
				? element
				: element.querySelector('button[aria-label]');
			var text = button ? button.getAttribute('aria-label') : element.textContent;
			text = (text || '').replace(/^Remove term:\s*/i, '').trim();
			return /^(cancelled|canceled)$/i.test(text);
		}

		function hasSelectedPublishedEvents() {
			return Array.prototype.some.call(
				document.querySelectorAll('tbody th.check-column input[type="checkbox"]:checked'),
				function (checkbox) {
					var row = checkbox.closest('tr');
					return row && row.classList.contains('status-publish');
				}
			);
		}

		var cancellationCheckbox = document.getElementById('onlinesched-event-cancelled');
		var cancellationChanged = document.getElementById('onlinesched-event-cancellation-changed');
		var tagBox = document.getElementById('tagsdiv-os_tag');
		if (cancellationCheckbox && cancellationChanged) {
			cancellationCheckbox.addEventListener('change', function () {
				cancellationChanged.value = '1';
			});
		}
		if (cancellationCheckbox && tagBox) {
			tagBox.addEventListener('click', function (event) {
				var removeButton = event.target.closest('.ntdelbutton, .tagchecklist button');
				if (removeButton && isCancellationTag(removeButton.closest('span') || removeButton)) {
					cancellationCheckbox.checked = false;
				}
			});

			var tagList = tagBox.querySelector('.tagchecklist');
			if (tagList && window.MutationObserver) {
				new MutationObserver(function (mutations) {
					mutations.forEach(function (mutation) {
						Array.prototype.forEach.call(mutation.addedNodes, function (node) {
							if (node.nodeType === 1 && isCancellationTag(node)) {
								cancellationCheckbox.checked = true;
							}
						});
					});
				}).observe(tagList, { childList: true });
			}
		}

		document.querySelectorAll('a.submitdelete').forEach(function (link) {
			var shouldWarn = config.screen === 'post' && config.editorPublished;
			if (config.screen === 'list') {
				var row = link.closest('tr');
				shouldWarn = row && row.classList.contains('status-publish');
			}

			if (shouldWarn) {
				link.addEventListener('click', confirmRemoval);
			}
		});

		if (config.screen === 'post' && config.editorPublished) {
			var postForm = document.getElementById('post');
			var status = document.getElementById('post_status');
			if (postForm && status) {
				postForm.addEventListener('submit', function (event) {
					if (status.value && status.value !== 'publish') {
						confirmRemoval(event);
					}
				});
			}
		}

		if (config.screen === 'list') {
			[
				['doaction', 'bulk-action-selector-top'],
				['doaction2', 'bulk-action-selector-bottom']
			].forEach(function (controls) {
				var button = document.getElementById(controls[0]);
				var action = document.getElementById(controls[1]);
				if (!button || !action) {
					return;
				}

				button.addEventListener('click', function (event) {
					if (action.value !== 'trash') {
						return;
					}

					if (hasSelectedPublishedEvents()) {
						confirmRemoval(event);
					}
				});
			});

			var bulkEditButton = document.getElementById('bulk_edit');
			var bulkEditStatus = document.querySelector('#bulk-edit select[name="_status"]');
			if (bulkEditButton && bulkEditStatus) {
				bulkEditButton.addEventListener('click', function (event) {
					var status = bulkEditStatus.value;
					if (
						status
						&& status !== '-1'
						&& status !== 'publish'
						&& hasSelectedPublishedEvents()
					) {
						confirmRemoval(event);
					}
				});
			}
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initialize);
	} else {
		initialize();
	}
}());
