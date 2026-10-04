(function () {
  'use strict';
  var box = document.querySelector('[data-wa-editor]');
  if (!box || !window.DixcoverHubWhatsApp) return;
  var status = box.querySelector('[data-wa-status]');
  var text = box.querySelector('[data-wa-text]');
  var busy = false;

  function message(value, kind) {
    status.hidden = false;
    status.className = 'dh-wa-status is-' + (kind || 'info');
    status.textContent = value;
  }
  function setBusy(state) {
    busy = state;
    box.querySelectorAll('button').forEach(function (button) { button.disabled = state; });
  }
  function headers(json) {
    var result = { 'X-WP-Nonce': DixcoverHubWhatsApp.nonce };
    if (json) result['Content-Type'] = 'application/json';
    return result;
  }
  async function request(url, payload) {
    var response = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: headers(true), body: JSON.stringify(payload) });
    var data = await response.json();
    if (!response.ok) throw new Error(data.message || 'The request could not be completed.');
    return data;
  }
  box.querySelector('[data-wa-generate]').addEventListener('click', async function () {
    if (busy) return;
    if (!DixcoverHubWhatsApp.postId) { message('Save the post as a draft first, then generate its summary.', 'warning'); return; }
    setBusy(true); message('Checking the article details and preparing a concise summary…', 'info');
    try {
      var data = await request(DixcoverHubWhatsApp.generateUrl, { postId: DixcoverHubWhatsApp.postId });
      text.value = data.summary || '';
      message('Summary generated and saved to this post. You can edit it before copying or saving again.', 'success');
    } catch (error) { message(error.message || 'Summary generation failed.', 'error'); }
    finally { setBusy(false); }
  });
  box.querySelector('[data-wa-save]').addEventListener('click', async function () {
    if (busy) return;
    if (!DixcoverHubWhatsApp.postId) { message('Save the post first, then save its WhatsApp summary.', 'warning'); return; }
    setBusy(true);
    try {
      var data = await request(DixcoverHubWhatsApp.saveUrl, { postId: DixcoverHubWhatsApp.postId, summary: text.value });
      message(data.message || 'WhatsApp summary saved.', 'success');
    } catch (error) { message(error.message || 'Could not save the summary.', 'error'); }
    finally { setBusy(false); }
  });
  box.querySelector('[data-wa-copy]').addEventListener('click', async function () {
    if (!text.value.trim()) { message('Generate or write a summary before copying.', 'warning'); return; }
    try {
      if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(text.value);
      else { text.focus(); text.select(); document.execCommand('copy'); text.setSelectionRange(0, 0); }
      message('Summary copied to the clipboard.', 'success');
    } catch (_) { text.focus(); text.select(); message('Select the text and copy it with Ctrl+C.', 'warning'); }
  });
})();
