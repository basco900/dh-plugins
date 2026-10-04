(function () {
  'use strict';

  var app = document.querySelector('.dh-ai-app');
  if (!app || !window.DixcoverHubAI) return;

  var $ = function (selector) { return app.querySelector(selector); };
  var status = $('[data-ai-status]');
  var activePostId = 0;
  var lastResult = null;
  var faqRows = [];
  var applicationLinkRows = [{ label: '', url: '' }];
  var selectedFeaturedImage = 0;

  function value(id) { var field = document.getElementById(id); return field ? field.value.trim() : ''; }
  function setValue(id, next) { var field = document.getElementById(id); if (field) field.value = next == null ? '' : String(next); }
  function setEditorContent(html) {
    if (window.tinymce && tinymce.get('dixcoverhub_ai_content')) {
      tinymce.get('dixcoverhub_ai_content').setContent(html || '');
    } else {
      var field = document.getElementById('dixcoverhub_ai_content');
      if (field) field.value = html || '';
    }
  }
  function getEditorContent() {
    if (window.tinymce && tinymce.get('dixcoverhub_ai_content')) return tinymce.get('dixcoverhub_ai_content').getContent();
    var field = document.getElementById('dixcoverhub_ai_content');
    return field ? field.value : '';
  }
  function notice(message, kind) {
    status.hidden = false;
    status.className = 'dh-ai-status is-' + (kind || 'info');
    status.textContent = message;
    status.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  function listText(items) { return Array.isArray(items) ? items.join('\n') : ''; }
  function splitLines(text) { return text.split(/\r?\n/).map(function (line) { return line.trim(); }).filter(Boolean); }
  function apiHeaders() { return { 'X-WP-Nonce': DixcoverHubAI.nonce }; }
  function normalizeName(text) { return String(text || '').toLowerCase().replace(/&/g, 'and').replace(/[^a-z0-9]+/g, ' ').trim(); }

  function renderFaqs() {
    var host = $('[data-faqs]');
    host.replaceChildren();
    faqRows.forEach(function (faq, index) {
      var row = document.createElement('div');
      row.className = 'dh-ai-faq-row';
      var heading = document.createElement('div');
      heading.className = 'dh-ai-faq-heading';
      var title = document.createElement('strong');
      title.textContent = 'Question ' + (index + 1);
      var remove = document.createElement('button');
      remove.type = 'button'; remove.className = 'button-link-delete'; remove.textContent = 'Remove';
      remove.addEventListener('click', function () { faqRows.splice(index, 1); renderFaqs(); });
      heading.append(title, remove);
      var question = document.createElement('input');
      question.className = 'dh-ai-input'; question.placeholder = 'Question'; question.value = faq.question || '';
      question.addEventListener('input', function () { faq.question = question.value; });
      var answer = document.createElement('textarea');
      answer.className = 'dh-ai-input'; answer.rows = 3; answer.placeholder = 'Answer based on confirmed details'; answer.value = faq.answer || '';
      answer.addEventListener('input', function () { faq.answer = answer.value; });
      row.append(heading, question, answer); host.appendChild(row);
    });
    $('[data-faq-count]').textContent = String(faqRows.length);
  }

  function renderSources(sources) {
    var host = $('[data-sources]'); host.replaceChildren();
    if (!Array.isArray(sources) || !sources.length) {
      var empty = document.createElement('li'); empty.textContent = 'No source citations were returned. Confirm all facts against your source material.'; host.appendChild(empty); return;
    }
    sources.forEach(function (source) {
      try {
        var parsed = new URL(source.url);
        if (!['https:', 'http:'].includes(parsed.protocol)) return;
        var item = document.createElement('li'); var link = document.createElement('a');
        link.href = parsed.href; link.target = '_blank'; link.rel = 'noopener noreferrer'; link.textContent = source.title || parsed.hostname;
        item.appendChild(link); host.appendChild(item);
      } catch (_) { /* Ignore malformed citations. */ }
    });
  }

  function renderApplicationLinks() {
    var host = $('[data-application-links]');
    if (!host) return;
    host.replaceChildren();
    applicationLinkRows.forEach(function (item, index) {
      var row = document.createElement('div');
      row.className = 'dh-ai-application-link-row';
      var labelWrap = document.createElement('label');
      labelWrap.className = 'dh-ai-application-link-label';
      labelWrap.textContent = 'Button label';
      var label = document.createElement('input');
      label.className = 'dh-ai-input';
      label.type = 'text';
      label.maxLength = 100;
      label.placeholder = 'e.g. Apply on official site';
      label.value = item.label || '';
      label.setAttribute('aria-label', 'Application link ' + (index + 1) + ' label');
      label.addEventListener('input', function () { item.label = label.value; });
      labelWrap.appendChild(label);

      var urlWrap = document.createElement('label');
      urlWrap.className = 'dh-ai-application-link-url';
      urlWrap.textContent = 'Application URL';
      var url = document.createElement('input');
      url.className = 'dh-ai-input';
      url.type = 'url';
      url.maxLength = 2048;
      url.placeholder = 'https://';
      url.value = item.url || '';
      url.setAttribute('aria-label', 'Application link ' + (index + 1) + ' URL');
      url.addEventListener('input', function () { item.url = url.value; });
      urlWrap.appendChild(url);

      row.append(labelWrap, urlWrap);
      if (applicationLinkRows.length > 1) {
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'button-link-delete dh-ai-application-link-remove';
        remove.textContent = 'Remove';
        remove.setAttribute('aria-label', 'Remove application link ' + (index + 1));
        remove.addEventListener('click', function () {
          applicationLinkRows.splice(index, 1);
          renderApplicationLinks();
        });
        row.appendChild(remove);
      }
      host.appendChild(row);
    });
    var addButton = $('[data-add-application-link]');
    if (addButton) addButton.disabled = applicationLinkRows.length >= 8;
  }

  function setApplicationLinks(links) {
    applicationLinkRows = (Array.isArray(links) ? links : []).slice(0, 8).map(function (link) {
      return { label: String((link && link.label) || ''), url: String((link && link.url) || '') };
    });
    if (!applicationLinkRows.length) applicationLinkRows = [{ label: '', url: '' }];
    renderApplicationLinks();
  }

  function getApplicationLinks() {
    return applicationLinkRows.map(function (link) {
      return { label: String(link.label || '').trim(), url: String(link.url || '').trim() };
    }).filter(function (link) { return link.url; }).slice(0, 8);
  }

  function applyCategorySuggestions(names) {
    var wanted = new Set((Array.isArray(names) ? names : []).map(normalizeName));
    if (value('dh-ai-category')) wanted.add(normalizeName((DixcoverHubAI.categories.find(function (item) { return Number(item.id) === Number(value('dh-ai-category')); }) || {}).name));
    app.querySelectorAll('input[name="dh_ai_categories[]"]').forEach(function (checkbox) {
      var category = DixcoverHubAI.categories.find(function (item) { return Number(item.id) === Number(checkbox.value); });
      if (category && wanted.has(normalizeName(category.name))) checkbox.checked = true;
    });
  }

  function applyResult(data) {
    lastResult = data;
    setValue('dh-ai-excerpt', data.excerpt);
    setValue('dh-ai-summary', data.summary);
    setEditorContent(data.content);
    setValue('dh-ai-provider', data.providerName);
    setValue('dh-ai-provider-about', data.providerAbout);
    setValue('dh-ai-provider-website', data.providerWebsite);
    setValue('dh-ai-provider-email', data.providerEmail);
    setValue('dh-ai-provider-social', listText(data.providerSocialProfiles));
    setValue('dh-ai-employment', data.employmentType);
    setValue('dh-ai-location', data.location);
    setValue('dh-ai-types', (data.typeNames || []).join(', '));
    setValue('dh-ai-levels', (data.levelNames || []).join(', '));
    setValue('dh-ai-modes', (data.modeNames || []).join(', '));
    setValue('dh-ai-locations', (data.locationNames || []).join(', '));
    setValue('dh-ai-deadline', /^\d{4}-\d{2}-\d{2}$/.test(data.deadline || '') ? data.deadline : '');
    setValue('dh-ai-duration', data.duration);
    setValue('dh-ai-salary', data.salary);
    setValue('dh-ai-application-method', data.applicationMethod || 'none');
    setApplicationLinks(Array.isArray(data.applicationLinks) && data.applicationLinks.length
      ? data.applicationLinks
      : (data.applicationLink ? [{ label: '', url: data.applicationLink }] : []));
    setValue('dh-ai-application-email', data.applicationEmail);
    setValue('dh-ai-tags', (data.tagNames || []).join(', '));
    setValue('dh-ai-requirements', listText(data.requirements));
    setValue('dh-ai-benefits', listText(data.benefits));
    setValue('dh-ai-meta-title', data.metaTitle);
    setValue('dh-ai-meta-description', data.metaDescription);
    setValue('dh-ai-focus-keyword', data.focusKeyword);
    faqRows = Array.isArray(data.faqs) ? data.faqs.map(function (item) { return { question: item.question || '', answer: item.answer || '' }; }) : [];
    renderFaqs();
    renderSources(data.sources);
    applyCategorySuggestions(data.categoryNames);
    notice('Draft content is ready. Review the article, metadata, dates, application details and FAQs before saving.', 'success');
  }

  $('[data-add-faq]').addEventListener('click', function () { if (faqRows.length >= 8) { notice('You can add up to 8 FAQs.', 'warning'); return; } faqRows.push({ question: '', answer: '' }); renderFaqs(); });
  $('[data-add-application-link]').addEventListener('click', function () {
    if (applicationLinkRows.length >= 8) return;
    applicationLinkRows.push({ label: '', url: '' });
    renderApplicationLinks();
    var fields = app.querySelectorAll('[data-application-links] input[type="url"]');
    if (fields.length) fields[fields.length - 1].focus();
  });
  renderApplicationLinks();

  $('[data-generate]').addEventListener('click', async function () {
    var title = value('dh-ai-title');
    if (!title) { notice('Enter the opportunity title first.', 'error'); document.getElementById('dh-ai-title').focus(); return; }
    var category = value('dh-ai-category');
    if (!category || Number(category) < 1) { notice('Choose the main opportunity category first.', 'error'); document.getElementById('dh-ai-category').focus(); return; }
    var button = this; button.disabled = true; setEvidenceBusy(true);
    notice(DixcoverHubAI.labels.working + ' This can take a little while.', 'info');
    var form = new FormData();
    form.append('title', title);
    form.append('provider', value('dh-ai-provider'));
    form.append('provider_website', value('dh-ai-provider-website-source'));
    form.append('application_email', value('dh-ai-application-email-source'));
    form.append('category_id', value('dh-ai-category'));
    form.append('mode', value('dh-ai-mode'));
    form.append('notes', value('dh-ai-notes'));
    form.append('source_links', value('dh-ai-links'));
    form.append('existing_content', value('dh-ai-mode') !== 'write' ? getEditorContent() : '');
    form.append('instruction', value('dh-ai-instruction'));
    evidenceFiles.forEach(function (item) { form.append('images[]', item.file, item.file.name); });
    try {
      var response = await fetch(DixcoverHubAI.generateUrl, { method: 'POST', credentials: 'same-origin', headers: apiHeaders(), body: form });
      var json = await response.json();
      if (!response.ok) throw new Error(json.message || 'AI generation failed. Check the server configuration and try again.');
      applyResult(json);
    } catch (error) {
      notice(error.message || 'AI generation failed. Please try again.', 'error');
    } finally { button.disabled = false; setEvidenceBusy(false); }
  });

  function opportunityPayload() {
    var base = lastResult || {};
    var applicationLinks = getApplicationLinks();
    return {
      summary: value('dh-ai-summary'), metaTitle: value('dh-ai-meta-title'), metaDescription: value('dh-ai-meta-description'), focusKeyword: value('dh-ai-focus-keyword'),
      providerName: value('dh-ai-provider'), providerAbout: value('dh-ai-provider-about'), providerWebsite: value('dh-ai-provider-website'), providerEmail: value('dh-ai-provider-email'), providerSocialProfiles: splitLines(value('dh-ai-provider-social')),
      employmentType: value('dh-ai-employment'), location: value('dh-ai-location'), deadline: value('dh-ai-deadline'), duration: value('dh-ai-duration'), salary: value('dh-ai-salary'),
      applicationMethod: value('dh-ai-application-method'), applicationLink: (applicationLinks[0] || {}).url || '', applicationLinks: applicationLinks, applicationEmail: value('dh-ai-application-email'),
      requirements: splitLines(value('dh-ai-requirements')), benefits: splitLines(value('dh-ai-benefits')), faqs: faqRows, sources: base.sources || [],
      categoryNames: base.categoryNames || [], typeNames: splitTags(value('dh-ai-types')), levelNames: splitTags(value('dh-ai-levels')), modeNames: splitTags(value('dh-ai-modes')), locationNames: splitTags(value('dh-ai-locations')), tagNames: splitTags(value('dh-ai-tags'))
    };
  }
  function splitTags(text) { return text.split(',').map(function (tag) { return tag.trim(); }).filter(Boolean).slice(0, 20); }

  $('[data-save]').addEventListener('click', async function () {
    var button = this;
    var title = value('dh-ai-title'); var content = getEditorContent();
    if (!title || !content.trim()) { notice('Add a title and article body before saving.', 'error'); return; }
    var categories = Array.prototype.map.call(app.querySelectorAll('input[name="dh_ai_categories[]"]:checked'), function (item) { return Number(item.value); });
    var payload = {
      postId: activePostId, title: title, content: content, excerpt: value('dh-ai-excerpt'), categoryIds: categories,
      tags: splitTags(value('dh-ai-tags')), opportunity: opportunityPayload(), featuredImageId: selectedFeaturedImage
    };
    button.disabled = true; button.textContent = 'Saving draft…';
    try {
      var response = await fetch(DixcoverHubAI.saveUrl, { method: 'POST', credentials: 'same-origin', headers: Object.assign({ 'Content-Type': 'application/json' }, apiHeaders()), body: JSON.stringify(payload) });
      var json = await response.json();
      if (!response.ok) throw new Error(json.message || 'Could not save the draft.');
      activePostId = Number(json.id); $('[data-save-hint]').textContent = json.message;
      notice(json.message, 'success');
      button.textContent = 'Update WordPress draft';
      var oldLink = $('[data-open-draft]');
      if (oldLink) oldLink.remove();
      var link = document.createElement('a'); link.dataset.openDraft = '1'; link.href = json.editUrl; link.className = 'dh-ai-edit-link'; link.textContent = 'Continue in WordPress editor ↗'; link.target = '_blank'; link.rel = 'noopener noreferrer';
      $('[data-save-hint]').appendChild(link);
    } catch (error) { notice(error.message || 'Could not save the draft.', 'error'); button.textContent = 'Save as WordPress draft'; }
    finally { button.disabled = false; }
  });


  var fileInput = document.getElementById('dh-ai-images');
  var evidenceFiles = [];
  var generationBusy = false;

  function showFeaturedImage(imageId, imageUrl, alt, thumbnailUrl) {
    selectedFeaturedImage = Number(imageId) || 0;
    var preview = $('[data-featured-preview]');
    preview.replaceChildren();
    if (!selectedFeaturedImage || !imageUrl) return;
    var img = document.createElement('img');
    img.src = thumbnailUrl || imageUrl;
    img.alt = alt || '';
    var label = document.createElement('span');
    label.textContent = 'Selected as featured image';
    var clear = document.createElement('button');
    clear.type = 'button';
    clear.className = 'button-link-delete';
    clear.textContent = 'Remove';
    clear.addEventListener('click', function () {
      selectedFeaturedImage = 0;
      preview.replaceChildren();
      app.querySelectorAll('[data-ai-feature-image]').forEach(function (button) {
        button.textContent = 'Use as featured';
        button.disabled = false;
      });
    });
    app.querySelectorAll('[data-ai-feature-image]').forEach(function (button) {
      var isSelected = Number(button.dataset.attachmentId) === selectedFeaturedImage;
      button.textContent = isSelected ? 'Selected' : 'Use as featured';
      button.disabled = false;
    });
    preview.append(img, label, clear);
  }

  function renderEvidenceImages() {
    var host = $('[data-file-list]');
    host.replaceChildren();
    if (evidenceFiles.length) {
      var queueLabel = document.createElement('p');
      queueLabel.className = 'dh-ai-image-queue-label';
      queueLabel.textContent = evidenceFiles.length + (evidenceFiles.length === 1 ? ' evidence image ready' : ' evidence images ready') + ' · all will be analyzed';
      host.appendChild(queueLabel);
    }
    evidenceFiles.forEach(function (item, index) {
      var file = item.file;
      var card = document.createElement('div');
      card.className = 'dh-ai-image-card';
      var preview = document.createElement('img');
      preview.alt = file.name;
      preview.src = item.previewUrl;
      var details = document.createElement('div');
      details.className = 'dh-ai-image-card-details';
      var title = document.createElement('strong');
      title.textContent = file.name;
      var size = document.createElement('small');
      size.textContent = Math.ceil(file.size / 1024) + ' KB';
      var statusText = document.createElement('small');
      statusText.textContent = item.uploaded ? 'Saved in Media Library.' : 'Included as source material.';
      details.append(title, size, statusText);
      var choose = document.createElement('button');
      choose.type = 'button';
      choose.className = 'button';
      choose.textContent = item.uploaded && Number(item.uploaded.id) === selectedFeaturedImage ? 'Selected' : 'Use as featured';
      choose.disabled = generationBusy;
      if (item.uploaded) choose.dataset.attachmentId = String(item.uploaded.id);
      choose.setAttribute('data-ai-feature-image', '1');
      var remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'button-link-delete dh-ai-image-remove';
      remove.textContent = 'Remove';
      remove.setAttribute('aria-label', 'Remove ' + file.name + ' from evidence images');
      remove.disabled = generationBusy || !!item.saving;
      var actions = document.createElement('div');
      actions.className = 'dh-ai-image-card-actions';
      choose.addEventListener('click', async function () {
        if (generationBusy || item.saving) return;
        if (item.uploaded) {
          showFeaturedImage(item.uploaded.id, item.uploaded.url, item.uploaded.alt, item.uploaded.thumbnail);
          choose.textContent = 'Selected';
          return;
        }
        item.saving = true;
        choose.disabled = true;
        choose.textContent = 'Adding to Media Library...';
        statusText.textContent = 'Uploading to the WordPress Media Library...';
        var upload = new FormData();
        upload.append('file', file, file.name);
        try {
          var response = await fetch(DixcoverHubAI.imageSaveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: apiHeaders(),
            body: upload
          });
          var result = await response.json();
          if (!response.ok || !result.id || !result.url) {
            throw new Error(result.message || 'WordPress could not save this image.');
          }
          item.uploaded = result;
          choose.dataset.attachmentId = String(result.id);
          showFeaturedImage(result.id, result.url, result.alt || file.name, result.thumbnail);
          choose.textContent = 'Selected';
          statusText.textContent = 'Saved in Media Library and selected as featured image.';
          notice('Image saved to the Media Library and selected for the draft.', 'success');
        } catch (error) {
          choose.disabled = false;
          choose.textContent = 'Try featured image';
          statusText.textContent = error.message || 'Could not save this image.';
          notice(statusText.textContent, 'error');
        } finally {
          item.saving = false;
        }
      });
      remove.addEventListener('click', function () {
        if (generationBusy || item.saving) return;
        URL.revokeObjectURL(item.previewUrl);
        evidenceFiles.splice(index, 1);
        renderEvidenceImages();
      });
      actions.append(choose, remove);
      card.append(preview, details, actions);
      host.appendChild(card);
    });
  }

  function addEvidenceImages(files) {
    var acceptedTypes = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
    var totalBytes = evidenceFiles.reduce(function (sum, item) { return sum + item.file.size; }, 0);
    var added = 0;
    var skipped = [];
    Array.prototype.forEach.call(files || [], function (file) {
      if (evidenceFiles.length >= 8) { skipped.push(file.name + ': limit of 8 images reached'); return; }
      if (!acceptedTypes.includes(String(file.type || '').toLowerCase())) { skipped.push(file.name + ': use PNG, JPEG, WebP, or GIF'); return; }
      if (file.size < 1 || file.size > 8 * 1024 * 1024) { skipped.push(file.name + ': each image must be 8 MB or smaller'); return; }
      if (totalBytes + file.size > 24 * 1024 * 1024) { skipped.push(file.name + ': combined image limit is 24 MB'); return; }
      totalBytes += file.size;
      evidenceFiles.push({ file: file, previewUrl: URL.createObjectURL(file), uploaded: null, saving: false });
      added += 1;
    });
    fileInput.value = '';
    renderEvidenceImages();
    if (skipped.length) notice(skipped.slice(0, 3).join('. ') + (skipped.length > 3 ? '. Some other files were skipped.' : '.'), 'warning');
    else if (added) notice(added + (added === 1 ? ' evidence image added.' : ' evidence images added.'), 'success');
  }

  function setEvidenceBusy(busy) {
    generationBusy = !!busy;
    fileInput.disabled = generationBusy;
    app.querySelectorAll('.dh-ai-image-card').forEach(function (card, index) {
      var disabled = generationBusy || !!(evidenceFiles[index] && evidenceFiles[index].saving);
      card.querySelectorAll('[data-ai-feature-image], .dh-ai-image-remove').forEach(function (button) {
        button.disabled = disabled;
      });
    });
  }

  fileInput.addEventListener('change', function () { addEvidenceImages(fileInput.files); });

  var mode = document.getElementById('dh-ai-mode');
  mode.addEventListener('change', function () {
    var label = document.querySelector('label[for="dh-ai-notes"]');
    if (label) {
      label.firstChild.textContent = mode.value === 'refine'
        ? 'Refinement instruction and verified notes '
        : mode.value === 'regenerate'
          ? 'Verified source notes and prior article context '
          : 'Verified notes or existing article ';
    }
  });

  var mediaButton = $('[data-featured-image]');
  if (mediaButton) mediaButton.addEventListener('click', function () {
    if (!window.wp || !wp.media) return;
    var frame = wp.media({ title: 'Select a featured image', button: { text: 'Use this image' }, multiple: false, library: { type: 'image' } });
    frame.on('select', function () {
      var image = frame.state().get('selection').first().toJSON();
      showFeaturedImage(image.id, image.url, image.alt, image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url);
    });
    frame.open();
  });
})();
