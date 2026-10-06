(function () {
  'use strict';

  var config = window.DixcoverHubWhatsAppList;
  var modal = document.querySelector('[data-wa-list-modal]');
  if (!config || !modal) return;

  var dialog = modal.querySelector('.dh-wa-list-dialog');
  var text = modal.querySelector('[data-wa-list-text]');
  var postTitle = modal.querySelector('[data-wa-list-post-title]');
  var status = modal.querySelector('[data-wa-list-status]');
  var generateButton = modal.querySelector('[data-wa-list-generate]');
  var copyButton = modal.querySelector('[data-wa-list-copy]');
  var activeLink = null;
  var activePostId = 0;
  var busy = false;
  var previousFocus = null;

  function setStatus(message, kind) {
    status.hidden = !message;
    status.textContent = message || '';
    status.className = 'dh-wa-list-status' + (kind ? ' is-' + kind : '');
  }

  function setBusy(value) {
    busy = value;
    generateButton.disabled = value;
    copyButton.disabled = value || !text.value.trim();
    modal.querySelectorAll('[data-wa-list-close]').forEach(function (button) {
      button.disabled = value;
    });
    generateButton.textContent = value ? 'Generating summary…' : (text.value.trim() ? 'Regenerate summary' : 'Generate summary');
    modal.setAttribute('aria-busy', value ? 'true' : 'false');
  }

  function open(link) {
    activeLink = link;
    activePostId = Number(link.dataset.postId || 0);
    previousFocus = document.activeElement;
    postTitle.textContent = link.dataset.postTitle || '';
    text.value = link.dataset.summary || '';
    setStatus('', '');
    modal.hidden = false;
    document.body.classList.add('dh-wa-list-open');
    copyButton.disabled = !text.value.trim();
    generateButton.textContent = text.value.trim() ? 'Regenerate summary' : 'Generate summary';
    window.requestAnimationFrame(function () { dialog.focus(); });
    if (!text.value.trim()) generate();
  }

  function close() {
    if (busy) return;
    modal.hidden = true;
    document.body.classList.remove('dh-wa-list-open');
    if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
    activeLink = null;
    activePostId = 0;
  }

  async function request(url, payload) {
    var response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce
      },
      body: JSON.stringify(payload)
    });
    var data = await response.json();
    if (!response.ok) throw new Error(data.message || 'The request could not be completed.');
    return data;
  }

  async function generate() {
    if (busy || !activePostId) return;
    setBusy(true);
    setStatus('Checking the article details and preparing a concise summary…', 'info');
    try {
      var result = await request(config.generateUrl, { postId: activePostId });
      if (!result.summary) throw new Error('The AI did not return a summary. Try again.');
      text.value = result.summary;
      if (activeLink) {
        activeLink.dataset.summary = result.summary;
        activeLink.textContent = 'View WhatsApp summary';
      }
      setStatus('Summary generated and saved to this post.', 'success');
    } catch (error) {
      setStatus(error.message || 'The summary could not be generated.', 'error');
    } finally {
      setBusy(false);
    }
  }

  async function copySummary() {
    if (busy || !text.value.trim()) return;
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text.value);
      } else {
        text.focus();
        text.select();
        if (!document.execCommand('copy')) throw new Error('Copy was not available.');
        dialog.focus();
      }
      setStatus('Summary copied to the clipboard.', 'success');
    } catch (error) {
      setStatus(error.message || 'Select the text and copy it with Ctrl+C.', 'warning');
    }
  }

  document.addEventListener('click', function (event) {
    var link = event.target.closest('[data-wa-list-open]');
    if (!link) return;
    event.preventDefault();
    open(link);
  });

  modal.querySelectorAll('[data-wa-list-close]').forEach(function (button) {
    button.addEventListener('click', close);
  });
  generateButton.addEventListener('click', generate);
  copyButton.addEventListener('click', copySummary);

  document.addEventListener('keydown', function (event) {
    if (modal.hidden) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
      return;
    }
    if (event.key !== 'Tab') return;
    var focusable = Array.prototype.slice.call(dialog.querySelectorAll('button:not(:disabled), textarea:not(:disabled), [href], [tabindex]:not([tabindex="-1"])'))
      .filter(function (element) { return !element.hidden; });
    if (!focusable.length) return;
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
