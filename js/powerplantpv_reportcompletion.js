/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
(() => {
	'use strict';

	function initialize() {
		const config = document.querySelector('[data-completion-config]');
		if (!config || config.dataset.initialized) return;
		config.dataset.initialized = '1';
		const {editors, options, connectionTypes, labels} = JSON.parse(config.textContent);
		const hidden = config.parentElement.querySelector('[data-completion-payload]');
		const label = key => labels[key] || key;

		function element(parent, tag, text = '') {
			const node = document.createElement(tag);
			node.textContent = text;
			parent.append(node);
			return node;
		}

		function sync(editor) {
			editor.dirty = true;
			hidden.value = JSON.stringify(Object.fromEntries(editors.filter(item => item.dirty).map(item =>
				[item.id, {revision: item.revision, proposal: item.proposal}])));
			hidden.dispatchEvent(new Event('change', {bubbles: true}));
		}

		function button(parent, key, action) {
			const node = element(parent, 'button', label(key));
			node.type = 'button';
			node.className = 'button small';
			node.addEventListener('click', action);
			return node;
		}

		function control(parent, key, value, change, settings = {}) {
			const wrapper = element(parent, 'label');
			wrapper.className = 'inline-block marginrightonly marginbottomonly';
			element(wrapper, 'span', label(key));
			element(wrapper, 'br');
			const field = element(wrapper, settings.options ? 'select' : 'input');
			field.className = 'flat maxwidth300 powerplantpv-report-control';
			const readOnly = typeof change !== 'function';
			if (settings.options) {
				field.add(new Option('', ''));
				Object.entries(settings.options).forEach(([id, name]) => field.add(new Option(name, id)));
				field.value = value || '';
				field.disabled = readOnly;
				if (window.jQuery && window.jQuery.fn.select2) {
					window.jQuery(field).select2({width: 'resolve'}).on('change', () => {
						if (!readOnly) change(field.value);
					});
					return field;
				}
			} else {
				field.type = settings.numeric ? 'number' : 'text';
				field.value = value ?? '';
				field.readOnly = readOnly;
				if (settings.length) field.maxLength = settings.length;
				if (settings.numeric) field.min = '1';
			}
			if (!readOnly) field.addEventListener(settings.options ? 'change' : 'input', () => change(field.value));
			return field;
		}

		function removeEditor(parent, row, list, value, editor, onRemove = () => {}) {
			button(parent, 'Delete', () => {
				list.splice(list.indexOf(value), 1);
				if (window.jQuery && window.jQuery.fn.select2) {
					window.jQuery(row).find('select.select2-hidden-accessible').select2('destroy');
				}
				row.remove();
				onRemove();
				sync(editor);
			});
		}

		function renderPlant(editor) {
			const current = editor.current;
			const proposal = editor.proposal;
			const moduleSelectors = new Set();
			const availableModules = () => ({
				...Object.fromEntries(Object.entries(current.modules).map(([key, module]) => [key, module.label])),
				...Object.fromEntries(proposal.modules.filter(module => options.modules[module.product]).map(module => [module.product, options.modules[module.product]])),
			});
			function refreshModules() {
				for (const select of moduleSelectors) {
					if (!select.isConnected) {
						moduleSelectors.delete(select);
						continue;
					}
					const value = select.value;
					select.replaceChildren(new Option('', ''));
					Object.entries(availableModules()).forEach(([id, name]) => select.add(new Option(name, id)));
					select.value = value;
					if (window.jQuery && window.jQuery.fn.select2) window.jQuery(select).trigger('change.select2');
				}
			}
			proposal.inverters = Object.values(proposal.inverters || {});
			proposal.modules = Object.values(proposal.modules || {});
			const block = element(config.parentElement, 'details');
			block.className = 'fichecenter marginbottomonly';
			element(block, 'summary', editor.label);
			const write = (row, field) => value => {
				row[field] = value;
				sync(editor);
			};
			control(block, 'PowerPlantPrmPdlNumber', proposal.prm || current.prm, current.prm ? null : write(proposal, 'prm'), {length: 128});
			if (current.type) {
				control(block, 'PowerPlantConnectionType', current.typeLabel, null);
			} else {
				control(block, 'PowerPlantConnectionType', proposal.type, write(proposal, 'type'), {options: connectionTypes});
			}

			const moduleBlock = element(block, 'div');
			element(moduleBlock, 'h4', label('PowerPlantPDFModules'));
			const installed = Object.values(current.modules).map(module => module.label + ' × ' + module.qty);
			if (installed.length) element(moduleBlock, 'p', installed.join(' · '));
			const moduleList = element(moduleBlock, 'div');
			const additions = Object.fromEntries(Object.entries(options.modules).filter(([id]) => !current.modules[id]));
			function renderModule(module) {
				const line = element(moduleList, 'div');
				control(line, 'SelectProduct', module.product, value => {
					write(module, 'product')(value);
					refreshModules();
				}, {options: additions});
				control(line, 'Qty', module.count, write(module, 'count'), {numeric: true});
				removeEditor(line, line, proposal.modules, module, editor, refreshModules);
			}
			proposal.modules.forEach(renderModule);
			button(moduleBlock, 'PowerPlantPVCompletionAddModules', () => {
				const module = {product: '', count: ''};
				proposal.modules.push(module);
				renderModule(module);
				sync(editor);
			});
			const inverterBlock = element(block, 'div');
			element(inverterBlock, 'h4', label('PowerPlantPVInverter'));
			const inverterList = element(inverterBlock, 'div');
			const installedOptions = {};
			for (const [id, row] of Object.entries(current.inverters)) {
				installedOptions[id] = [options.inverters[row.fk_product], row.equipment_marker, row.equipment_identification, row.serial_number].filter(Boolean).join(' · ');
			}

			function renderInverter(inverter) {
				const line = element(inverterList, 'fieldset');
				const id = inverter.key.startsWith('line-') ? inverter.key.slice(5) : '';
				const source = current.inverters[id] || {};
				element(line, 'legend', id ? installedOptions[id] : label('PowerPlantPVInverter'));
				if (!id) control(line, 'SelectProduct', inverter.product, write(inverter, 'product'), {options: options.inverters});
				for (const [field, key, length] of [
					['equipment_marker', 'PowerPlantPVCompletionMarker', 32],
					['equipment_identification', 'PowerPlantPVCompletionIdentification', 255],
					['serial_number', 'SerialNumber', 128],
				]) {
					control(line, key, source[field] || inverter[field], source[field] ? null : write(inverter, field), {length});
				}

				const existingMppts = current.mppts[id] || {};
				const existingStrings = current.strings[id] || {};
				inverter.mppts = Object.values(inverter.mppts || {});
				const mpptList = element(line, 'div');
				function renderMppt(mppt, installedMppt = false) {
					const group = element(mpptList, 'fieldset');
					element(group, 'legend', label('PowerPlantPVMPPT'));
					control(group, 'PowerPlantPVMPPT', mppt.number, installedMppt ? null : write(mppt, 'number'), {numeric: true});
					const configured = installedMppt && Number(existingMppts[mppt.number]) > 0;
					const activate = () => {
						if (!inverter.mppts.includes(mppt)) inverter.mppts.push(mppt);
					};
					control(group, 'PowerPlantPVPVInputCount', mppt.inputs, configured ? null : value => {
						activate();
						write(mppt, 'inputs')(value);
					}, {numeric: true});
					mppt.strings = Object.values(mppt.strings || {});
					const stringList = element(group, 'div');
					const installedStrings = installedMppt ? Object.values(existingStrings[mppt.number] || {}) : [];
					for (const existing of installedStrings) {
						const text = [existing.pv_input_number, existing.string_ref, options.modules[existing.fk_product],
							existing.module_count ? '× ' + existing.module_count : ''].filter(Boolean).join(' · ');
						element(stringList, 'p', text);
					}
					function renderString(string) {
						const stringRow = element(stringList, 'div');
						control(stringRow, 'PowerPlantPVCompletionInput', string.input, write(string, 'input'), {numeric: true});
						moduleSelectors.add(control(stringRow, 'PowerPlantPDFModules', string.product, write(string, 'product'), {options: availableModules()}));
						control(stringRow, 'Qty', string.count, write(string, 'count'), {numeric: true});
						removeEditor(stringRow, stringRow, mppt.strings, string, editor);
					}
					mppt.strings.forEach(renderString);
					button(group, 'PowerPlantPVCompletionAddString', () => {
						activate();
						const string = {input: '', product: '', count: ''};
						mppt.strings.push(string);
						renderString(string);
						sync(editor);
					});
					if (!installedMppt) removeEditor(group, group, inverter.mppts, mppt, editor);
				}
				inverter.mppts.forEach(mppt => renderMppt(mppt, Object.hasOwn(existingMppts, mppt.number)));
				for (const [number, inputs] of Object.entries(existingMppts)) {
					if (!inverter.mppts.some(mppt => String(mppt.number) === number)) renderMppt({number, inputs: inputs || '', strings: []}, true);
				}
				button(line, 'PowerPlantPVCompletionAddMppt', () => {
					const mppt = {number: '', inputs: '', strings: []};
					inverter.mppts.push(mppt);
					renderMppt(mppt);
					sync(editor);
				});
				removeEditor(line, line, proposal.inverters, inverter, editor);
			}

			proposal.inverters.forEach(renderInverter);
			control(inverterBlock, 'PowerPlantPVCompletionInstalled', '', value => {
				if (!value || proposal.inverters.some(candidate => candidate.key === 'line-' + value)) return;
				const inverter = {key: 'line-' + value, mppts: []};
				proposal.inverters.push(inverter);
				renderInverter(inverter);
				sync(editor);
			}, {options: installedOptions});
			button(inverterBlock, 'PowerPlantPVCompletionAddInverter', () => {
				const key = Array.from(crypto.getRandomValues(new Uint8Array(8)), value => value.toString(16).padStart(2, '0')).join('');
				const inverter = {key: 'new-' + key, product: '', mppts: []};
				proposal.inverters.push(inverter);
				renderInverter(inverter);
				sync(editor);
			});
		}

		editors.forEach(renderPlant);
		editors.filter(editor => editor.dirty).forEach(sync);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
	else initialize();
})();
