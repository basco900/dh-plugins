(function () {
  'use strict';
  var root = document.querySelector('[data-dh-bottom-nav]');
  if (!root) return;

  var overlay = root.querySelector('[data-dh-bottom-nav-close]');
  var panelWrap = root.querySelector('[data-dh-bottom-nav-panel-wrap]');
  var panel = root.querySelector('[data-dh-bottom-nav-panel]');
  var toggles = Array.prototype.slice.call(root.querySelectorAll('[data-dh-bottom-nav-toggle]'));
  var isOpen = false;
  var previousFocus = null;

  function setOpen(value) {
    isOpen = Boolean(value);
    overlay.hidden = !isOpen;
    panelWrap.hidden = !isOpen;
    toggles.forEach(function (toggle) {
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
    if (isOpen) {
      previousFocus = document.activeElement;
      window.requestAnimationFrame(function () {
        var firstLink = panel.querySelector('a[href]');
        if (firstLink) firstLink.focus();
        else panel.focus();
      });
    } else if (previousFocus && typeof previousFocus.focus === 'function') {
      previousFocus.focus();
      previousFocus = null;
    }
  }

  toggles.forEach(function (toggle) {
    toggle.addEventListener('click', function () { setOpen(!isOpen); });
  });
  overlay.addEventListener('click', function () { setOpen(false); });
  panel.addEventListener('click', function (event) {
    if (event.target.closest('a[href]')) setOpen(false);
  });
  document.addEventListener('keydown', function (event) {
    if (!isOpen) return;
    if (event.key === 'Escape') {
      setOpen(false);
      return;
    }
    if (event.key !== 'Tab') return;
    var focusable = Array.prototype.slice.call(panel.querySelectorAll('a[href], button:not([disabled]), [tabindex="0"]'));
    if (!focusable.length) {
      event.preventDefault();
      panel.focus();
      return;
    }
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });
})();
