(function () {
  'use strict';

  var app = document.querySelector('.dh-ai-app');
  var optionsByTaxonomy = window.DixcoverHubTaxonomyOptions && window.DixcoverHubTaxonomyOptions.taxonomies;
  if (!app || !optionsByTaxonomy) return;

  var definitions = [
    { id: 'dh-ai-types', key: 'typeNames', title: 'Opportunity types', hint: 'Search existing types or add a new one.' },
    { id: 'dh-ai-levels', key: 'levelNames', title: 'Opportunity levels', hint: 'Search existing levels or add a new one.' },
    { id: 'dh-ai-modes', key: 'modeNames', title: 'Modes', hint: 'Search existing modes or add a new one.' },
    { id: 'dh-ai-locations', key: 'locationNames', title: 'Location filters', hint: 'Search existing locations or add a new one.' },
    { id: 'dh-ai-tags', key: 'tagNames', title: 'Tags', hint: 'Search existing tags or add a new one.' }
  ];
  var pickers = [];
  var selectPickers = [];

  function closeOtherPickers(active) {
    pickers.concat(selectPickers).forEach(function (picker) {
      if (picker !== active) picker.close(false);
    });
  }

  function normalize(value) {
    return String(value || '').trim().replace(/\s+/g, ' ').toLocaleLowerCase();
  }

  function parseValue(value) {
    var seen = Object.create(null);
    return String(value || '').split(',').map(function (part) {
      return part.trim().replace(/\s+/g, ' ');
    }).filter(function (name) {
      var key = normalize(name);
      if (!key || seen[key]) return false;
      seen[key] = true;
      return true;
    }).slice(0, 20);
  }

  function element(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text) node.textContent = text;
    return node;
  }

  function init(definition) {
    var source = document.getElementById(definition.id);
    if (!source || source.dataset.dhTaxonomyReady) return;

    var choices = Array.isArray(optionsByTaxonomy[definition.key]) ? optionsByTaxonomy[definition.key].slice() : [];
    choices.sort(function (a, b) {
      return String(a.parent || '').localeCompare(String(b.parent || '')) || String(a.name || '').localeCompare(String(b.name || ''));
    });

    var root = element('div', 'dh-ai-taxonomy-picker');
    var selectedHost = element('div', 'dh-ai-taxonomy-selected');
    var control = element('div', 'dh-ai-taxonomy-control');
    var trigger = element('button', 'dh-ai-taxonomy-trigger');
    var searchPanel = element('div', 'dh-ai-taxonomy-panel');
    var search = element('input', 'dh-ai-taxonomy-search');
    var list = element('div', 'dh-ai-taxonomy-options');
    var addCustom = element('button', 'dh-ai-taxonomy-add');
    var empty = element('p', 'dh-ai-taxonomy-empty', 'No matching terms. Search or add a term.');
    var selected = parseValue(source.value);
    var open = false;

    source.dataset.dhTaxonomyReady = '1';
    source.classList.add('dh-ai-taxonomy-source');
    source.setAttribute('aria-hidden', 'true');
    source.tabIndex = -1;
    source.autocomplete = 'off';
    var details = source.closest('.dh-ai-details');
    if (details) details.classList.add('has-taxonomy-picker');

    trigger.type = 'button';
    trigger.id = definition.id + '-choose';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', definition.id + '-options');
    trigger.append(element('span', 'dh-ai-taxonomy-plus', '+'), element('span', '', 'Choose ' + definition.title.toLowerCase()));

    search.type = 'search';
    search.placeholder = 'Search ' + definition.title.toLowerCase() + '…';
    search.setAttribute('aria-label', 'Search ' + definition.title.toLowerCase());
    list.id = definition.id + '-options';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', definition.title);
    list.setAttribute('aria-multiselectable', 'true');
    addCustom.type = 'button';
    addCustom.hidden = true;
    empty.hidden = true;

    searchPanel.hidden = true;
    control.append(trigger);
    searchPanel.append(search, addCustom, list, empty);
    root.append(selectedHost, control, searchPanel);
    source.insertAdjacentElement('afterend', root);

    var label = app.querySelector('label[for="' + definition.id + '"]');
    if (label) {
      label.htmlFor = trigger.id;
      var hint = label.querySelector('small');
      if (hint) hint.textContent = definition.hint;
    }

    function setSourceValue() {
      source.value = selected.join(', ');
      source.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function renderSelected() {
      selectedHost.replaceChildren();
      selected.forEach(function (name) {
        var chip = element('span', 'dh-ai-taxonomy-chip');
        var labelText = element('span', '', name);
        var remove = element('button', 'dh-ai-taxonomy-remove', '×');
        remove.type = 'button';
        remove.setAttribute('aria-label', 'Remove ' + name);
        remove.addEventListener('click', function () {
          selected = selected.filter(function (item) { return normalize(item) !== normalize(name); });
          setSourceValue();
        });
        chip.append(labelText, remove);
        selectedHost.appendChild(chip);
      });
      trigger.setAttribute('aria-label', 'Choose ' + definition.title.toLowerCase() + '. ' + selected.length + ' selected.');
    }

    function close(restoreFocus) {
      if (!open) return;
      open = false;
      searchPanel.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
      search.value = '';
      if (restoreFocus) trigger.focus();
      renderOptions();
    }

    function addTerm(name) {
      name = String(name || '').trim().replace(/\s+/g, ' ');
      if (!name) return;
      var exists = selected.some(function (item) { return normalize(item) === normalize(name); });
      if (exists) {
        close(true);
        return;
      }
      if (selected.length >= 20) {
        empty.textContent = 'A maximum of 20 terms can be selected.';
        empty.hidden = false;
        return;
      }
      selected.push(name);
      empty.textContent = 'No matching terms. Search or add a term.';
      setSourceValue();
      close(true);
    }

    function renderOptions() {
      list.replaceChildren();
      var query = normalize(search.value);
      var available = choices.filter(function (item) {
        var name = String(item.name || '');
        if (selected.some(function (chosen) { return normalize(chosen) === normalize(name); })) return false;
        return !query || normalize(name + ' ' + (item.parent || '')).indexOf(query) !== -1;
      });
      available.forEach(function (item) {
        var option = element('button', 'dh-ai-taxonomy-option');
        option.type = 'button';
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', 'false');
        if (item.parent) option.appendChild(element('small', '', item.parent));
        option.appendChild(element('span', '', String(item.name || '')));
        option.addEventListener('click', function () { addTerm(item.name); });
        list.appendChild(option);
      });

      var exactChoice = choices.some(function (item) { return normalize(item.name) === query; });
      var alreadyChosen = selected.some(function (item) { return normalize(item) === query; });
      addCustom.hidden = !query || exactChoice || alreadyChosen || selected.length >= 20;
      if (!addCustom.hidden) {
        addCustom.textContent = 'Add “' + search.value.trim() + '”';
        addCustom.setAttribute('aria-label', 'Add custom ' + definition.title.toLowerCase() + ' term ' + search.value.trim());
      }
      empty.hidden = available.length > 0 || !query || !addCustom.hidden;
      if (alreadyChosen && query) {
        empty.textContent = 'This term is already selected.';
        empty.hidden = false;
      } else if (selected.length >= 20 && query) {
        empty.textContent = 'A maximum of 20 terms can be selected.';
        empty.hidden = false;
      } else {
        empty.textContent = 'No matching terms. Search or add a term.';
      }
    }

    function show() {
      closeOtherPickers(api);
      open = true;
      searchPanel.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');
      renderOptions();
      search.focus();
    }

    var api = { root: root, close: close };
    pickers.push(api);
    source.addEventListener('input', function () {
      selected = parseValue(source.value);
      renderSelected();
      if (open) renderOptions();
    });
    trigger.addEventListener('click', function () {
      if (open) close(false);
      else show();
    });
    search.addEventListener('input', renderOptions);
    search.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        close(true);
      } else if (event.key === 'ArrowDown') {
        var first = list.querySelector('[role="option"]');
        if (first) { event.preventDefault(); first.focus(); }
      } else if (event.key === 'Enter') {
        var query = normalize(search.value);
        var exact = choices.find(function (item) { return normalize(item.name) === query; });
        if (query) {
          event.preventDefault();
          addTerm(exact ? exact.name : search.value);
        }
      }
    });
    addCustom.addEventListener('click', function () { addTerm(search.value); });
    list.addEventListener('keydown', function (event) {
      var options = Array.prototype.slice.call(list.querySelectorAll('[role="option"]'));
      var index = options.indexOf(document.activeElement);
      var next = index;
      if (event.key === 'ArrowDown') next = Math.min(options.length - 1, index + 1);
      else if (event.key === 'ArrowUp' && index <= 0) { event.preventDefault(); search.focus(); return; }
      else if (event.key === 'ArrowUp') next = Math.max(0, index - 1);
      else if (event.key === 'Home') next = 0;
      else if (event.key === 'End') next = options.length - 1;
      else if (event.key === 'Escape') { event.preventDefault(); close(true); return; }
      else if (event.key === 'Tab') { close(false); return; }
      else return;
      event.preventDefault();
      if (options[next]) options[next].focus();
    });
    renderSelected();
  }

  function initSelect(definition) {
    var source = document.getElementById(definition.id);
    if (!source || source.dataset.dhCustomSelect) return;

    var root = element('div', 'dh-ai-custom-select');
    var trigger = element('button', 'dh-ai-custom-select-trigger');
    var labelText = element('span', 'dh-ai-custom-select-label');
    var panel = element('div', 'dh-ai-custom-select-panel');
    var search = element('input', 'dh-ai-custom-select-search');
    var list = element('div', 'dh-ai-custom-select-options');
    var empty = element('p', 'dh-ai-custom-select-empty', 'No matching options.');
    var open = false;

    source.dataset.dhCustomSelect = '1';
    source.classList.add('dh-ai-select-source');
    source.setAttribute('aria-hidden', 'true');
    source.tabIndex = -1;
    var details = source.closest('.dh-ai-details');
    if (details) details.classList.add('has-custom-select');

    trigger.type = 'button';
    trigger.id = definition.id + '-choose';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', definition.id + '-custom-options');
    trigger.appendChild(labelText);

    search.type = 'search';
    search.placeholder = 'Search ' + definition.title.toLowerCase() + '…';
    search.setAttribute('aria-label', 'Search ' + definition.title.toLowerCase());
    list.id = definition.id + '-custom-options';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', definition.title);
    empty.hidden = true;
    panel.hidden = true;
    panel.append(search, list, empty);
    root.append(trigger, panel);
    source.insertAdjacentElement('afterend', root);

    var label = app.querySelector('label[for="' + definition.id + '"]');
    if (label) label.htmlFor = trigger.id;

    function renderOptions() {
      list.replaceChildren();
      var query = normalize(search.value);
      var options = Array.prototype.slice.call(source.options).filter(function (option) {
        return option.value !== '' && !option.disabled && (!query || normalize(option.textContent).indexOf(query) !== -1);
      });
      options.forEach(function (option) {
        var choice = element('button', 'dh-ai-custom-select-option');
        var text = element('span', '', option.textContent.trim());
        var check = element('span', 'dh-ai-custom-select-check', option.value === source.value ? '✓' : '');
        choice.type = 'button';
        choice.dataset.value = option.value;
        choice.setAttribute('role', 'option');
        choice.setAttribute('aria-selected', option.value === source.value ? 'true' : 'false');
        choice.append(text, check);
        choice.addEventListener('click', function () {
          source.value = option.value;
          source.dispatchEvent(new Event('change', { bubbles: true }));
          syncValue();
          close(true);
        });
        list.appendChild(choice);
      });
      empty.hidden = options.length > 0;
    }

    function syncValue() {
      var current = source.options[source.selectedIndex];
      labelText.textContent = current ? current.textContent.trim() : definition.placeholder;
      trigger.classList.toggle('is-placeholder', !source.value);
      trigger.setAttribute('aria-label', definition.title + ': ' + labelText.textContent);
      if (open) renderOptions();
    }

    function close(restoreFocus) {
      if (!open) return;
      open = false;
      panel.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
      search.value = '';
      renderOptions();
      if (restoreFocus) trigger.focus();
    }

    function show() {
      closeOtherPickers(api);
      open = true;
      panel.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');
      renderOptions();
      search.focus();
    }

    var api = { root: root, close: close };
    selectPickers.push(api);
    source.addEventListener('change', syncValue);
    trigger.addEventListener('click', function () {
      if (open) close(false);
      else show();
    });
    search.addEventListener('input', renderOptions);
    search.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        close(true);
      } else if (event.key === 'ArrowDown') {
        var first = list.querySelector('[role="option"]');
        if (first) { event.preventDefault(); first.focus(); }
      } else if (event.key === 'Enter') {
        var query = normalize(search.value);
        var match = Array.prototype.slice.call(source.options).find(function (option) {
          return option.value !== '' && normalize(option.textContent) === query;
        });
        var visibleOptions = Array.prototype.slice.call(list.querySelectorAll('[role="option"]'));
        var target = match ? visibleOptions.find(function (option) { return option.dataset.value === match.value; }) : visibleOptions[0];
        if (target) { event.preventDefault(); target.click(); }
      } else if (event.key === 'Tab') {
        close(false);
      }
    });
    list.addEventListener('keydown', function (event) {
      var options = Array.prototype.slice.call(list.querySelectorAll('[role="option"]'));
      var index = options.indexOf(document.activeElement);
      var next = index;
      if (event.key === 'ArrowDown') next = Math.min(options.length - 1, index + 1);
      else if (event.key === 'ArrowUp' && index <= 0) { event.preventDefault(); search.focus(); return; }
      else if (event.key === 'ArrowUp') next = Math.max(0, index - 1);
      else if (event.key === 'Home') next = 0;
      else if (event.key === 'End') next = options.length - 1;
      else if (event.key === 'Escape') { event.preventDefault(); close(true); return; }
      else if (event.key === 'Tab') { close(false); return; }
      else return;
      event.preventDefault();
      if (options[next]) options[next].focus();
    });
    renderOptions();
    syncValue();
  }

  definitions.forEach(init);
  [
    { id: 'dh-ai-category', title: 'Main category', placeholder: 'Choose a category' },
    { id: 'dh-ai-mode', title: 'Writing mode', placeholder: 'Choose a writing mode' },
    { id: 'dh-ai-application-method', title: 'Application method', placeholder: 'Not stated' }
  ].forEach(initSelect);
  document.addEventListener('pointerdown', function (event) {
    pickers.concat(selectPickers).forEach(function (picker) { if (!picker.root.contains(event.target)) picker.close(false); });
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') pickers.concat(selectPickers).forEach(function (picker) { picker.close(false); });
  });
})();
