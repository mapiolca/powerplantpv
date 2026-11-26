/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
(function () {
	"use strict";

	function select2(root) {
		const $ = window.jQuery;
		if (!$ || !$.fn.select2) return;
		$(root).find("select[data-residential-select]").each(function () {
			if (!$(this).data("select2")) $(this).select2({ width: "resolve" });
		});
	}

	function syncPlacement(placement) {
		const fields = placement.querySelector("[data-residential-placement-fields]");
		fields.hidden = !placement.querySelector("input[type=checkbox]").checked;
		if (!fields.hidden) select2(fields);
	}

	function appendSerial(serials, group) {
		const row = document.createElement("div");
		row.className = "marginbottomonly";
		row.setAttribute("data-residential-serial", "");
		const label = document.createElement("label");
		label.append(group.dataset.serial + " ");
		const number = document.createElement("span");
		number.setAttribute("data-residential-serial-number", "");
		label.append(number, " ");
		const input = document.createElement("input");
		input.type = "text";
		input.name = serials.dataset.name;
		input.maxLength = 128;
		input.className = "flat maxwidth150";
		label.append(input);
		const remove = document.createElement("button");
		remove.type = "button";
		remove.className = "button";
		remove.textContent = group.dataset.remove;
		remove.setAttribute("data-residential-remove-serial", "");
		row.append(label, " ", remove);
		serials.append(row);
	}

	function updateString(item, group, resetProduct, commitQuantity = true) {
		const micro = item.querySelector("[data-residential-micro]").checked;
		const conventionalFields = item.querySelector("[data-residential-conventional]");
		const microFields = item.querySelector("[data-residential-micro-fields]");
		microFields.querySelector("[data-residential-serial-help]").hidden = true;
		conventionalFields.hidden = micro;
		microFields.hidden = !micro;
		for (const input of conventionalFields.querySelectorAll("input")) input.disabled = micro;
		for (const input of microFields.querySelectorAll("input, select")) input.disabled = !micro;
		if (!micro) return;

		const plant = item.querySelector("[data-residential-plant]");
		const product = item.querySelector("[data-residential-product]");
		const products = JSON.parse(group.dataset.products)[plant.value] || {};
		const old = resetProduct ? "" : product.value;
		product.replaceChildren(new Option(""));
		for (const [id, reference] of Object.entries(products)) product.add(new Option(reference.label, id));
		const references = Object.keys(products);
		product.value = Object.hasOwn(products, old) ? old : references.length === 1 ? references[0] : "";
		if (window.jQuery?.fn.select2) window.jQuery(product).trigger("change.select2");

		const limit = products[product.value]?.maximum;
		const quantity = item.querySelector("[data-residential-quantity]");
		const serials = item.querySelector("[data-residential-serials]");
		const count = Number(quantity.value);
		const note = item.querySelector("[data-residential-limit]");
		note.textContent = !references.length ? group.dataset.noProduct
			: limit ? group.dataset.maximum + " : " + limit : group.dataset.noLimit;
		if (limit && count > limit) note.textContent += " · " + group.dataset.exceeded;

		// Populated series are removed only by the technician, including after a lower quantity is typed.
		while (commitQuantity && serials.children.length > count && count > 0 && !serials.lastElementChild.querySelector("input").value) {
			serials.lastElementChild.remove();
		}
		if (commitQuantity && serials.children.length > count && serials.children.length > 0) quantity.value = serials.children.length;
		const requested = Number(quantity.value);
		if (Number.isInteger(requested) && requested <= 1000 && (!limit || requested <= limit)) {
			while (serials.children.length < requested) appendSerial(serials, group);
		}
		Array.from(serials.children).forEach((row, i) => {
			row.querySelector("[data-residential-serial-number]").textContent = i + 1;
		});
		select2(microFields);
	}

	function markChanged(group) {
		group.dispatchEvent(new Event("change", { bubbles: true }));
	}

	function initialize() {
		document.querySelectorAll("[data-residential-placement]").forEach((placement) => {
			placement.addEventListener("change", () => syncPlacement(placement));
			syncPlacement(placement);
		});
		document.querySelectorAll("[data-residential-group]").forEach((group) => {
			const items = group.querySelector("[data-residential-items]");
			const microStrings = group.dataset.residentialGroup === "strings";
			let nextIndex = items.children.length;
			const refreshNumbers = () => Array.from(items.children).forEach((item, i) => {
				item.querySelector("[data-residential-number]").textContent = i + 1;
			});
			group.addEventListener("input", (event) => {
				if (microStrings && event.target.matches("[data-residential-quantity]")) {
					updateString(event.target.closest("[data-residential-item]"), group, false, false);
				}
			});
			group.addEventListener("change", (event) => {
				const item = event.target.closest("[data-residential-item]");
				if (microStrings && item && event.target.matches("[data-residential-micro], [data-residential-plant], [data-residential-product], [data-residential-quantity]")) {
					updateString(item, group, event.target.matches("[data-residential-plant]"));
				}
			});
			if (window.jQuery?.fn.select2) {
				window.jQuery(group).on("select2:select select2:clear", "[data-residential-plant], [data-residential-product]", function () {
					updateString(this.closest("[data-residential-item]"), group, this.matches("[data-residential-plant]"));
				});
			}
			group.addEventListener("click", (event) => {
				const button = event.target.closest("button");
				if (!button) return;
				if (button.hasAttribute("data-residential-add")) {
					const template = group.querySelector("[data-residential-template]");
					items.insertAdjacentHTML("beforeend", template.innerHTML.replaceAll("RESINDEX", String(nextIndex++)));
					if (microStrings) updateString(items.lastElementChild, group, false);
					select2(items.lastElementChild);
				} else if (button.hasAttribute("data-residential-remove")) {
					button.closest("[data-residential-item]").remove();
				} else if (button.hasAttribute("data-residential-remove-serial")) {
					const item = button.closest("[data-residential-item]");
					const serials = button.closest("[data-residential-serials]");
					button.closest("[data-residential-serial]").remove();
					item.querySelector("[data-residential-quantity]").value = serials.children.length || "";
					updateString(item, group, false);
				} else {
					return;
				}
				event.preventDefault();
				refreshNumbers();
				markChanged(group);
			});
			refreshNumbers();
			if (microStrings) Array.from(items.children).forEach((item) => updateString(item, group, false));
			select2(items);
		});
	}
	if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", initialize, { once: true });
	else initialize();
}());
