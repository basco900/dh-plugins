(function () {
	'use strict';

	var faceType = document.querySelector('[data-dh-font-face-type]');
	var variableFields = document.querySelector('[data-dh-font-variable-fields]');
	var staticFields = document.querySelector('[data-dh-font-static-fields]');
	if (faceType && variableFields && staticFields) {
		function syncWeightFields() {
			var variable = faceType.value === 'variable';
			variableFields.hidden = !variable;
			staticFields.hidden = variable;
		}
		faceType.addEventListener('change', syncWeightFields);
		syncWeightFields();
	}

	document.querySelectorAll('[data-dh-logo-select]').forEach(function (button) {
		var variant = button.getAttribute('data-dh-logo-select') || 'header';
		var inputId = 'footer' === variant ? 'dh-navbar-footer_logo_url' : 'dh-navbar-logo_url';
		var input = document.getElementById(inputId);
		var preview = document.querySelector('[data-dh-logo-preview="' + variant + '"]');
		var clear = document.querySelector('[data-dh-logo-clear="' + variant + '"]');
		var frame = null;
		function showPreview(url) {
			if (!preview) return;
			preview.textContent = '';
			if (!url) {
				var note = document.createElement('span');
				note.textContent = 'footer' === variant ? 'The footer will use the navbar or WordPress logo.' : 'The navbar will use the site logo, or show the site name without an icon.';
				preview.appendChild(note);
				return;
			}
			var image = document.createElement('img');
			image.src = url;
			image.alt = 'Logo preview';
			preview.appendChild(image);
		}
		if (input) input.addEventListener('change', function () { showPreview(input.value.trim()); });
		if (clear && input) {
			clear.addEventListener('click', function () {
				input.value = '';
				input.dispatchEvent(new Event('change', { bubbles: true }));
			});
		}
		if (input && window.wp && window.wp.media) {
			button.addEventListener('click', function () {
				if (!frame) {
					frame = window.wp.media({ title: 'Choose a site logo', button: { text: 'Use this logo' }, library: { type: 'image' }, multiple: false });
					frame.on('select', function () {
						var attachment = frame.state().get('selection').first().toJSON();
						input.value = attachment.url || '';
						input.dispatchEvent(new Event('change', { bubbles: true }));
					});
				}
				frame.open();
			});
		}
	});

	var repeater = document.querySelector('[data-dh-nav-repeater]');
	if (repeater) {
	var list = repeater.querySelector('[data-dh-nav-items]');
	var itemTemplate = repeater.querySelector('[data-dh-nav-item-template]');
	if (list && itemTemplate) {

	function renameNames(container, itemIndex, childIndex) {
		container.querySelectorAll('[name]').forEach(function (field) {
			var name = field.getAttribute('name') || '';
			name = name.replace(/(manual_nav_items\[)(?:\d+|__ITEM__)(\])/, '$1' + itemIndex + '$2');
			if (childIndex !== null) {
				name = name.replace(/(\[children\]\[)(?:\d+|__CHILD__)(\])/, '$1' + childIndex + '$2');
			}
			field.setAttribute('name', name);
		});
	}

	function updateType(card) {
		var select = card.querySelector('[data-dh-nav-type]');
		var children = card.querySelector('[data-dh-nav-children-panel]');
		if (select && children) children.hidden = select.value !== 'dropdown';
		if (select) card.querySelectorAll('[data-dh-nav-link-field]').forEach(function (field) { field.hidden = select.value === 'text'; });
	}

	function renumber() {
		Array.prototype.slice.call(list.querySelectorAll('[data-dh-nav-item]')).forEach(function (card, itemIndex) {
			renameNames(card, itemIndex, null);
			var number = card.querySelector('[data-dh-nav-item-number]');
			if (number) number.textContent = String(itemIndex + 1);
			card.querySelectorAll('[data-dh-nav-child]').forEach(function (child, childIndex) {
				renameNames(child, itemIndex, childIndex);
			});
			updateType(card);
		});
	}

	function addItem() {
		if (list.querySelectorAll('[data-dh-nav-item]').length >= 12) return;
		var holder = document.createElement('template');
		holder.innerHTML = itemTemplate.innerHTML.replace(/__ITEM__/g, String(list.querySelectorAll('[data-dh-nav-item]').length)).trim();
		var card = holder.content.firstElementChild;
		if (!card) return;
		list.appendChild(card);
		renumber();
		var firstField = card.querySelector('input[type="text"]');
		if (firstField) firstField.focus();
	}

	function addChild(card) {
		var children = card.querySelector('[data-dh-nav-children]');
		var template = card.querySelector('[data-dh-nav-child-template]');
		if (!children || !template || children.querySelectorAll('[data-dh-nav-child]').length >= 12) return;
		var itemIndex = Array.prototype.indexOf.call(list.querySelectorAll('[data-dh-nav-item]'), card);
		var childIndex = children.querySelectorAll('[data-dh-nav-child]').length;
		var holder = document.createElement('template');
		holder.innerHTML = template.innerHTML.replace(/__ITEM__/g, String(itemIndex)).replace(/__CHILD__/g, String(childIndex)).trim();
		var child = holder.content.firstElementChild;
		if (!child) return;
		children.appendChild(child);
		renumber();
		var field = child.querySelector('input[type="text"]');
		if (field) field.focus();
	}

	repeater.addEventListener('click', function (event) {
		var add = event.target.closest('[data-dh-nav-add]');
		if (add) { addItem(); return; }
		var card = event.target.closest('[data-dh-nav-item]');
		if (!card) return;
		if (event.target.closest('[data-dh-nav-add-child]')) { addChild(card); return; }
		var child = event.target.closest('[data-dh-nav-child]');
		if (child && event.target.closest('[data-dh-nav-child-up]') && child.previousElementSibling) { child.parentNode.insertBefore(child, child.previousElementSibling); renumber(); return; }
		if (child && event.target.closest('[data-dh-nav-child-down]') && child.nextElementSibling) { child.parentNode.insertBefore(child.nextElementSibling, child); renumber(); return; }
		if (child && event.target.closest('[data-dh-nav-remove-child]')) { child.remove(); renumber(); return; }
		if (event.target.closest('[data-dh-nav-remove]')) { card.remove(); renumber(); return; }
		if (event.target.closest('[data-dh-nav-up]') && card.previousElementSibling) { list.insertBefore(card, card.previousElementSibling); renumber(); return; }
		if (event.target.closest('[data-dh-nav-down]') && card.nextElementSibling) { list.insertBefore(card.nextElementSibling, card); renumber(); }
	});

	repeater.addEventListener('change', function (event) {
		if (event.target.matches('[data-dh-nav-type]')) updateType(event.target.closest('[data-dh-nav-item]'));
	});

	renumber();
	}
	}


	function initFooterColumns() {
		var root = document.querySelector('[data-dh-footer-columns]');
		if (!root) return;
		var list = root.querySelector('[data-dh-footer-column-list]');
		var template = root.querySelector('[data-dh-footer-column-template]');
		if (!list || !template) return;

		function updateItemType(item) {
			var select = item.querySelector('[data-dh-footer-item-type]');
			var type = select ? select.value : 'link';
			var linkFields = item.querySelector('[data-dh-footer-link-fields]');
			var textFields = item.querySelector('[data-dh-footer-text-fields]');
			var logoFields = item.querySelector('[data-dh-footer-logo-fields]');
			var newTab = item.querySelector('[data-dh-footer-new-tab]');
			if (linkFields) linkFields.hidden = type !== 'link' && type !== 'social';
			if (textFields) textFields.hidden = type !== 'text';
			if (logoFields) logoFields.hidden = type !== 'logo';
			if (newTab) newTab.hidden = type === 'divider' || type === 'text';
		}

		function renumber() {
			Array.prototype.slice.call(list.querySelectorAll('[data-dh-footer-column]')).forEach(function (column, columnIndex) {
				column.querySelectorAll('[name]').forEach(function (field) {
					field.name = field.name.replace(/(footer_columns\[)(?:\d+|__COLUMN__)(\])/, '$1' + columnIndex + '$2');
				});
				var number = column.querySelector('[data-dh-footer-column-number]');
				if (number) number.textContent = String(columnIndex + 1);
				Array.prototype.slice.call(column.querySelectorAll('[data-dh-footer-item]')).forEach(function (item, itemIndex) {
					item.querySelectorAll('[name]').forEach(function (field) {
						field.name = field.name.replace(/(footer_columns\[)\d+(\]\[items\]\[)(?:\d+|__ITEM__)(\])/, '$1' + columnIndex + '$2' + itemIndex + '$3');
					});
					updateItemType(item);
				});
			});
		}

		function addColumn() {
			if (list.querySelectorAll('[data-dh-footer-column]').length >= 8) return;
			var index = list.querySelectorAll('[data-dh-footer-column]').length;
			var holder = document.createElement('template');
			holder.innerHTML = template.innerHTML.replace(/__COLUMN__/g, String(index)).trim();
			if (!holder.content.firstElementChild) return;
			list.appendChild(holder.content.firstElementChild);
			renumber();
		}

		function addItem(column) {
			var items = column.querySelector('[data-dh-footer-item-list]');
			var itemTemplate = column.querySelector('[data-dh-footer-item-template]');
			if (!items || !itemTemplate || items.querySelectorAll('[data-dh-footer-item]').length >= 24) return;
			var columnIndex = Array.prototype.indexOf.call(list.querySelectorAll('[data-dh-footer-column]'), column);
			var itemIndex = items.querySelectorAll('[data-dh-footer-item]').length;
			var holder = document.createElement('template');
			holder.innerHTML = itemTemplate.innerHTML.replace(/__COLUMN__/g, String(columnIndex)).replace(/__ITEM__/g, String(itemIndex)).trim();
			if (!holder.content.firstElementChild) return;
			items.appendChild(holder.content.firstElementChild);
			renumber();
		}

		root.addEventListener('change', function (event) {
			var item = event.target.closest('[data-dh-footer-item]');
			if (item && event.target.matches('[data-dh-footer-item-type]')) updateItemType(item);
		});
		root.addEventListener('click', function (event) {
			if (event.target.closest('[data-dh-footer-add-column]')) { addColumn(); return; }
			var column = event.target.closest('[data-dh-footer-column]');
			if (!column) return;
			if (event.target.closest('[data-dh-footer-add-item]')) { addItem(column); return; }
			var item = event.target.closest('[data-dh-footer-item]');
			if (item && event.target.closest('[data-dh-footer-remove-item]')) { item.remove(); renumber(); return; }
			if (item && event.target.closest('[data-dh-footer-item-up]') && item.previousElementSibling) { item.parentNode.insertBefore(item, item.previousElementSibling); renumber(); return; }
			if (item && event.target.closest('[data-dh-footer-item-down]') && item.nextElementSibling) { item.parentNode.insertBefore(item.nextElementSibling, item); renumber(); return; }
			if (item && event.target.closest('[data-dh-footer-image-select]')) {
				if (!window.wp || !window.wp.media) return;
				var input = item.querySelector('[data-dh-footer-image-url]');
				if (!input) return;
				var frame = window.wp.media({ title: 'Choose footer image', button: { text: 'Use this image' }, library: { type: 'image' }, multiple: false });
				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					input.value = attachment.url || '';
				});
				frame.open();
				return;
			}
			if (event.target.closest('[data-dh-footer-remove-column]')) { column.remove(); renumber(); return; }
			if (event.target.closest('[data-dh-footer-up]') && column.previousElementSibling) { list.insertBefore(column, column.previousElementSibling); renumber(); return; }
			if (event.target.closest('[data-dh-footer-down]') && column.nextElementSibling) { list.insertBefore(column.nextElementSibling, column); renumber(); }
		});
		renumber();
	}

	function initFooterSocial() {
		var root = document.querySelector('[data-dh-footer-social]');
		if (!root) return;
		var list = root.querySelector('[data-dh-footer-social-list]');
		var template = root.querySelector('[data-dh-footer-social-template]');
		if (!list || !template) return;

		function renumber() {
			Array.prototype.slice.call(list.querySelectorAll('[data-dh-footer-social-item]')).forEach(function (item, index) {
				item.querySelectorAll('[name]').forEach(function (field) {
					field.name = field.name.replace(/(footer_social_links\[)(?:\d+|__SOCIAL__)(\])/, '$1' + index + '$2');
				});
			});
		}

		root.addEventListener('click', function (event) {
			if (event.target.closest('[data-dh-footer-add-social]')) {
				if (list.querySelectorAll('[data-dh-footer-social-item]').length >= 8) return;
				var holder = document.createElement('template');
				holder.innerHTML = template.innerHTML.replace(/__SOCIAL__/g, String(list.querySelectorAll('[data-dh-footer-social-item]').length)).trim();
				if (holder.content.firstElementChild) list.appendChild(holder.content.firstElementChild);
				renumber();
				return;
			}
			var item = event.target.closest('[data-dh-footer-social-item]');
			if (!item) return;
			if (event.target.closest('[data-dh-footer-remove-social]')) { item.remove(); renumber(); return; }
			if (event.target.closest('[data-dh-footer-social-up]') && item.previousElementSibling) { list.insertBefore(item, item.previousElementSibling); renumber(); return; }
			if (event.target.closest('[data-dh-footer-social-down]') && item.nextElementSibling) { list.insertBefore(item.nextElementSibling, item); renumber(); }
		});
		renumber();
	}

	function initFooterLegal() {
		var root = document.querySelector('[data-dh-footer-legal]');
		if (!root) return;
		var list = root.querySelector('[data-dh-footer-legal-list]');
		var template = root.querySelector('[data-dh-footer-legal-template]');
		if (!list || !template) return;

		function renumber() {
			Array.prototype.slice.call(list.querySelectorAll('[data-dh-footer-legal-item]')).forEach(function (item, index) {
				item.querySelectorAll('[name]').forEach(function (field) {
					field.name = field.name.replace(/(footer_legal_links\[)(?:\d+|__LEGAL__)(\])/, '$1' + index + '$2');
				});
			});
		}

		root.addEventListener('click', function (event) {
			if (event.target.closest('[data-dh-footer-add-legal]')) {
				if (list.querySelectorAll('[data-dh-footer-legal-item]').length >= 20) return;
				var holder = document.createElement('template');
				holder.innerHTML = template.innerHTML.replace(/__LEGAL__/g, String(list.querySelectorAll('[data-dh-footer-legal-item]').length)).trim();
				if (holder.content.firstElementChild) list.appendChild(holder.content.firstElementChild);
				renumber();
				return;
			}
			var item = event.target.closest('[data-dh-footer-legal-item]');
			if (!item) return;
			if (event.target.closest('[data-dh-footer-remove-legal]')) { item.remove(); renumber(); return; }
			if (event.target.closest('[data-dh-footer-legal-up]') && item.previousElementSibling) { list.insertBefore(item, item.previousElementSibling); renumber(); return; }
			if (event.target.closest('[data-dh-footer-legal-down]') && item.nextElementSibling) { list.insertBefore(item.nextElementSibling, item); renumber(); }
		});
		renumber();
	}

	function initPopupEditor() {
		var editor = document.querySelector('[data-dh-popup-editor]');
		if (!editor) return;
		var tabs = Array.prototype.slice.call(editor.querySelectorAll('[data-dh-popup-tab]'));
		var panels = Array.prototype.slice.call(editor.querySelectorAll('[data-dh-popup-panel]'));
		function activatePanel(name) {
			tabs.forEach(function (tab) {
				var active = tab.getAttribute('data-dh-popup-tab') === name;
				tab.classList.toggle('is-active', active);
				tab.setAttribute('aria-selected', active ? 'true' : 'false');
				tab.setAttribute('tabindex', active ? '0' : '-1');
			});
			panels.forEach(function (panel) {
				var active = panel.getAttribute('data-dh-popup-panel') === name;
				panel.hidden = !active;
				panel.classList.toggle('is-active', active);
			});
		}
		tabs.forEach(function (tab, index) {
			tab.setAttribute('role', 'tab');
			tab.setAttribute('aria-selected', tab.classList.contains('is-active') ? 'true' : 'false');
			tab.addEventListener('click', function () { activatePanel(tab.getAttribute('data-dh-popup-tab')); });
			tab.addEventListener('keydown', function (event) {
				if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
				event.preventDefault();
				var step = event.key === 'ArrowRight' ? 1 : -1;
				var next = tabs[(index + step + tabs.length) % tabs.length];
				next.focus();
				next.click();
			});
		});
		panels.forEach(function (panel) { panel.setAttribute('role', 'tabpanel'); });

		var trigger = editor.querySelector('[data-dh-popup-trigger]');
		var triggerValue = editor.querySelector('[data-dh-popup-trigger-value]');
		var triggerSelector = editor.querySelector('[data-dh-popup-trigger-selector]');
		function syncTriggerFields() {
			if (!trigger) return;
			if (triggerValue) triggerValue.hidden = trigger.value !== 'delay' && trigger.value !== 'scroll';
			if (triggerSelector) triggerSelector.hidden = trigger.value !== 'click';
		}
		if (trigger) { trigger.addEventListener('change', syncTriggerFields); syncTriggerFields(); }

		var preview = document.querySelector('[data-dh-popup-preview]');
		var previewContent = document.querySelector('[data-dh-popup-preview-content]');
		var previewSurface = document.querySelector('[data-dh-popup-preview-surface]');
		var previewRoot = document.querySelector('[data-dh-popup-preview-root]');
		var previewLayer = document.querySelector('.dh-popup-preview');
		function closePreview() {
			if (!previewLayer) return;
			previewLayer.hidden = true;
			document.body.classList.remove('dh-popup-preview-open');
			if (preview) preview.focus();
		}
		if (preview && previewLayer && previewContent && previewSurface && previewRoot) {
			preview.addEventListener('click', function () {
				var html = editor.querySelector('[data-dh-popup-html]');
				var css = editor.querySelector('[data-dh-popup-css]');
				var template = document.createElement('template');
				template.innerHTML = html ? html.value : '';
				template.content.querySelectorAll('script, iframe, object, embed').forEach(function (node) { node.remove(); });
				template.content.querySelectorAll('*').forEach(function (node) {
					Array.prototype.slice.call(node.attributes).forEach(function (attribute) {
						if (/^on/i.test(attribute.name) || ((attribute.name === 'href' || attribute.name === 'src') && /^\s*javascript:/i.test(attribute.value))) node.removeAttribute(attribute.name);
					});
				});
				previewRoot.replaceChildren(template.content);
				var style = previewContent.querySelector('style[data-dh-popup-preview-style]');
				if (!style) {
					style = document.createElement('style');
					style.setAttribute('data-dh-popup-preview-style', '');
					previewContent.appendChild(style);
				}
				style.textContent = css ? css.value : '';
				['background_color', 'text_color', 'accent_color'].forEach(function (key) {
					var field = editor.querySelector('[name="' + key + '"]');
					if (field) previewContent.style.setProperty('--dh-popup-' + key.replace('_color', ''), field.value);
				});
				var width = editor.querySelector('[name="width"]');
				if (width) previewContent.style.setProperty('--dh-popup-width', Math.max(280, Math.min(960, parseInt(width.value, 10) || 520)) + 'px');
				previewLayer.hidden = false;
				document.body.classList.add('dh-popup-preview-open');
			});
			previewLayer.querySelectorAll('[data-dh-popup-preview-close]').forEach(function (button) { button.addEventListener('click', closePreview); });
			document.addEventListener('keydown', function (event) {
				if (event.key === 'Escape' && !previewLayer.hidden) closePreview();
			});
		}
	}

	initFooterColumns();
	initFooterSocial();
	initFooterLegal();
	initPopupEditor();
}());
