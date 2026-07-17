/* global window, document */
(function () {
	'use strict';
	var mobileMediaQuery = window.matchMedia ? window.matchMedia('(max-width: 767px)') : null;
	var editorIframeSelector = '.powerplantpv-report-input iframe';
	var editorFontMarker = 'data-powerplantpv-mobile-font-size';
	var editorOriginalFontSize = 'data-powerplantpv-original-font-size';
	var editorOriginalFontPriority = 'data-powerplantpv-original-font-priority';

	function isMobileReportViewport() {
		return mobileMediaQuery ? mobileMediaQuery.matches : window.innerWidth <= 767;
	}

	function updateEditorIframeFontSize(iframe) {
		var editorDocument;
		var editorBody;

		try {
			editorDocument = iframe.contentDocument || (iframe.contentWindow ? iframe.contentWindow.document : null);
			editorBody = editorDocument ? editorDocument.body : null;
		} catch (error) {
			return;
		}

		if (!editorBody) {
			return;
		}

		if (isMobileReportViewport()) {
			if (!editorBody.hasAttribute(editorFontMarker)) {
				editorBody.setAttribute(editorFontMarker, '1');
				editorBody.setAttribute(editorOriginalFontSize, editorBody.style.getPropertyValue('font-size'));
				editorBody.setAttribute(editorOriginalFontPriority, editorBody.style.getPropertyPriority('font-size'));
			}
			editorBody.style.setProperty('font-size', '16px', 'important');
			return;
		}

		if (editorBody.hasAttribute(editorFontMarker)) {
			var originalFontSize = editorBody.getAttribute(editorOriginalFontSize) || '';
			var originalFontPriority = editorBody.getAttribute(editorOriginalFontPriority) || '';
			if (originalFontSize) {
				editorBody.style.setProperty('font-size', originalFontSize, originalFontPriority);
			} else {
				editorBody.style.removeProperty('font-size');
			}
			editorBody.removeAttribute(editorFontMarker);
			editorBody.removeAttribute(editorOriginalFontSize);
			editorBody.removeAttribute(editorOriginalFontPriority);
		}
	}

	function registerEditorIframe(iframe) {
		if (!iframe.matches(editorIframeSelector)) {
			return;
		}

		if (!iframe.hasAttribute('data-powerplantpv-font-listener')) {
			iframe.setAttribute('data-powerplantpv-font-listener', '1');
			iframe.addEventListener('load', function () {
				updateEditorIframeFontSize(iframe);
			});
		}

		updateEditorIframeFontSize(iframe);
	}

	function updateEditorIframes(form) {
		var editorIframes = form.querySelectorAll(editorIframeSelector);
		for (var i = 0; i < editorIframes.length; i++) {
			registerEditorIframe(editorIframes[i]);
		}
	}

	function observeEditorIframes(form) {
		if (!window.MutationObserver) {
			return;
		}

		var observer = new MutationObserver(function (mutations) {
			for (var i = 0; i < mutations.length; i++) {
				for (var j = 0; j < mutations[i].addedNodes.length; j++) {
					var addedNode = mutations[i].addedNodes[j];
					if (addedNode.nodeType !== 1) {
						continue;
					}
					if (addedNode.matches && addedNode.matches(editorIframeSelector)) {
						registerEditorIframe(addedNode);
					}
					if (addedNode.querySelectorAll) {
						var nestedIframes = addedNode.querySelectorAll(editorIframeSelector);
						for (var k = 0; k < nestedIframes.length; k++) {
							registerEditorIframe(nestedIframes[k]);
						}
					}
				}
			}
		});

		observer.observe(form, {childList: true, subtree: true});
	}

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
			updateEditorIframes(form);
			observeEditorIframes(form);

			var updateFonts = function () {
				updateEditorIframes(form);
			};
			if (mobileMediaQuery) {
				if (mobileMediaQuery.addEventListener) {
					mobileMediaQuery.addEventListener('change', updateFonts);
				} else if (mobileMediaQuery.addListener) {
					mobileMediaQuery.addListener(updateFonts);
				}
			} else {
				window.addEventListener('resize', updateFonts);
			}
		}

		restoreScrollPosition();
	});
}());
