// Badge Types Admin JS
function closeMessageBox(btn) {
	btn.parentElement.style.display = 'none';
}
function showEditForm(badge) {
	document.querySelectorAll('.badge-edit-form').forEach(function(form) {
		form.classList.remove('active');
	});
	document.querySelectorAll('.badge-types-table tr').forEach(function(row) {
		row.classList.remove('editing');
	});
	var form = document.getElementById('badge-edit-form-' + badge);
	if (form) {
		form.classList.add('active');
		var row = document.getElementById('badge-row-' + badge);
		if (row) row.classList.add('editing');
	}
}
function confirmDelete(badge) {
	if (confirm('Are you sure you want to delete the badge type "' + badge + '"? This cannot be undone.')) {
		document.getElementById('badge-delete-form-' + badge).submit();
	}
}
document.addEventListener('DOMContentLoaded', function() {
	var addBtn = document.getElementById('show-add-badge-type-btn');
	var addFormCard = document.getElementById('badge-add-form-card');
	var cancelBtn = document.getElementById('cancel-add-badge-type-btn');
	if (addBtn && addFormCard) {
		addBtn.addEventListener('click', function() {
			addFormCard.style.display = 'block';
			addBtn.style.display = 'none';
			var nameField = document.getElementById('badge_type_name');
			if (nameField) nameField.focus();
		});
	}
	if (cancelBtn && addFormCard && addBtn) {
		cancelBtn.addEventListener('click', function() {
			addFormCard.style.display = 'none';
			addBtn.style.display = '';
		});
	}
	jQuery(document).ready(function($){
		$('#assign-default-badge-types').on('click', function(e){
			e.preventDefault();
			var btn = $(this);
			btn.prop('disabled', true);
			$('#assign-badge-types-message').hide().empty();
			$.post(ajaxurl, { action: 'onlinesched_assign_default_badge_types' }, function(resp){
				btn.prop('disabled', false);
				var msgClass = resp.success ? 'schedule-updated' : 'upload-error';
				var msgText = resp.success ?
					'Assigned badge types to ' + resp.data.updated + ' tags.' :
					'Error: ' + resp.data;
				var msgHtml = '<div class="' + msgClass + '"><button class="close-message" onclick="closeMessageBox(this)">&times;</button><p>' + msgText + '</p></div>';
				$('#assign-badge-types-message').html(msgHtml).show();
			});
		});
	});
});