(function () {
	'use strict';

	var roots = Array.prototype.slice.call(document.querySelectorAll('[data-dh-select]'));
	var categoryStrips = Array.prototype.slice.call(document.querySelectorAll('[data-dh-category-strip]'));
	var categoryStripObservers = [];
	var archiveSearch = document.querySelector('[data-dh-archive-search]');
	var clearSearch = document.querySelector('[data-dh-search-clear]');
	var archiveList = document.querySelector('.dh-opportunity-list');
	var archiveCount = document.querySelector('[data-dh-results-count]');
	var loadMore = document.querySelector('[data-dh-load-more]');
	if (!roots.length && !categoryStrips.length && !archiveSearch && !loadMore) return;

	if (archiveSearch && clearSearch) {
		function updateClearSearch() { clearSearch.hidden = !archiveSearch.value.trim(); }
		archiveSearch.addEventListener('input', updateClearSearch);
		clearSearch.addEventListener('click', function () {
			archiveSearch.value = '';
			updateClearSearch();
			var form = archiveSearch.closest('form');
			if (!form) return;
			if (typeof form.requestSubmit === 'function') form.requestSubmit();
			else form.submit();
		});
		updateClearSearch();
	}

	if (loadMore && archiveList) {
		loadMore.addEventListener('click', async function (event) {
			event.preventDefault();
			if (loadMore.getAttribute('aria-busy') === 'true') return;
			var nextUrl = loadMore.href;
			var label = loadMore.querySelector('[data-dh-load-more-label]');
			loadMore.setAttribute('aria-busy', 'true');
			loadMore.classList.add('is-loading');
			if (label) label.textContent = 'Loading…';
			try {
				var response = await fetch(nextUrl, { credentials: 'same-origin', headers: { 'Accept': 'text/html' } });
				if (!response.ok) throw new Error('Could not load more listings.');
				var page = new DOMParser().parseFromString(await response.text(), 'text/html');
				var cards = Array.prototype.slice.call(page.querySelectorAll('.dh-opportunity-list > .dh-opportunity-card'));
				if (!cards.length) throw new Error('The next listing page was empty.');
				cards.forEach(function (card) { archiveList.appendChild(document.importNode(card, true)); });
				if (archiveCount) {
					var loaded = Number(archiveCount.dataset.loaded || 0) + cards.length;
					var total = Number(archiveCount.dataset.total || loaded);
					archiveCount.dataset.loaded = String(loaded);
					archiveCount.textContent = 'Showing ' + loaded.toLocaleString() + ' of ' + total.toLocaleString() + ' listings';
				}
				var nextButton = page.querySelector('[data-dh-load-more]');
				if (nextButton) {
					loadMore.href = nextButton.href;
				} else {
					var wrapper = loadMore.closest('.dh-opportunity-load-more-wrap');
					if (wrapper) wrapper.remove();
				}
			} catch (_) {
				window.location.assign(nextUrl);
			} finally {
				if (loadMore.isConnected) {
					loadMore.removeAttribute('aria-busy');
					loadMore.classList.remove('is-loading');
					if (label && label.isConnected) label.textContent = 'Show more listings';
				}
			}
		});
	}

	categoryStrips.forEach(function (strip) {
		var list = strip.querySelector('[data-dh-category-list]');
		var previous = strip.querySelector('[data-dh-category-scroll="-1"]');
		var next = strip.querySelector('[data-dh-category-scroll="1"]');
		if (!list || !previous || !next) return;
		strip.classList.add('has-scroll-controls');

		function updateScrollControls() {
			var maxScroll = Math.max(0, list.scrollWidth - list.clientWidth);
			previous.hidden = list.scrollLeft <= 4;
			next.hidden = list.scrollLeft >= maxScroll - 4;
		}

		previous.addEventListener('click', function () { list.scrollBy({ left: -220, behavior: 'smooth' }); });
		next.addEventListener('click', function () { list.scrollBy({ left: 220, behavior: 'smooth' }); });
		list.addEventListener('scroll', updateScrollControls, { passive: true });
		window.addEventListener('resize', updateScrollControls);
		if ('ResizeObserver' in window) {
			var observer = new ResizeObserver(updateScrollControls);
			observer.observe(list);
			categoryStripObservers.push(observer);
		}
		updateScrollControls();
	});

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
		var typeahead = '';
		var typeaheadTimer = 0;
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
			if (!options.length) return;
			var current = options.indexOf(document.activeElement);
			var next = current;
			if (event.key === 'ArrowDown') next = Math.min(options.length - 1, current + 1);
			else if (event.key === 'ArrowUp') next = Math.max(0, current - 1);
			else if (event.key === 'Home') next = 0;
			else if (event.key === 'End') next = options.length - 1;
			else if (event.key === 'Enter' || event.key === ' ') {
				var activeOption = document.activeElement.closest('[role="option"]');
				if (activeOption && menu.contains(activeOption)) {
					event.preventDefault();
					selectOption(root, activeOption);
				}
				return;
			} else if (event.key === 'Escape') { event.preventDefault(); close(root, true); return; }
			else if (event.key === 'Tab') { close(root, false); return; }
			else if (event.key.length === 1 && event.key !== ' ' && !event.altKey && !event.ctrlKey && !event.metaKey) {
				var character = event.key.toLocaleLowerCase();
				var query = typeahead.length === 1 && typeahead === character ? character : typeahead + character;
				var findMatch = function (search) {
					for (var offset = 1; offset <= options.length; offset++) {
						var index = (Math.max(-1, current) + offset) % options.length;
						var text = options[index].textContent.trim().toLocaleLowerCase();
						if (text.indexOf(search) === 0) return options[index];
					}
					return null;
				};
				var match = findMatch(query);
				if (!match && query !== character) {
					query = character;
					match = findMatch(query);
				}
				typeahead = query;
				window.clearTimeout(typeaheadTimer);
				typeaheadTimer = window.setTimeout(function () { typeahead = ''; }, 700);
				if (match) {
					event.preventDefault();
					match.focus();
				}
				return;
			} else return;
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
