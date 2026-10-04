(function () {
	'use strict';

	var roots = Array.prototype.slice.call(document.querySelectorAll('[data-dh-select]'));
	if (!roots.length) return;

	function close(root, restoreFocus) {
		var trigger = root.querySelector('[data-dh-select-trigger]');
		var menu = root.querySelector('[data-dh-select-menu]');
		if (!trigger || !menu) return;
		menu.hidden = true;
		trigger.setAttribute('aria-expanded', 'false');
		if (restoreFocus) trigger.focus();
	}

	function open(root, focusSelected) {
		var trigger = root.querySelector('[data-dh-select-trigger]');
		var menu = root.querySelector('[data-dh-select-menu]');
		if (!trigger || !menu) return;
		roots.forEach(function (other) { if (other !== root) close(other, false); });
		menu.hidden = false;
		trigger.setAttribute('aria-expanded', 'true');
		if (focusSelected) {
			var selected = menu.querySelector('[aria-selected="true"]') || menu.querySelector('[role="option"]');
			if (selected) selected.focus();
		}
	}

	function selectOption(root, option) {
		var input = root.querySelector('[data-dh-select-value]');
		var trigger = root.querySelector('[data-dh-select-trigger]');
		var label = root.querySelector('[data-dh-select-label]');
		var menu = root.querySelector('[data-dh-select-menu]');
		if (!input || !trigger || !label || !menu || !option) return;
		input.value = option.getAttribute('data-value') || '';
		label.textContent = option.querySelector('span').textContent;
		menu.querySelectorAll('[role="option"]').forEach(function (item) {
			var selected = item === option;
			item.setAttribute('aria-selected', selected ? 'true' : 'false');
			item.classList.toggle('is-selected', selected);
		});
		close(root, true);
	}

	roots.forEach(function (root) {
		var trigger = root.querySelector('[data-dh-select-trigger]');
		var menu = root.querySelector('[data-dh-select-menu]');
		if (!trigger || !menu) return;

		trigger.addEventListener('click', function () {
			if (trigger.getAttribute('aria-expanded') === 'true') close(root, false);
			else open(root, true);
		});

		trigger.addEventListener('keydown', function (event) {
			if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				open(root, true);
			}
		});

		menu.addEventListener('click', function (event) {
			var option = event.target.closest('[role="option"]');
			if (option) selectOption(root, option);
		});

		menu.addEventListener('keydown', function (event) {
			var options = Array.prototype.slice.call(menu.querySelectorAll('[role="option"]'));
			var current = options.indexOf(document.activeElement);
			var next = current;
			if (event.key === 'ArrowDown') next = Math.min(options.length - 1, current + 1);
			else if (event.key === 'ArrowUp') next = Math.max(0, current - 1);
			else if (event.key === 'Home') next = 0;
			else if (event.key === 'End') next = options.length - 1;
			else if (event.key === 'Escape') { event.preventDefault(); close(root, true); return; }
			else if (event.key === 'Tab') { close(root, false); return; }
			else return;
			event.preventDefault();
			if (options[next]) options[next].focus();
		});
	});

	document.addEventListener('pointerdown', function (event) {
		roots.forEach(function (root) { if (!root.contains(event.target)) close(root, false); });
	});
	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') roots.forEach(function (root) { close(root, false); });
	});
}());
