(function () {
	'use strict';

	document.addEventListener('submit', function (event) {
		var form = event.target;
		var message = form.getAttribute('data-wccp-confirm');
		if (message && !window.confirm(message)) {
			event.preventDefault();
		}
	});

	var search = document.getElementById('wccp-field-search');
	if (search) {
		search.addEventListener('input', function () {
			var query = search.value.toLowerCase().trim();
			document.querySelectorAll('[data-wccp-field-row]').forEach(function (row) {
				row.hidden = query !== '' && row.getAttribute('data-search').indexOf(query) === -1;
				if (row.getAttribute('data-wccp-field-key') === 'billing_delivery_area') {
					var editor = row.parentNode.querySelector('[data-wccp-delivery-editor-row]');
					if (editor) {
						editor.hidden = row.hidden;
					}
				}
			});
		});
	}

	var draggedRow = null;
	document.querySelectorAll('[data-wccp-field-row]').forEach(function (row) {
		row.addEventListener('dragstart', function () {
			draggedRow = row;
			row.classList.add('is-dragging');
		});
		row.addEventListener('dragend', function () {
			row.classList.remove('is-dragging');
			draggedRow = null;
			row.closest('tbody').querySelectorAll('[data-wccp-field-row]').forEach(function (sortedRow, index) {
				var priority = sortedRow.querySelector('input[type="number"]');
				if (priority) {
					priority.value = String((index + 1) * 10);
				}
			});
			var deliveryRow = row.closest('tbody').querySelector('[data-wccp-field-key="billing_delivery_area"]');
			var deliveryEditor = row.closest('tbody').querySelector('[data-wccp-delivery-editor-row]');
			if (deliveryRow && deliveryEditor) {
				deliveryRow.insertAdjacentElement('afterend', deliveryEditor);
			}
		});
		row.addEventListener('dragover', function (event) {
			if (!draggedRow || draggedRow === row || draggedRow.parentNode !== row.parentNode) {
				return;
			}
			event.preventDefault();
			var box = row.getBoundingClientRect();
			row.parentNode.insertBefore(draggedRow, event.clientY < box.top + box.height / 2 ? row : row.nextSibling);
		});
	});

	var type = document.getElementById('wccp-custom-type');
	var options = document.getElementById('wccp-custom-options');
	var content = document.getElementById('wccp-custom-content');
	function updateFieldEditor() {
		if (!type) {
			return;
		}
		if (options) {
			options.closest('tr').hidden = type.value !== 'select' && type.value !== 'radio';
		}
		if (content) {
			content.closest('tr').hidden = type.value !== 'content';
		}
	}
	if (type) {
		type.addEventListener('change', updateFieldEditor);
		updateFieldEditor();
	}
}());
