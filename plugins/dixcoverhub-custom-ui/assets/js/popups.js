(function () {
	'use strict';

	var config = window.DixcoverHubPopup;
	var overlay = document.querySelector('[data-dh-popup]');
	if (!config || !overlay) return;

	var surface = overlay.querySelector('.dh-popup-surface');
	var root = overlay.querySelector('[data-dh-popup-root]');
	var closeButton = overlay.querySelector('[data-dh-popup-close]');
	var scrim = overlay.querySelector('[data-dh-popup-overlay-close]');
	var displayKey = 'dixcoverhub:popup:' + config.id + ':v' + config.version + ':count';
	var frequencyKey = 'dixcoverhub:popup:' + config.id + ':v' + config.version + ':frequency';
	var opened = false;
	var closed = false;
	var lastFocus = null;

	function storageGet(storageName, key) {
		try { return window[storageName].getItem(key); } catch (error) { return null; }
	}

	function storageSet(storageName, key, value) {
		try { window[storageName].setItem(key, value); } catch (error) { /* Storage can be disabled by the browser. */ }
	}

	function dayKey() {
		return new Date().toLocaleDateString('en-CA');
	}

	function weekKey() {
		var date = new Date();
		var first = new Date(date.getFullYear(), 0, 1);
		var week = Math.ceil((((date.getTime() - first.getTime()) / 86400000) + first.getDay() + 1) / 7);
		return date.getFullYear() + '-' + week;
	}

	function currentDevice() {
		var width = window.innerWidth || document.documentElement.clientWidth;
		return width < 768 ? 'mobile' : (width < 1200 ? 'tablet' : 'desktop');
	}

	function frequencyAllows() {
		if (config.frequency === 'every_visit') return true;
		if (config.frequency === 'once_per_session') return !storageGet('sessionStorage', frequencyKey);
		var lastSeen = storageGet('localStorage', frequencyKey);
		if (config.frequency === 'once_per_day') return lastSeen !== dayKey();
		if (config.frequency === 'once_per_week') return lastSeen !== weekKey();
		return lastSeen !== '1';
	}

	function deviceAllows() {
		var device = currentDevice();
		return !!(config.devices && config.devices[device]);
	}

	function record(eventName) {
		if (!config.eventUrl || !window.fetch) return;
		window.fetch(config.eventUrl, {
			method: 'POST',
			credentials: 'same-origin',
			keepalive: true,
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce || '' },
			body: JSON.stringify({ popupId: config.id, version: config.version, event: eventName, path: window.location.pathname.slice(0, 500), device: currentDevice() })
		}).catch(function () { /* Analytics should never block the experience. */ });
	}

	function isDismissedByLimit() {
		var max = parseInt(config.maxDisplays, 10) || 0;
		var count = parseInt(storageGet('localStorage', displayKey) || '0', 10) || 0;
		return max > 0 && count >= max;
	}

	function markDisplayed() {
		var count = parseInt(storageGet('localStorage', displayKey) || '0', 10) || 0;
		storageSet('localStorage', displayKey, String(count + 1));
		if (config.frequency === 'once_per_session') storageSet('sessionStorage', frequencyKey, '1');
		if (config.frequency === 'once_per_day') storageSet('localStorage', frequencyKey, dayKey());
		if (config.frequency === 'once_per_week') storageSet('localStorage', frequencyKey, weekKey());
		if (config.frequency === 'once_ever') storageSet('localStorage', frequencyKey, '1');
	}

	function visibleFocusables() {
		return Array.prototype.slice.call(surface.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')).filter(function (element) {
			return !element.hidden && element.getAttribute('aria-hidden') !== 'true' && element.getClientRects().length;
		});
	}

	function openPopup() {
		if (opened || closed || !deviceAllows() || !frequencyAllows() || isDismissedByLimit()) return;
		opened = true;
		lastFocus = document.activeElement;
		overlay.dataset.state = 'open';
		overlay.setAttribute('aria-hidden', 'false');
		document.body.classList.add('dh-popup-open');
		markDisplayed();
		record('impression');
		window.setTimeout(function () {
			var focusables = visibleFocusables();
			(closeButton || focusables[0] || surface).focus();
		}, 40);
		if (config.javascript) {
			try {
				new Function('root', 'closePopup', 'popupId', config.javascript)(root, closePopup, config.id); // Trusted administrator-authored code.
			} catch (error) {
				if (window.console && console.error) console.error('DixcoverHub popup script error:', error);
			}
		}
	}

	function closePopup(reason) {
		if (!opened || closed) return;
		closed = true;
		overlay.dataset.state = 'closed';
		overlay.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('dh-popup-open');
		if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
		if (reason) record(reason);
	}

	if (!deviceAllows() || !frequencyAllows() || isDismissedByLimit()) return;

	if (closeButton) closeButton.addEventListener('click', function () { closePopup('close_button'); });
	if (scrim && config.closeOnOverlay) scrim.addEventListener('click', function () { closePopup('overlay_close'); });
	if (root) {
		root.addEventListener('click', function (event) {
			var close = event.target.closest('[data-dh-popup-close]');
			if (close) { closePopup('close_button'); return; }
			var action = event.target.closest('a[href], button');
			if (!action) return;
			var cta = action.matches('[data-popup-cta], [data-popup-close], .dh-popup-cta');
			record(cta ? 'cta_click' : 'button_click');
			if (action.matches('[data-popup-close]')) closePopup();
		}, true);
	}

	document.addEventListener('keydown', function (event) {
		if (!opened || closed) return;
		if (event.key === 'Escape' && config.closeOnEscape) { closePopup('escape_close'); return; }
		if (event.key !== 'Tab') return;
		var focusables = visibleFocusables();
		if (!focusables.length) { event.preventDefault(); surface.focus(); return; }
		var first = focusables[0];
		var last = focusables[focusables.length - 1];
		if (event.shiftKey && (document.activeElement === first || document.activeElement === surface)) { event.preventDefault(); last.focus(); }
		else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
	});

	switch (config.trigger) {
		case 'immediate':
			window.setTimeout(openPopup, 250);
			break;
		case 'delay':
			window.setTimeout(openPopup, Math.max(1, parseInt(config.triggerValue, 10) || 1) * 1000);
			break;
		case 'scroll':
			var scrollHandler = function () {
				var page = document.documentElement;
				var maxScroll = page.scrollHeight - window.innerHeight;
				var percent = maxScroll > 0 ? (window.scrollY / maxScroll) * 100 : 0;
				if (maxScroll <= 0 || percent >= (parseInt(config.triggerValue, 10) || 50)) { window.removeEventListener('scroll', scrollHandler); openPopup(); }
			};
			window.addEventListener('scroll', scrollHandler, { passive: true });
			scrollHandler();
			break;
		case 'exit_intent':
			if (window.matchMedia && window.matchMedia('(pointer: fine)').matches) {
				document.addEventListener('mouseout', function (event) { if (event.clientY <= 0 && !event.relatedTarget) openPopup(); }, { once: true });
			}
			break;
		case 'click':
			if (config.triggerSelector) {
				var clickTrigger = function (event) {
					if (!(event.target instanceof Element)) return;
					try {
						if (event.target.closest(config.triggerSelector)) {
							document.removeEventListener('click', clickTrigger, true);
							openPopup();
						}
					} catch (error) { document.removeEventListener('click', clickTrigger, true); /* Invalid selectors cannot break the page. */ }
				};
				document.addEventListener('click', clickTrigger, true);
			}
			break;
	}
}());
