// Badge Types Admin JS
function closeMessageBox(btn) {
	btn.parentElement.style.display = 'none';
}
function showEditForm(badge_slug) {
	document.querySelectorAll('.badge-edit-row').forEach(function(row) {
		row.style.display = 'none';
	});
	document.querySelectorAll('.badge-types-table tr.main-row').forEach(function(row) {
		row.classList.remove('editing');
	});
	var editRow = document.getElementById('badge-edit-row-' + badge_slug);
	if (editRow) {
		editRow.style.display = 'table-row';
		var mainRow = document.getElementById('badge-row-' + badge_slug);
		if (mainRow) mainRow.classList.add('editing');
	}
}
function hideEditForm(badge_slug) {
	var editRow = document.getElementById('badge-edit-row-' + badge_slug);
	if (editRow) {
		editRow.style.display = 'none';
		var mainRow = document.getElementById('badge-row-' + badge_slug);
		if (mainRow) mainRow.classList.remove('editing');
	}
}
function confirmDelete(badge_slug) {
	if (confirm('Are you sure you want to delete this badge type? This cannot be undone.')) {
		document.getElementById('badge-delete-form-' + badge_slug).submit();
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
	var assignBtn = document.getElementById('assign-default-badge-types');
	var assignMessage = document.getElementById('assign-badge-types-message');
	if (assignBtn && assignMessage) {
		assignBtn.addEventListener('click', function(e) {
			e.preventDefault();
			assignBtn.disabled = true;
			assignMessage.style.display = 'none';
			assignMessage.textContent = '';

			fetch(ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({ action: 'onlinesched_assign_default_badge_types' })
			})
				.then(function(response) {
					if (!response.ok) throw new Error('HTTP ' + response.status);
					return response.json();
				})
				.then(function(resp) {
					var msgClass = resp.success ? 'schedule-updated' : 'upload-error';
					var msgText = resp.success ?
						'Assigned badge types to ' + resp.data.updated + ' tags.' :
						'Error: ' + resp.data;

					var msgBox = document.createElement('div');
					msgBox.className = msgClass;

					var closeBtn = document.createElement('button');
					closeBtn.className = 'close-message';
					closeBtn.type = 'button';
					closeBtn.innerHTML = '&times;';
					closeBtn.addEventListener('click', function() {
						closeMessageBox(closeBtn);
					});

					var msgParagraph = document.createElement('p');
					msgParagraph.textContent = msgText;

					msgBox.appendChild(closeBtn);
					msgBox.appendChild(msgParagraph);
					assignMessage.appendChild(msgBox);
					assignMessage.style.display = '';
				})
				.catch(function(error) {
					var msgBox = document.createElement('div');
					msgBox.className = 'upload-error';
					var msgParagraph = document.createElement('p');
					msgParagraph.textContent = 'Error: ' + error.message;
					msgBox.appendChild(msgParagraph);
					assignMessage.appendChild(msgBox);
					assignMessage.style.display = '';
				})
				.finally(function() {
					assignBtn.disabled = false;
				});
		});
	}
});
