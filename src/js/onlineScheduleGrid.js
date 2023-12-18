
export function onlineScheduleGrid() {

	let selected = 0;

	jQuery(function () {

		// Hide/Show entries based on checked entries
		jQuery(".show-selected").click(function (e) {
			e.preventDefault();
			if (selected === 0) {
				jQuery('tr').has('input:checkbox:not(:checked)').each(function () {
					jQuery(this).hide()
				});
				jQuery('.show-selected').text("Show All");
				selected = 1;
			} else {
				jQuery('tr').has('input:checkbox:not(:checked)').show();
				jQuery('.show-selected').text("Show Selected");
				selected = 0;
			}
		});

		// Check or uncheck all checkboxes
		jQuery(".selected-all").click(function (e) {
			if (jQuery(this).is(':checked')) {
				jQuery('[type="checkbox"]').each(function () {
					jQuery(this).attr('checked', 'checked');
				});
			} else {
				jQuery('[type="checkbox"]').removeAttr('checked');
			}
		});

		// Send all selected to iCal generator code
		jQuery(".selected-ical").click(function (e) {
			e.preventDefault();

			var uuid = "";
			jQuery('input:checkbox:checked').each(function () {
				if (!uuid) {
					uuid += jQuery(this).val();
				} else {
					uuid += "," + jQuery(this).val();
				}
			});
			if (!uuid) {
				alert("No events seleceted for Calendar.");
			} else {
				window.location = 'http://proof.furrymigration.org/online-schedule/ical/?uuid=' + uuid;
			}
		});

		// Footable clear link for search field
		jQuery('.clear-track').click(function (e) {
			e.preventDefault();
			jQuery('table').trigger('footable_clear_filter');
			jQuery('.filter-track').val('');
		});

		// Firefox caches stuff, make it go away.
		jQuery('table').trigger('footable_clear_filter');
		jQuery('.filter-track').val('');
		jQuery('input:checkbox:checked').each(function () {
			jQuery(this).prop('checked', false);
		});
	});
}
