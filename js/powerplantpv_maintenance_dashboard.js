(function () {
	'use strict';

	function initializeDashboard() {
		var dashboard = document.getElementById('powerplantpv-maintenance-dashboard');
		if (!dashboard) return;
		var dragged = null;
		var select = document.getElementById('powerplantpv_maintenance_widget_select');
		var message = document.getElementById('powerplantpv-maintenance-widget-message');
		var saveQueue = Promise.resolve(true);

		function displayMessage(text, isError) {
			message.textContent = text || '';
			message.className = 'marginleftonly ' + (isError ? 'error' : 'opacitymedium');
		}

		function request(action, layout) {
			var params = new URLSearchParams();
			params.append('token', dashboard.dataset.token);
			params.append('action', action);
			(layout || []).forEach(function (item) {
				params.append('widget_code[]', item.code);
				params.append('widget_column[]', String(item.column));
			});
			displayMessage('…', false);
			return fetch(dashboard.dataset.saveUrl, {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: params.toString(),
				credentials: 'same-origin'
			}).then(function (response) {
				if (!response.ok) throw new Error('HTTP ' + response.status);
				return response.json();
			}).then(function () {
				displayMessage('✓', false);
				return true;
			}).catch(function () {
				displayMessage('!', true);
				return false;
			});
		}

		function currentLayout() {
			var layout = [];
			dashboard.querySelectorAll('.powerplantpv-maintenance-widget-column').forEach(function (column) {
				column.querySelectorAll(':scope > .powerplantpv-maintenance-widget-card').forEach(function (card) {
					layout.push({code: card.dataset.widgetCode, column: Number(column.dataset.column)});
				});
			});
			return layout;
		}

		function save() {
			var layout = currentLayout();
			saveQueue = saveQueue.then(function () { return request('save_layout', layout); });
			return saveQueue;
		}

		dashboard.addEventListener('dragstart', function (event) {
			var card = event.target.closest('.powerplantpv-maintenance-widget-card');
			if (!card || card.closest('#powerplantpv-maintenance-widget-templates')) return;
			dragged = card;
			card.classList.add('is-dragging');
			event.dataTransfer.effectAllowed = 'move';
		});
		dashboard.addEventListener('dragend', function () {
			if (dragged) dragged.classList.remove('is-dragging');
			dragged = null;
			save();
		});
		dashboard.querySelectorAll('.powerplantpv-maintenance-widget-column').forEach(function (column) {
			column.addEventListener('dragover', function (event) {
				event.preventDefault();
				if (!dragged) return;
				var after = Array.from(column.querySelectorAll(':scope > .powerplantpv-maintenance-widget-card:not(.is-dragging)')).find(function (card) {
					return event.clientY < card.getBoundingClientRect().top + card.offsetHeight / 2;
				});
				column.insertBefore(dragged, after || null);
			});
		});

		dashboard.addEventListener('click', function (event) {
			var remove = event.target.closest('.powerplantpv-maintenance-widget-remove');
			if (remove && !remove.closest('#powerplantpv-maintenance-widget-templates')) {
				event.preventDefault();
				var card = remove.closest('.powerplantpv-maintenance-widget-card');
				var option = select ? Array.from(select.options).find(function (item) { return item.value === card.dataset.widgetCode; }) : null;
				if (!option && select) {
					option = document.createElement('option');
					option.value = card.dataset.widgetCode;
					option.textContent = card.querySelector('.powerplantpv-maintenance-widget-title > span:nth-child(2)').textContent;
					select.appendChild(option);
				}
				if (option) option.hidden = false;
				if (select && window.jQuery) window.jQuery(select).trigger('change');
				card.remove();
				save();
			}
		});

		var addButton = document.getElementById('powerplantpv-maintenance-widget-add');
		if (addButton) addButton.addEventListener('click', function (event) {
			event.preventDefault();
			if (!select || !select.value) return;
			var template = Array.from(dashboard.querySelectorAll('.powerplantpv-maintenance-widget-template')).find(function (item) { return item.dataset.widgetCode === select.value; });
			if (!template) return;
			var clone = template.firstElementChild.cloneNode(true);
			dashboard.querySelector('.powerplantpv-maintenance-widget-column[data-column="0"]').appendChild(clone);
			var selectedOption = Array.from(select.options).find(function (item) { return item.value === select.value; });
			if (selectedOption) selectedOption.hidden = true;
			select.value = '';
			if (window.jQuery) window.jQuery(select).trigger('change');
			save();
		});

		var resetButton = document.getElementById('powerplantpv-maintenance-widget-reset');
		if (resetButton) resetButton.addEventListener('click', function (event) {
			event.preventDefault();
			saveQueue.then(function () { return request('reset_layout', []); }).then(function (success) {
				if (success) window.location.reload();
			});
		});
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeDashboard);
	else initializeDashboard();
}());
