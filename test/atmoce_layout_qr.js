/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const jsQR = require('../js/vendor/jsqr-1.4.0.js');

const fixture = JSON.parse(fs.readFileSync(0, 'utf8'));
const cells = fixture.map((code) => {
	const width = (code.num_cols + 8) * 4;
	const data = new Uint8ClampedArray(width * width * 4).fill(255);
	for (let y = 0; y < width; y++) {
		for (let x = 0; x < width; x++) {
			if (code.bcode[Math.floor(y / 4) - 4]?.[Math.floor(x / 4) - 4]) {
				const offset = (y * width + x) * 4;
				data.fill(0, offset, offset + 3);
			}
		}
	}
	return { width, height: width, data };
});
const size = cells[0].width;
const element = (props = {}) => ({
	...props,
	events: {},
	addEventListener(name, handler) { this.events[name] = handler; },
});
const createRow = (serial, line) => {
	const field = { value: '' };
	const selection = { value: String(line) };
	return {
		dataset: { serial, known: '0', basePositions: '', revision: 'initial' },
		field,
		querySelector(name) { return name === '[data-ppv-line]' ? selection : field; },
	};
};
const rows = [createRow('00123', 11), createRow('00456', 12)];
const image = element({ files: [{}] });
const report = element();
const scan = element();
const preview = element({
	getContext() { return { drawImage() {}, strokeRect() {} }; },
});
const form = element({
	dataset: { reading: 'Scanning', result: '%s read, %s empty, %s unknown', corners: 'Crop', empty: 'Empty' },
	elements: { review: {}, keep_photo: { value: '0' } },
	querySelectorAll(selector) {
		return selector === '[data-serial]' ? rows : rows.map((row) => row.querySelector(selector));
	},
});
const elements = {
	'ppv-atmoce-layout': form, 'ppv-layout-image': image, 'ppv-layout-preview': preview,
	'ppv-layout-scan': scan, 'ppv-layout-status': report,
	'ppv-layout-zone': { value: 'East' }, 'ppv-layout-cols': { valueAsNumber: 2 }, 'ppv-layout-rows': { valueAsNumber: 1 },
};
let created = 0;
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../js/powerplantpv_atmoce_layout.js'), 'utf8'), {
	window: {
		addEventListener(_, callback) { callback(); },
		requestAnimationFrame(callback) { callback(); },
		jsQR,
	},
	document: {
		getElementById(id) { return elements[id]; },
		createElement() {
			if (created++ === 0) {
				return { getContext: preview.getContext };
			}
			let current = 0;
			return {
				getContext() {
					return {
						drawImage(_, x, y, width, height) {
							assert.equal(y, 0);
							assert.equal(width, size);
							assert.equal(height, size);
							current = x / size;
						},
						getImageData() { return cells[current]; },
					};
				},
			};
		},
	},
	Image: class {
		naturalWidth = size * 2;
		naturalHeight = size;
		decode() { return Promise.resolve(); }
	},
	URL: { createObjectURL() { return 'local-image'; }, revokeObjectURL() {} },
});

(async () => {
	await image.events.change();
	await scan.events.click();
	assert.equal(rows[0].field.value, 'EAST A1');
	assert.equal(rows[1].field.value, 'EAST B1');
	assert.equal(report.textContent, '2 read, 0 empty, 0 unknown');
	assert.equal(form.elements.review.value, undefined, 'Scan must not submit the result');
	rows[1].field.value = 'EAST B2';
	form.events.submit({ preventDefault() { assert.fail('A valid review was rejected'); } });
	assert.equal(image.disabled, true, 'The photograph stays local unless archiving is selected');
	assert.deepEqual(JSON.parse(form.elements.review.value).map((row) => [row.serial, row.positions]), [['00123', 'EAST A1'], ['00456', 'EAST B2']]);
	form.elements.keep_photo.value = '1';
	form.events.submit({ preventDefault() { assert.fail('Archiving was rejected'); } });
	assert.equal(image.disabled, false, 'An explicitly archived photograph is submitted with the review');
	process.stdout.write('Two QR codes read from one simulated layout, with editable assignments and optional evidence\n');
})().catch((error) => {
	process.stderr.write(String(error) + '\n');
	process.exitCode = 1;
});
