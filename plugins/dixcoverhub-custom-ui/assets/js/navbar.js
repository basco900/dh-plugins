(function () {
	'use strict';

	var root = document.querySelector('[data-dh-navbar]');
	if (!root) return;

	var mobileToggle = root.querySelector('[data-dh-mobile-toggle]');
	var drawer = root.querySelector('[data-dh-drawer]');
	var closeButtons = root.querySelectorAll('[data-dh-close]');
	var accordions = root.querySelectorAll('[data-dh-accordion]');
	var priorFocus = null;

	function closeDesktopMenus(except) {
		root.querySelectorAll('[data-dh-dropdown][open]').forEach(function (menu) {
			if (menu !== except) menu.removeAttribute('open');
		});
	}

	root.querySelectorAll('[data-dh-dropdown]').forEach(function (menu) {
		menu.addEventListener('toggle', function () {
			if (menu.open) closeDesktopMenus(menu);
		});
	});

	document.addEventListener('pointerdown', function (event) {
		if (!root.contains(event.target)) closeDesktopMenus(null);
	});

	function setDrawer(open) {
		if (!drawer || !mobileToggle) return;
		if (open) {
			priorFocus = document.activeElement;
			drawer.hidden = false;
			root.querySelectorAll('[data-dh-close]').forEach(function (button) {
				button.hidden = false;
			});
			mobileToggle.setAttribute('aria-expanded', 'true');
			mobileToggle.setAttribute('aria-label', 'Close navigation menu');
			document.body.classList.add('dh-navbar-mobile-open');
			var firstLink = drawer.querySelector('a, button');
			if (firstLink) firstLink.focus();
		} else {
			drawer.hidden = true;
			root.querySelectorAll('[data-dh-close]').forEach(function (button) {
				button.hidden = true;
			});
			mobileToggle.setAttribute('aria-expanded', 'false');
			mobileToggle.setAttribute('aria-label', 'Open navigation menu');
			document.body.classList.remove('dh-navbar-mobile-open');
			if (priorFocus && typeof priorFocus.focus === 'function') priorFocus.focus();
		}
	}

	if (mobileToggle) {
		mobileToggle.addEventListener('click', function () {
			setDrawer(drawer.hidden);
		});
	}

	closeButtons.forEach(function (button) {
		button.hidden = true;
		button.addEventListener('click', function () {
			setDrawer(false);
		});
	});

	accordions.forEach(function (button) {
		button.addEventListener('click', function () {
			var panel = document.getElementById(button.getAttribute('aria-controls'));
			if (!panel) return;
			var open = button.getAttribute('aria-expanded') !== 'true';
			button.setAttribute('aria-expanded', open ? 'true' : 'false');
			panel.hidden = !open;
		});
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Tab' && drawer && !drawer.hidden) {
			var focusable = drawer.querySelectorAll('a[href], button:not([disabled])');
			if (focusable.length) {
				var first = focusable[0];
				var last = focusable[focusable.length - 1];
				if (event.shiftKey && document.activeElement === first) {
					event.preventDefault();
					last.focus();
				} else if (!event.shiftKey && document.activeElement === last) {
					event.preventDefault();
					first.focus();
				}
			}
			return;
		}

		if (event.key !== 'Escape') return;
		if (drawer && !drawer.hidden) {
			setDrawer(false);
			return;
		}
		var openMenu = root.querySelector('[data-dh-dropdown][open]');
		if (openMenu) {
			openMenu.removeAttribute('open');
			var summary = openMenu.querySelector('summary');
			if (summary) summary.focus();
		}
	});

	root.addEventListener('click', function (event) {
		if (drawer && !drawer.hidden && event.target.closest('a')) setDrawer(false);
	});
})();
