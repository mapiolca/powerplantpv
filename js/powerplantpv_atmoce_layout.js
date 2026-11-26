/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

window.addEventListener('DOMContentLoaded', () => {
	const form = document.getElementById('ppv-atmoce-layout');
	if (!form) {
		return;
	}
	const imageInput = document.getElementById('ppv-layout-image');
	const preview = document.getElementById('ppv-layout-preview');
	const scan = document.getElementById('ppv-layout-scan');
	const status = document.getElementById('ppv-layout-status');
	const rows = new Map(Array.from(form.querySelectorAll('[data-serial]'), (row) => [row.dataset.serial, row]));
	const source = document.createElement('canvas');
	const cell = document.createElement('canvas');
	let frame = null;
	let firstCorner = null;
	let generation = 0;
	let scanning = false;

	function drawPreview() {
		const view = preview.getContext('2d');
		view.drawImage(source, 0, 0, preview.width, preview.height);
		if (frame) {
			view.strokeStyle = '#07569e';
			view.lineWidth = 3;
			view.strokeRect(frame.x * preview.width, frame.y * preview.height, frame.w * preview.width, frame.h * preview.height);
		}
	}

	imageInput.addEventListener('change', async () => {
		const expected = ++generation;
		const file = imageInput.files[0];
		frame = null;
		firstCorner = null;
		scanning = false;
		scan.disabled = true;
		preview.hidden = true;
		status.textContent = '';
		if (!file) {
			return;
		}
		const image = new Image();
		const url = URL.createObjectURL(file);
		try {
			image.src = url;
			await image.decode();
			if (expected !== generation) {
				return;
			}
			const scale = Math.min(1, 4800 / Math.max(image.naturalWidth, image.naturalHeight));
			source.width = Math.max(1, Math.round(image.naturalWidth * scale));
			source.height = Math.max(1, Math.round(image.naturalHeight * scale));
			source.getContext('2d').drawImage(image, 0, 0, source.width, source.height);
			preview.width = Math.min(900, source.width);
			preview.height = Math.max(1, Math.round(source.height * preview.width / source.width));
			preview.hidden = false;
			drawPreview();
			status.textContent = form.dataset.corners;
			scan.disabled = typeof window.jsQR !== 'function';
		} catch (error) {
			if (expected === generation) {
				status.textContent = form.dataset.failed;
			}
		} finally {
			URL.revokeObjectURL(url);
		}
	});

	preview.addEventListener('click', (event) => {
		if (scanning) {
			return;
		}
		const bounds = preview.getBoundingClientRect();
		const point = { x: (event.clientX - bounds.left) / bounds.width, y: (event.clientY - bounds.top) / bounds.height };
		if (firstCorner === null) {
			firstCorner = point;
			return;
		}
		const w = Math.abs(point.x - firstCorner.x);
		const h = Math.abs(point.y - firstCorner.y);
		frame = w > 0.02 && h > 0.02 ? { x: Math.min(point.x, firstCorner.x), y: Math.min(point.y, firstCorner.y), w, h } : null;
		firstCorner = null;
		drawPreview();
	});

	function matchSerial(payload) {
		const value = payload.trim();
		if (rows.has(value)) {
			return value;
		}
		// Try complete fields in a label without inferring partial or ambiguous serials.
		const matches = new Set(value.split(/[\s,;|=:/?&]+/).filter((token) => rows.has(token)));
		return matches.size === 1 ? matches.values().next().value : null;
	}

	scan.addEventListener('click', async () => {
		const columns = document.getElementById('ppv-layout-cols').valueAsNumber;
		const countRows = document.getElementById('ppv-layout-rows').valueAsNumber;
		if (!Number.isInteger(columns) || columns < 1 || columns > 26 || !Number.isInteger(countRows)
			|| countRows < 1 || countRows > 40 || columns * countRows > 300) {
			status.textContent = form.dataset.failed;
			return;
		}

		const expected = ++generation;
		const grid = frame ?? { x: 0, y: 0, w: 1, h: 1 };
		const zone = document.getElementById('ppv-layout-zone').value.trim().toUpperCase();
		const cropWidth = grid.w * source.width / columns;
		const cropHeight = grid.h * source.height / countRows;
		const scale = Math.min(1, 720 / Math.max(cropWidth, cropHeight));
		cell.width = Math.max(1, Math.round(cropWidth * scale));
		cell.height = Math.max(1, Math.round(cropHeight * scale));
		const context = cell.getContext('2d', { willReadFrequently: true });
		let matched = 0;
		let unreadable = 0;
		let unknown = 0;
		const suggestions = [];
		scanning = true;
		scan.disabled = true;
		status.textContent = form.dataset.reading;
		try {
			for (let index = 0; index < columns * countRows; index += 1) {
				if (index % 4 === 0) {
					await new Promise((resolve) => window.requestAnimationFrame(resolve));
				}
				if (expected !== generation) {
					return;
				}
				const x = index % columns;
				const y = Math.floor(index / columns);
				const left = grid.x * source.width + x * cropWidth;
				const top = grid.y * source.height + y * cropHeight;
				context.drawImage(source, left, top, cropWidth, cropHeight, 0, 0, cell.width, cell.height);
				const pixels = context.getImageData(0, 0, cell.width, cell.height);
				const decoded = window.jsQR(pixels.data, cell.width, cell.height, { inversionAttempts: 'dontInvert' });
				if (!decoded) {
					unreadable += 1;
					continue;
				}
				const serial = matchSerial(decoded.data);
				if (serial === null) {
					unknown += 1;
					continue;
				}
				const position = (zone ? zone + ' ' : '') + String.fromCharCode(65 + x) + (countRows - y);
				suggestions.push({ serial, position });
			}

			const occupied = new Map();
			rows.forEach((row, serial) => {
				const field = row.querySelector('[data-ppv-position]');
				field.value.split(',').map((value) => value.trim().toUpperCase()).filter(Boolean)
					.forEach((position) => occupied.set(position, serial));
			});
			suggestions.forEach(({ serial, position }) => {
				const other = occupied.get(position);
				if (other !== undefined && other !== serial) {
					unknown += 1;
					return;
				}
				if (other === undefined) {
					const field = rows.get(serial).querySelector('[data-ppv-position]');
					field.value = field.value.trim() ? field.value.trim() + ', ' + position : position;
					occupied.set(position, serial);
				}
				matched += 1;
			});
			const counts = [matched, unreadable, unknown];
			status.textContent = form.dataset.result.replace(/%s/g, () => counts.shift());
		} catch (error) {
			status.textContent = form.dataset.failed;
		} finally {
			if (expected === generation) {
				scanning = false;
				scan.disabled = false;
			}
		}
	});

	const applyLine = document.getElementById('ppv-layout-apply-line');
	if (applyLine) {
		applyLine.addEventListener('click', () => {
			const chosen = document.getElementById('ppv-layout-default-line').value;
			if (Number(chosen) <= 0) {
				return;
			}
			form.querySelectorAll('[data-ppv-line]').forEach((input) => {
				if (Number(input.value) <= 0) {
					input.value = chosen;
					if (window.jQuery) {
						window.jQuery(input).trigger('change');
					}
				}
			});
		});
	}

	form.addEventListener('submit', (event) => {
		const review = [];
		rows.forEach((row, serial) => {
			const line = Number(row.querySelector('[data-ppv-line]')?.value ?? 0);
			const positions = row.querySelector('[data-ppv-position]').value.trim();
			if (row.dataset.known === '0' ? line > 0 || positions !== '' : positions !== row.dataset.basePositions) {
				review.push({ serial, line, positions, previous: row.dataset.revision });
			}
		});
		const keepPhoto = form.elements.keep_photo?.value === '1';
		if (scanning || !review.length || (keepPhoto && !imageInput.files.length)) {
			event.preventDefault();
			status.textContent = scanning ? form.dataset.reading : form.dataset.empty;
			status.scrollIntoView({ block: 'nearest' });
			return;
		}
		form.elements.review.value = JSON.stringify(review);
		imageInput.disabled = !keepPhoto;
		form.querySelectorAll('[data-ppv-line]').forEach((input) => {
			input.disabled = true;
		});
	});
});
