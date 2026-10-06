(function () {
  'use strict';

  var app = document.querySelector('.dh-ai-app');
  if (!app || !window.DixcoverHubAI) return;

  var maxImageBytes = Number(DixcoverHubAI.maxImageBytes);
  var maxTotalImageBytes = Number(DixcoverHubAI.maxTotalImageBytes);
  if (!Number.isFinite(maxImageBytes)) maxImageBytes = 8 * 1024 * 1024;
  if (!Number.isFinite(maxTotalImageBytes)) maxTotalImageBytes = 24 * 1024 * 1024;
  var maxImageLabel = DixcoverHubAI.maxImageLabel || '8 MB';
  var maxTotalImageLabel = DixcoverHubAI.maxTotalImageLabel || '24 MB';

  var $ = function (selector) { return app.querySelector(selector); };
  var status = $('[data-ai-status]');
  var activePostId = 0;
  var autoSaveTimer = 0;
  var editorChangeVersion = 0;
  var editorDirty = false;
  var autoSaveFailed = false;
  var saveInProgress = false;
  var autoSaveState = null;
  var suspendDirtyTracking = false;
  var lastResult = null;
  var faqRows = [];
  var applicationLinkRows = [{ label: '', url: '' }];
  var selectedFeaturedImage = 0;
  var currentSlug = '';
  var slugCustomized = false;
  var activeGenerationController = null;
  var generationTimeoutId = 0;
  var generationTimedOut = false;
  var cancelGenerationButton = $('[data-cancel-generation]');

  function value(id) { var field = document.getElementById(id); return field ? field.value.trim() : ''; }
  function setValue(id, next) {
    var field = document.getElementById(id);
    if (!field) return;
    field.value = next == null ? '' : String(next);
    if (field.dataset.dhTaxonomyReady) field.dispatchEvent(new Event('input', { bubbles: true }));
    if (field.dataset.dhCustomSelect) field.dispatchEvent(new Event('change', { bubbles: true }));
  }
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
  function setAutoSaveState(message, kind) {
    if (!autoSaveState) return;
    autoSaveState.textContent = message || '';
    autoSaveState.classList.toggle('is-saving', kind === 'saving');
    autoSaveState.classList.toggle('is-error', kind === 'error');
    autoSaveState.hidden = !message;
  }
  function queueAutoSave() {
    window.clearTimeout(autoSaveTimer);
    autoSaveTimer = 0;
    if (!activePostId || !editorDirty || autoSaveFailed) return;
    autoSaveTimer = window.setTimeout(function () {
      autoSaveTimer = 0;
      var button = activePostStatus === 'publish' && publishButton ? publishButton : saveButton;
      savePost(activePostStatus, button, true);
    }, 30000);
  }
  function markEditorDirty() {
    if (suspendDirtyTracking) return;
    editorChangeVersion += 1;
    editorDirty = true;
    autoSaveFailed = false;
    if (!activePostId) return;
    setAutoSaveState('Unsaved changes');
    queueAutoSave();
  }
  function listText(items) { return Array.isArray(items) ? items.join('\n') : ''; }
  function splitLines(text) { return text.split(/\r?\n/).map(function (line) { return line.trim(); }).filter(Boolean); }
  function apiHeaders() { return { 'X-WP-Nonce': DixcoverHubAI.nonce }; }
  function createGenerationRequestId() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
    return Date.now().toString(16) + '-' + Math.random().toString(16).slice(2) + '-' + Math.random().toString(16).slice(2);
  }
  function waitForProgressPoll(signal) {
    return new Promise(function (resolve) {
      var timer = window.setTimeout(done, 1200);
      function done() {
        signal.removeEventListener('abort', abort);
        resolve();
      }
      function abort() {
        window.clearTimeout(timer);
        done();
      }
      if (signal.aborted) abort();
      else signal.addEventListener('abort', abort, { once: true });
    });
  }
  async function pollGenerationProgress(requestId, controller) {
    var lastStage = '';
    while (!controller.signal.aborted) {
      try {
        var url = new URL(DixcoverHubAI.progressUrl, window.location.href);
        url.searchParams.set('request_id', requestId);
        var response = await fetch(url.toString(), { method: 'GET', credentials: 'same-origin', headers: apiHeaders(), signal: controller.signal, cache: 'no-store' });
        if (response.ok) {
          var progress = await response.json();
          var stage = progress && progress.stage;
          var label = stage && DixcoverHubAI.labels[stage];
          if (label && stage !== lastStage && status.classList.contains('is-loading')) {
            status.textContent = label;
            lastStage = stage;
          }
        }
      } catch (error) {
        if (error && error.name === 'AbortError') return;
        // Progress is an enhancement; generation can still finish if polling is unavailable.
      }
      await waitForProgressPoll(controller.signal);
    }
  }
  function normalizeName(text) { return String(text || '').toLowerCase().replace(/&/g, 'and').replace(/[^a-z0-9]+/g, ' ').trim(); }
  function slugify(text) {
    text = String(text || '');
    if (typeof text.normalize === 'function') text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    return text.toLowerCase().replace(/&/g, ' and ').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 180).replace(/-+$/g, '');
  }
  function renderSlug() {
    var text = $('[data-slug-text]');
    var input = $('[data-slug-input]');
    if (text) text.textContent = '/' + (currentSlug || 'your-post-title');
    if (input && document.activeElement !== input) input.value = currentSlug;
  }
  function setSlug(slug, title, postStatus) {
    var savedSlug = slugify(slug);
    var titleSlug = slugify(title);
    currentSlug = savedSlug || titleSlug;
    slugCustomized = !!savedSlug && ('publish' === postStatus || savedSlug !== titleSlug);
    renderSlug();
  }
  function updateAutomaticSlug() {
    if (!slugCustomized && 'publish' !== activePostStatus) currentSlug = slugify(value('dh-ai-title'));
    else if (!currentSlug) currentSlug = slugify(value('dh-ai-title'));
    renderSlug();
  }
  function initializeSlugEditor() {
    var title = document.getElementById('dh-ai-title');
    var display = $('[data-slug-display]');
    var editButton = $('[data-slug-edit]');
    var editor = $('[data-slug-editor]');
    var input = $('[data-slug-input]');
    var saveButtonForSlug = $('[data-slug-save]');
    var cancelButton = $('[data-slug-cancel]');
    var help = $('[data-slug-error]');
    if (!display || !editButton || !editor || !input || !saveButtonForSlug || !cancelButton) return;

    function closeEditor() {
      editor.hidden = true;
      display.hidden = false;
      editButton.hidden = false;
      input.value = currentSlug;
      if (help) {
        help.classList.remove('is-error');
        help.textContent = 'WordPress uses this slug within your configured permalink structure.';
      }
      renderSlug();
    }
    function commitSlug() {
      var nextSlug = slugify(input.value);
      if (!nextSlug) {
        if (help) {
          help.classList.add('is-error');
          help.textContent = 'Add at least one letter or number to the slug.';
        }
        input.focus();
        return;
      }
      currentSlug = nextSlug;
      slugCustomized = true;
      closeEditor();
      markEditorDirty();
    }

    if (title) title.addEventListener('input', updateAutomaticSlug);
    input.addEventListener('input', function (event) { event.stopPropagation(); });
    input.addEventListener('change', function (event) { event.stopPropagation(); });
    editButton.addEventListener('click', function () {
      editor.hidden = false;
      display.hidden = true;
      editButton.hidden = true;
      input.value = currentSlug || slugify(value('dh-ai-title'));
      input.focus();
      input.select();
    });
    saveButtonForSlug.addEventListener('click', commitSlug);
    cancelButton.addEventListener('click', closeEditor);
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') { event.preventDefault(); commitSlug(); }
      if (event.key === 'Escape') { event.preventDefault(); closeEditor(); }
    });
    updateAutomaticSlug();
  }

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
          markEditorDirty();
          renderReadiness();
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

  function applyResult(data, shouldMarkDirty) {
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
    renderReadiness();
    if (shouldMarkDirty === false) {
      notice('Saved WordPress content loaded. Review or refine it, then save your changes.', 'success');
    } else {
      notice('Draft content is ready. Review the article, metadata, dates, application details and FAQs before saving.', 'success');
      markEditorDirty();
    }
  }

  $('[data-add-faq]').addEventListener('click', function () { if (faqRows.length >= 8) { notice('You can add up to 8 FAQs.', 'warning'); return; } faqRows.push({ question: '', answer: '' }); renderFaqs(); markEditorDirty(); });
  $('[data-add-application-link]').addEventListener('click', function () {
    if (applicationLinkRows.length >= 8) return;
    applicationLinkRows.push({ label: '', url: '' });
    renderApplicationLinks();
    markEditorDirty();
    var fields = app.querySelectorAll('[data-application-links] input[type="url"]');
    if (fields.length) fields[fields.length - 1].focus();
  });
  renderApplicationLinks();

  cancelGenerationButton.addEventListener('click', function () {
    if (activeGenerationController) activeGenerationController.abort();
  });

  $('[data-generate]').addEventListener('click', async function () {
    var title = value('dh-ai-title');
    if (!title) { notice('Enter the opportunity title first.', 'error'); document.getElementById('dh-ai-title').focus(); return; }
    var category = value('dh-ai-category');
    if (!category || Number(category) < 1) { notice('Choose the main opportunity category first.', 'error'); document.getElementById('dh-ai-category-choose').focus(); return; }
    var button = this; button.disabled = true; setEvidenceBusy(true);
    var controller = new AbortController();
    var progressController = new AbortController();
    var requestId = createGenerationRequestId();
    activeGenerationController = controller;
    generationTimedOut = false;
    cancelGenerationButton.hidden = false;
    notice(DixcoverHubAI.labels.working + ' This may take a few minutes.', 'info');
    status.classList.add('is-loading');
    generationTimeoutId = window.setTimeout(function () {
      generationTimedOut = true;
      controller.abort();
    }, 520000);
    var progressPoll = pollGenerationProgress(requestId, progressController);
    var form = new FormData();
    form.append('request_id', requestId);
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
      var response = await fetch(DixcoverHubAI.generateUrl, { method: 'POST', credentials: 'same-origin', headers: apiHeaders(), body: form, signal: controller.signal });
      var json = await response.json();
      if (!response.ok) throw new Error(json.message || 'AI generation failed. Check the server configuration and try again.');
      applyResult(json);
    } catch (error) {
      if (error && 'AbortError' === error.name) {
        notice(generationTimedOut ? 'Generation timed out. Add fewer sources or try again.' : 'Stopped waiting. Your current editor content was kept, and no draft was saved.', generationTimedOut ? 'error' : 'warning');
      } else {
        notice(error.message || 'AI generation failed. Please try again.', 'error');
      }
    } finally {
      progressController.abort();
      await progressPoll;
      window.clearTimeout(generationTimeoutId);
      generationTimeoutId = 0;
      activeGenerationController = null;
      cancelGenerationButton.hidden = true;
      status.classList.remove('is-loading');
      button.disabled = false;
      setEvidenceBusy(false);
    }
  });

  function opportunityPayload() {
    var base = lastResult || {};
    var applicationLinks = getApplicationLinks();
    var sources = Array.isArray(base.sources) ? base.sources.slice() : [];
    var sourceUrls = new Set(sources.map(function (source) { return String((source && source.url) || ''); }));
    splitLines(value('dh-ai-links')).forEach(function (sourceUrl) {
      try {
        var parsed = new URL(sourceUrl);
        if (!['http:', 'https:'].includes(parsed.protocol) || sourceUrls.has(parsed.href)) return;
        sourceUrls.add(parsed.href);
        sources.push({ title: parsed.hostname, url: parsed.href });
      } catch (_) { /* Ignore incomplete source URLs until the editor adds a valid link. */ }
    });
    return {
      summary: value('dh-ai-summary'), metaTitle: value('dh-ai-meta-title'), metaDescription: value('dh-ai-meta-description'), focusKeyword: value('dh-ai-focus-keyword'),
      providerName: value('dh-ai-provider'), providerAbout: value('dh-ai-provider-about'), providerWebsite: value('dh-ai-provider-website'), providerEmail: value('dh-ai-provider-email'), providerSocialProfiles: splitLines(value('dh-ai-provider-social')),
      employmentType: value('dh-ai-employment'), location: value('dh-ai-location'), deadline: value('dh-ai-deadline'), duration: value('dh-ai-duration'), salary: value('dh-ai-salary'),
      applicationMethod: value('dh-ai-application-method'), applicationLink: (applicationLinks[0] || {}).url || '', applicationLinks: applicationLinks, applicationEmail: value('dh-ai-application-email'),
      requirements: splitLines(value('dh-ai-requirements')), benefits: splitLines(value('dh-ai-benefits')), faqs: faqRows, sources: sources,
      categoryNames: base.categoryNames || [], typeNames: splitTags(value('dh-ai-types')), levelNames: splitTags(value('dh-ai-levels')), modeNames: splitTags(value('dh-ai-modes')), locationNames: splitTags(value('dh-ai-locations')), tagNames: splitTags(value('dh-ai-tags'))
    };
  }
  function splitTags(text) { return text.split(',').map(function (tag) { return tag.trim(); }).filter(Boolean).slice(0, 20); }

  function renderReadiness() {
    var panel = $('[data-readiness]');
    if (!panel) return;
    var article = document.createElement('div');
    article.innerHTML = getEditorContent() || '';
    var articleText = String(article.textContent || '').replace(/\s+/g, ' ').trim();
    var wordCount = articleText ? articleText.split(' ').length : 0;
    var hasApplication = getApplicationLinks().length > 0 || !!value('dh-ai-application-email');
    var hasCategory = app.querySelectorAll('input[name="dh_ai_categories[]"]:checked').length > 0;
    var hasSeo = !!(value('dh-ai-meta-title') && value('dh-ai-meta-description') && value('dh-ai-focus-keyword'));
    var checks = {
      title: !!value('dh-ai-title'),
      provider: !!value('dh-ai-provider'),
      application: hasApplication,
      content: wordCount >= 550,
      category: hasCategory,
      seo: hasSeo,
      image: selectedFeaturedImage > 0
    };
    var keys = Object.keys(checks);
    var completed = keys.filter(function (key) { return checks[key]; }).length;
    var score = Math.round((completed / keys.length) * 100);
    var scoreNode = $('[data-readiness-score]');
    var countNode = $('[data-readiness-count]');
    var track = $('.dh-ai-readiness-track');
    var bar = $('[data-readiness-bar]');
    if (scoreNode) scoreNode.textContent = String(score);
    if (countNode) countNode.textContent = completed + ' of ' + keys.length + ' checks complete';
    if (track) track.setAttribute('aria-valuenow', String(score));
    if (bar) bar.style.width = score + '%';
    panel.querySelectorAll('[data-readiness-item]').forEach(function (item) {
      var complete = !!checks[item.dataset.readinessItem];
      item.classList.toggle('is-complete', complete);
      var indicator = item.querySelector('[data-readiness-icon]');
      if (indicator) indicator.textContent = complete ? '✓' : '○';
    });
  }

  var saveButton = $('[data-save]');
  var publishButton = $('[data-publish]');
  var activePostStatus = 'draft';
  var saveHint = $('[data-save-hint]');
  autoSaveState = $('[data-autosave-state]');

  function updatePostActions() {
    var isPublished = activePostId > 0 && activePostStatus === 'publish';
    saveButton.hidden = isPublished && !!publishButton;
    saveButton.textContent = activePostId
      ? (isPublished ? 'Update published post' : 'Update WordPress draft')
      : 'Save as WordPress draft';
    if (publishButton) {
      publishButton.hidden = false;
      publishButton.textContent = isPublished ? 'Update published post' : 'Publish opportunity';
    }
    var stateLabel = $('[data-post-state]');
    if (stateLabel) stateLabel.textContent = isPublished ? 'Published' : (activePostId ? 'WordPress draft' : 'Draft only');
  }

  function updateReviewWarning() {
    var title = $('[data-review-warning-title]');
    var message = $('[data-review-warning-text]');
    if (!title || !message) return;
    if (activePostId && activePostStatus === 'publish') {
      title.textContent = 'This opportunity is public';
      message.textContent = 'Edits autosave after 30 seconds and update the live page. Review carefully before changing published content.';
    } else if (activePostId) {
      title.textContent = 'WordPress draft saved';
      message.textContent = 'Edits autosave after 30 seconds. Publishing still requires an explicit action.';
    } else {
      title.textContent = 'Review before publishing';
      message.textContent = 'Generated content is not saved yet. Check dates, eligibility, pay, links, and every claim against the source, then save a draft or publish it explicitly.';
    }
  }

  function updatePostLinks(record) {
    ['[data-open-draft]', '[data-open-preview]', '[data-open-public]'].forEach(function (selector) {
      var oldLink = $(selector);
      if (oldLink) oldLink.remove();
    });
    var links = [];
    if (record.editUrl) links.push({ key: 'openDraft', url: record.editUrl, label: 'Continue in WordPress editor' });
    if (record.status === 'publish' && record.publicUrl) {
      links.push({ key: 'openPublic', url: record.publicUrl, label: 'View published post' });
    } else if (record.previewUrl) {
      links.push({ key: 'openPreview', url: record.previewUrl, label: 'Preview draft' });
    }
    links.forEach(function (item) {
      var link = document.createElement('a');
      link.dataset[item.key] = '1';
      link.href = item.url;
      link.className = 'dh-ai-edit-link';
      link.textContent = item.label;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      saveHint.appendChild(link);
    });
  }

  async function loadExistingPost() {
    var requestedPostId = Number(DixcoverHubAI.postId || 0);
    if (!requestedPostId) return;
    var startingVersion = editorChangeVersion;
    notice('Loading the selected WordPress post…', 'info');
    app.setAttribute('aria-busy', 'true');
    try {
      var response = await fetch(DixcoverHubAI.loadUrl + encodeURIComponent(requestedPostId), {
        credentials: 'same-origin',
        headers: apiHeaders()
      });
      var data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Could not load this WordPress post.');
      if (startingVersion !== editorChangeVersion) {
        throw new Error('The editor changed while the post was loading. Reopen it before editing.');
      }
      suspendDirtyTracking = true;
      activePostId = Number(data.id);
      activePostStatus = data.status === 'publish' ? 'publish' : 'draft';
      setValue('dh-ai-title', data.title);
      setSlug(data.slug, data.title, data.status);
      setValue('dh-ai-category', data.categoryId || '');
      setValue('dh-ai-mode', 'refine');
      var featuredToggle = $('[data-featured-toggle]');
      if (featuredToggle) featuredToggle.checked = !!data.featured;
      setValue('dh-ai-provider-website-source', data.providerWebsite);
      setValue('dh-ai-application-email-source', data.applicationEmail);
      setValue('dh-ai-links', (data.sources || []).map(function (source) { return source.url; }).join('\n'));
      selectedFeaturedImage = 0;
      var preview = $('[data-featured-preview]');
      if (preview) preview.replaceChildren();
      applyResult(data, false);
      if (data.featuredImageId && data.featuredImageUrl) {
        showFeaturedImage(data.featuredImageId, data.featuredImageUrl, data.featuredImageAlt, data.featuredImageThumbnail);
      }
      var pageTitle = $('[data-editor-title]');
      var workspaceTitle = $('[data-editor-workspace-title]');
      if (pageTitle) pageTitle.textContent = 'Edit Opportunity';
      if (workspaceTitle) workspaceTitle.textContent = 'Edit the opportunity';
      updatePostActions();
      updateReviewWarning();
      saveHint.textContent = activePostStatus === 'publish' ? 'Published opportunity loaded.' : 'WordPress draft loaded.';
      updatePostLinks(data);
      editorDirty = false;
      autoSaveFailed = false;
      setAutoSaveState('Saved in WordPress');
      notice('WordPress post loaded. Review or refine its content; changes autosave or you can save them now.', 'success');
    } catch (error) {
      notice(error.message || 'Could not load this WordPress post.', 'error');
    } finally {
      suspendDirtyTracking = false;
      app.removeAttribute('aria-busy');
    }
  }

  async function savePost(status, button, autosave) {
    autosave = !!autosave;
    if (saveInProgress) return;
    if (autosave && !activePostId) return;
    var title = value('dh-ai-title');
    var content = getEditorContent();
    if (!title || !content.trim()) {
      if (autosave) {
        autoSaveFailed = true;
        setAutoSaveState('Autosave could not run. Add a title and article body, then save again.', 'error');
      } else {
        notice('Add a title and article body before saving.', 'error');
      }
      return;
    }
    window.clearTimeout(autoSaveTimer);
    autoSaveTimer = 0;
    saveInProgress = true;
    var savedVersion = editorChangeVersion;
    var saveButtonForLabel = button || saveButton;
    var categories = Array.prototype.map.call(app.querySelectorAll('input[name="dh_ai_categories[]"]:checked'), function (item) { return Number(item.value); });
    var payload = {
      postId: activePostId, status: status, title: title, content: content, excerpt: value('dh-ai-excerpt'), categoryIds: categories,
      slug: currentSlug || slugify(title),
      tags: splitTags(value('dh-ai-tags')), opportunity: opportunityPayload(), featuredImageId: selectedFeaturedImage,
      featured: !!($('[data-featured-toggle]') && $('[data-featured-toggle]').checked)
    };
    saveButton.disabled = true;
    if (publishButton) publishButton.disabled = true;
    if (autosave) {
      setAutoSaveState('Saving automatically…', 'saving');
    } else {
      autoSaveFailed = false;
      saveButtonForLabel.textContent = status === 'publish' ? 'Publishing...' : 'Saving draft...';
    }
    try {
      var response = await fetch(DixcoverHubAI.saveUrl, { method: 'POST', credentials: 'same-origin', headers: Object.assign({ 'Content-Type': 'application/json' }, apiHeaders()), body: JSON.stringify(payload) });
      var json = await response.json();
      if (!response.ok) throw new Error(json.message || 'Could not save the post.');
      activePostId = Number(json.id);
      activePostStatus = json.status === 'publish' ? 'publish' : 'draft';
      if (json.slug && editorChangeVersion === savedVersion) {
        currentSlug = slugify(json.slug);
        if ('publish' === activePostStatus) slugCustomized = true;
        renderSlug();
      }
      if (!autosave) {
        saveHint.textContent = json.message;
        notice(json.message, 'success');
      }
      updatePostActions();
      updateReviewWarning();
      updatePostLinks(json);
      if (editorChangeVersion === savedVersion) {
        editorDirty = false;
        autoSaveFailed = false;
        setAutoSaveState(autosave ? 'Saved automatically' : 'Saved to WordPress');
      } else {
        editorDirty = true;
        setAutoSaveState('Unsaved changes');
      }
    } catch (error) {
      editorDirty = true;
      autoSaveFailed = true;
      if (autosave) {
        setAutoSaveState('Autosave failed. Use a save button to retry.', 'error');
      } else {
        notice(error.message || 'Could not save the post.', 'error');
        setAutoSaveState('Save failed; changes remain in this editor.', 'error');
      }
      updatePostActions();
    } finally {
      saveInProgress = false;
      saveButton.disabled = false;
      if (publishButton) publishButton.disabled = false;
      if (editorDirty && !autoSaveFailed && activePostId) queueAutoSave();
    }
  }

  saveButton.addEventListener('click', function () {
    savePost(activePostId ? activePostStatus : 'draft', saveButton, false);
  });
  if (publishButton) publishButton.addEventListener('click', function () { savePost('publish', publishButton, false); });
  window.addEventListener('keydown', function (event) {
    if (!(event.metaKey || event.ctrlKey) || event.key.toLowerCase() !== 's') return;
    event.preventDefault();
    var statusToSave = activePostId ? activePostStatus : 'draft';
    var button = statusToSave === 'publish' && publishButton ? publishButton : saveButton;
    savePost(statusToSave, button, false);
  });
  app.addEventListener('input', function () { markEditorDirty(); renderReadiness(); });
  app.addEventListener('change', function () { markEditorDirty(); renderReadiness(); });
  function bindTinyMCEAutosave(editor) {
    if (!editor || editor.id !== 'dixcoverhub_ai_content' || editor._dhAutosaveBound) return;
    editor._dhAutosaveBound = true;
    editor.on('input change keyup', function () { markEditorDirty(); renderReadiness(); });
  }
  if (window.tinymce) {
    var currentEditor = window.tinymce.get('dixcoverhub_ai_content');
    if (currentEditor) bindTinyMCEAutosave(currentEditor);
    window.tinymce.on('AddEditor', function (event) { bindTinyMCEAutosave(event.editor); });
  }
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
    var edit = document.createElement('button');
    edit.type = 'button';
    edit.className = 'button-link dh-ai-image-edit';
    edit.textContent = 'Edit image';
    edit.dataset.imageId = String(selectedFeaturedImage);
    edit.dataset.imageUrl = imageUrl;
    edit.dataset.imageAlt = alt || '';
    try { edit.dataset.imageFilename = decodeURIComponent(new URL(imageUrl, window.location.href).pathname.split('/').pop() || 'featured-image'); }
    catch (_) { edit.dataset.imageFilename = 'featured-image'; }
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
      markEditorDirty();
      renderReadiness();
    });
    app.querySelectorAll('[data-ai-feature-image]').forEach(function (button) {
      var isSelected = Number(button.dataset.attachmentId) === selectedFeaturedImage;
      button.textContent = isSelected ? 'Selected' : 'Use as featured';
      button.disabled = false;
    });
    preview.append(img, label, edit, clear);
    markEditorDirty();
    renderReadiness();
  }

  window.addEventListener('dixcoverhub-ai-featured-image-update', function (event) {
    var image = event.detail || {};
    if (image.id && image.url) showFeaturedImage(image.id, image.url, image.alt || '', image.thumbnail || image.url);
  });

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
      if (file.size < 1 || file.size > maxImageBytes) { skipped.push(file.name + ': maximum image size for this WordPress server is ' + maxImageLabel); return; }
      if (totalBytes + file.size > maxTotalImageBytes) { skipped.push(file.name + ': combined image limit for this WordPress server is ' + maxTotalImageLabel); return; }
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

  initializeSlugEditor();
  renderReadiness();
  loadExistingPost();
})();
