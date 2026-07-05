/* global window, document */
(function () {
	'use strict';

	function getScrollY() {
		if (typeof window.scrollY === 'number') {
			return window.scrollY;
		}

		return window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
	}

	function captureScrollPosition(form) {
		var scrollInput = form.querySelector('#powerplantpv-scroll-y');
		if (!scrollInput) {
			return;
		}

		scrollInput.value = String(Math.max(0, Math.round(getScrollY())));
	}

	function cleanScrollParameter() {
		if (!window.history || !window.history.replaceState || !window.URL) {
			return;
		}

		var url = new URL(window.location.href);
		if (!url.searchParams.has('report_scroll_y')) {
			return;
		}

		url.searchParams.delete('report_scroll_y');
		window.history.replaceState(null, document.title, url.pathname + url.search + url.hash);
	}

	function restoreScrollPosition() {
		var restoreInput = document.getElementById('powerplantpv-restore-scroll-y');
		if (!restoreInput) {
			return;
		}

		var scrollY = parseInt(restoreInput.value, 10);
		if (!scrollY || scrollY < 1) {
			return;
		}

		window.requestAnimationFrame(function () {
			window.scrollTo(0, scrollY);
			window.setTimeout(function () {
				window.scrollTo(0, scrollY);
				cleanScrollParameter();
			}, 150);
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var form = document.getElementById('powerplantpvreportform');
		if (form) {
			form.addEventListener('submit', function () {
				captureScrollPosition(form);
			});
		}

		restoreScrollPosition();
	});
}());
