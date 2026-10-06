(function () {
  'use strict';

  var root = document.querySelector('[data-ai-image-editor]');
  var app = document.querySelector('.dh-ai-app');
  if (!root || !app || !window.DixcoverHubAI) return;

  var dialog = root.querySelector('.dh-ai-image-editor-dialog');
  var stage = root.querySelector('[data-ai-image-stage]');
  var canvasView = root.querySelector('[data-ai-image-canvas]');
  var preview = root.querySelector('[data-ai-image-preview]');
  var cropBox = root.querySelector('[data-ai-image-crop-box]');
  var status = root.querySelector('[data-ai-image-editor-status]');
  var saveButton = root.querySelector('[data-ai-image-save]');
  var state = null;
  var sourceImage = null;
  var previousFocus = null;
  var saving = false;
  var drag = null;
  var loadRequestId = 0;
  var editorMode = 'edit';
  var collage = { sections: 2, layout: 'split-v', slots: [null, null, null, null], aspect: '16:9', gap: 6, radius: 0, background: '#ffffff', border: 0, borderColor: '#cbd5e1' };
  var collageImageCache = Object.create(null);
  var collageDrawRequest = 0;
  var collageCanvas = root.querySelector('[data-ai-collage-canvas]');
  var ratioValues = { '1:1': 1, '16:9': 16 / 9, '4:3': 4 / 3, '9:16': 9 / 16, '2:1': 2 };

  function cleanNumber(value, fallback) {
    var number = Number(value);
    return Number.isFinite(number) ? number : fallback;
  }

  function say(message, kind) {
    status.textContent = message || '';
    status.className = 'dh-ai-image-editor-status' + (kind ? ' is-' + kind : '');
  }

  function collageDimensions(aspect, preview) {
    var width = preview ? 800 : 1600;
    var height = aspect === '1:1' ? (preview ? 800 : 1200) : aspect === '4:3' ? (preview ? 600 : 1200) : (preview ? 450 : 900);
    if (aspect === '1:1') width = preview ? 800 : 1200;
    return { width: width, height: height };
  }

  function collageRects(width, height, gap) {
    var g = gap;
    if (collage.sections === 2) {
      if (collage.layout === 'split-h') {
        var halfH = (height - g * 3) / 2;
        return [{ x: g, y: g, w: width - g * 2, h: halfH }, { x: g, y: g * 2 + halfH, w: width - g * 2, h: halfH }];
      }
      var halfW = (width - g * 3) / 2;
      return [{ x: g, y: g, w: halfW, h: height - g * 2 }, { x: g * 2 + halfW, y: g, w: halfW, h: height - g * 2 }];
    }
    if (collage.sections === 3) {
      if (collage.layout === 'rows-3') {
        var rowH = (height - g * 4) / 3;
        return [0, 1, 2].map(function (index) { return { x: g, y: g * (index + 1) + rowH * index, w: width - g * 2, h: rowH }; });
      }
      if (collage.layout === 'hero-top') {
        var topAvailable = height - g * 3;
        var topH = topAvailable * 0.55;
        var bottomH = topAvailable * 0.45;
        var bottomW = (width - g * 3) / 2;
        return [{ x: g, y: g, w: width - g * 2, h: topH }, { x: g, y: g * 2 + topH, w: bottomW, h: bottomH }, { x: g * 2 + bottomW, y: g * 2 + topH, w: bottomW, h: bottomH }];
      }
      if (collage.layout === 'hero-left') {
        var availableW = width - g * 3;
        var leftW = availableW * 0.58;
        var rightW = availableW * 0.42;
        var sideH = (height - g * 3) / 2;
        return [{ x: g, y: g, w: leftW, h: height - g * 2 }, { x: g * 2 + leftW, y: g, w: rightW, h: sideH }, { x: g * 2 + leftW, y: g * 2 + sideH, w: rightW, h: sideH }];
      }
      var colW = (width - g * 4) / 3;
      return [0, 1, 2].map(function (index) { return { x: g * (index + 1) + colW * index, y: g, w: colW, h: height - g * 2 }; });
    }
    if (collage.layout === 'hero-left-3') {
      var heroAvailableW = width - g * 3;
      var heroW = heroAvailableW * 0.6;
      var stripW = heroAvailableW * 0.4;
      var stripH = (height - g * 4) / 3;
      return [{ x: g, y: g, w: heroW, h: height - g * 2 }, { x: g * 2 + heroW, y: g, w: stripW, h: stripH }, { x: g * 2 + heroW, y: g * 2 + stripH, w: stripW, h: stripH }, { x: g * 2 + heroW, y: g * 3 + stripH * 2, w: stripW, h: stripH }];
    }
    var gridW = (width - g * 3) / 2;
    var gridH = (height - g * 3) / 2;
    return [{ x: g, y: g, w: gridW, h: gridH }, { x: g * 2 + gridW, y: g, w: gridW, h: gridH }, { x: g, y: g * 2 + gridH, w: gridW, h: gridH }, { x: g * 2 + gridW, y: g * 2 + gridH, w: gridW, h: gridH }];
  }

  function loadCollageImage(url) {
    if (collageImageCache[url]) return collageImageCache[url];
    collageImageCache[url] = new Promise(function (resolve, reject) {
      var image = new Image();
      image.crossOrigin = 'anonymous';
      image.onload = function () { resolve(image); };
      image.onerror = function () { delete collageImageCache[url]; reject(new Error('One of the selected images could not be loaded.')); };
      image.src = url;
    });
    return collageImageCache[url];
  }

  function drawRoundedPath(context, x, y, width, height, radius) {
    if (radius > 0 && typeof context.roundRect === 'function') context.roundRect(x, y, width, height, Math.min(radius, width / 2, height / 2));
    else context.rect(x, y, width, height);
  }

  async function drawCollage(canvas, width, height, strict) {
    var requestId = ++collageDrawRequest;
    canvas.width = width;
    canvas.height = height;
    var context = canvas.getContext('2d');
    if (!context) throw new Error('This browser could not prepare the collage.');
    context.fillStyle = collage.background;
    context.fillRect(0, 0, width, height);
    var ratio = width / 800;
    var gap = collage.gap * ratio;
    var radius = collage.radius * ratio;
    var border = collage.border * ratio;
    var rects = collageRects(width, height, gap);
    var images = await Promise.all(collage.slots.slice(0, collage.sections).map(function (slot) { return slot ? loadCollageImage(slot.url).catch(function (error) { if (strict) throw error; return null; }) : Promise.resolve(null); }));
    if (requestId !== collageDrawRequest) return;
    rects.forEach(function (rect, index) {
      var image = images[index];
      context.save();
      context.beginPath();
      drawRoundedPath(context, rect.x, rect.y, rect.w, rect.h, radius);
      context.clip();
      if (image) {
        var scale = Math.max(rect.w / image.naturalWidth, rect.h / image.naturalHeight);
        var drawWidth = image.naturalWidth * scale;
        var drawHeight = image.naturalHeight * scale;
        context.drawImage(image, rect.x + (rect.w - drawWidth) / 2, rect.y + (rect.h - drawHeight) / 2, drawWidth, drawHeight);
      } else {
        context.fillStyle = collage.background.toLowerCase() === '#ffffff' ? 'rgba(20,15,24,.055)' : 'rgba(255,255,255,.08)';
        context.fillRect(rect.x, rect.y, rect.w, rect.h);
      }
      context.restore();
      if (border > 0) {
        context.save();
        context.beginPath();
        drawRoundedPath(context, rect.x, rect.y, rect.w, rect.h, radius);
        context.lineWidth = border;
        context.strokeStyle = collage.borderColor;
        context.stroke();
        context.restore();
      }
    });
    return canvas;
  }

  function updateCollagePreview() {
    if (!collageCanvas) return;
    var dimensions = collageDimensions(collage.aspect, true);
    collageCanvas.style.aspectRatio = dimensions.width + ' / ' + dimensions.height;
    root.querySelector('[data-ai-collage-preview-size]').textContent = collage.aspect === '1:1' ? '1200 × 1200' : collage.aspect === '4:3' ? '1600 × 1200' : '1600 × 900';
    var count = collage.slots.slice(0, collage.sections).filter(Boolean).length;
    root.querySelector('[data-ai-collage-count]').textContent = count + ' of ' + collage.sections + ' images selected';
    root.querySelector('[data-ai-collage-preview-status]').textContent = count < 2 ? 'Choose at least two images to create a collage' : 'Preview updates as you adjust the layout';
    saveButton.disabled = editorMode === 'collage' ? count < 2 || saving : !sourceImage || saving;
    drawCollage(collageCanvas, dimensions.width, dimensions.height).catch(function (error) { if (editorMode === 'collage') say(error.message, 'error'); });
  }

  function renderCollageSlots() {
    root.querySelectorAll('[data-ai-collage-slot]').forEach(function (row) {
      var index = Number(row.dataset.aiCollageSlot);
      var image = collage.slots[index];
      row.hidden = index >= collage.sections;
      row.classList.toggle('has-image', Boolean(image));
      var previewBox = row.querySelector('[data-ai-collage-slot-preview]');
      previewBox.replaceChildren();
      if (image) {
        var thumbnail = document.createElement('img');
        thumbnail.src = image.thumbnail || image.url;
        thumbnail.alt = '';
        previewBox.appendChild(thumbnail);
      }
      row.querySelector('[data-ai-collage-slot-name]').textContent = image ? image.filename : 'No image selected';
      row.querySelector('[data-ai-collage-remove]').hidden = !image;
      row.querySelector('[data-ai-collage-select]').textContent = image ? 'Replace' : 'Choose image';
    });
    updateCollagePreview();
  }

  function resetCollage() {
    collage = { sections: 2, layout: 'split-v', slots: [null, null, null, null], aspect: '16:9', gap: 6, radius: 0, background: '#ffffff', border: 0, borderColor: '#cbd5e1' };
    root.querySelectorAll('[data-ai-collage-sections]').forEach(function (button) { var active = button.dataset.aiCollageSections === '2'; button.classList.toggle('is-active', active); button.setAttribute('aria-pressed', active ? 'true' : 'false'); });
    root.querySelectorAll('[data-ai-collage-layout]').forEach(function (button) { var active = button.dataset.aiCollageLayout === 'split-v'; button.classList.toggle('is-active', active); button.setAttribute('aria-pressed', active ? 'true' : 'false'); });
    root.querySelectorAll('[data-ai-collage-layout-set]').forEach(function (set) { set.hidden = set.dataset.aiCollageLayoutSet !== '2'; });
    root.querySelectorAll('[data-ai-collage-aspect]').forEach(function (button) { var active = button.dataset.aiCollageAspect === '16:9'; button.classList.toggle('is-active', active); button.setAttribute('aria-pressed', active ? 'true' : 'false'); });
    root.querySelectorAll('[data-ai-collage-adjust]').forEach(function (input) { input.value = input.dataset.aiCollageAdjust === 'gap' ? '6' : '0'; var output = root.querySelector('[data-ai-collage-value="' + input.dataset.aiCollageAdjust + '"]'); if (output) output.textContent = input.value + ' px'; });
    root.querySelector('[data-ai-collage-color="background"]').value = '#ffffff';
    root.querySelector('[data-ai-collage-color="border"]').value = '#cbd5e1';
    root.querySelector('[data-ai-collage-alt]').value = '';
    renderCollageSlots();
  }

  function setEditorMode(mode) {
    editorMode = mode === 'collage' ? 'collage' : 'edit';
    root.querySelector('[data-ai-image-edit-view]').hidden = editorMode !== 'edit';
    root.querySelector('[data-ai-image-collage-view]').hidden = editorMode !== 'collage';
    root.querySelectorAll('[data-ai-image-mode]').forEach(function (button) {
      var active = button.dataset.aiImageMode === editorMode;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
      button.disabled = button.dataset.aiImageMode === 'edit' && !sourceImage;
    });
    root.querySelector('[data-ai-image-reset]').textContent = editorMode === 'collage' ? 'Reset collage' : 'Reset edits';
    root.querySelector('#dh-ai-image-editor-title').textContent = editorMode === 'collage' ? 'Create a featured-image collage' : 'Edit featured image';
    saveButton.textContent = editorMode === 'collage' ? 'Create collage' : 'Save edited image';
    saveButton.disabled = saving || (editorMode === 'collage' ? collage.slots.slice(0, collage.sections).filter(Boolean).length < 2 : !sourceImage);
    if (editorMode === 'collage') updateCollagePreview();
  }

  function filterString() {
    if (!state) return 'none';
    var value = 'brightness(' + state.brightness + '%) contrast(' + state.contrast + '%) saturate(' + state.saturation + '%)';
    if (state.filter === 'bw') value += ' grayscale(100%)';
    if (state.filter === 'warm') value += ' sepia(35%)';
    if (state.filter === 'cool') value += ' hue-rotate(180deg)';
    if (state.filter === 'vibrant') value += ' saturate(140%) contrast(110%)';
    if (state.filter === 'dramatic') value += ' contrast(135%) brightness(95%)';
    return value;
  }

  function setCropBox() {
    if (!state) return;
    cropBox.style.left = state.cropX + '%';
    cropBox.style.top = state.cropY + '%';
    cropBox.style.width = state.cropW + '%';
    cropBox.style.height = state.cropH + '%';
    cropBox.setAttribute('aria-valuenow', String(Math.round(Math.min(state.cropW, state.cropH))));
  }

  function fitPreview() {
    if (!sourceImage || !stage || !canvasView) return;
    var availableWidth = Math.max(80, stage.clientWidth - 36);
    var availableHeight = Math.max(80, stage.clientHeight - 36);
    var scale = Math.min(availableWidth / sourceImage.naturalWidth, availableHeight / sourceImage.naturalHeight, 1);
    var width = Math.max(1, Math.round(sourceImage.naturalWidth * scale));
    var height = Math.max(1, Math.round(sourceImage.naturalHeight * scale));
    canvasView.style.width = width + 'px';
    canvasView.style.height = height + 'px';
    preview.style.filter = filterString();
    preview.style.transform = 'rotate(' + state.rotation + 'deg) scale(' + (state.flipX ? '-1' : '1') + ',' + (state.flipY ? '-1' : '1') + ')';
    setCropBox();
  }

  function resetState() {
    state.cropX = 0;
    state.cropY = 0;
    state.cropW = 100;
    state.cropH = 100;
    state.ratio = 'free';
    state.rotation = 0;
    state.flipX = false;
    state.flipY = false;
    state.brightness = 100;
    state.contrast = 100;
    state.saturation = 100;
    state.filter = 'none';
    root.querySelectorAll('[data-ai-image-ratio]').forEach(function (button) {
      button.classList.toggle('is-active', button.dataset.aiImageRatio === 'free');
    });
    root.querySelectorAll('[data-ai-image-adjust]').forEach(function (input) {
      input.value = '100';
      var output = root.querySelector('[data-ai-image-value="' + input.dataset.aiImageAdjust + '"]');
      if (output) output.textContent = '100%';
    });
    root.querySelectorAll('[data-ai-image-filter]').forEach(function (button) {
      var active = button.dataset.aiImageFilter === 'none';
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    fitPreview();
  }

  function setActiveTool(tool) {
    root.querySelectorAll('[data-ai-image-tool]').forEach(function (button) {
      var active = button.dataset.aiImageTool === tool;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
      button.tabIndex = active ? 0 : -1;
    });
    root.querySelectorAll('[data-ai-image-panel]').forEach(function (panel) {
      var active = panel.dataset.aiImagePanel === tool;
      panel.hidden = !active;
      panel.classList.toggle('is-active', active);
    });
    cropBox.hidden = tool !== 'crop';
  }

  function applyRatio(ratio) {
    if (!state || !sourceImage) return;
    state.ratio = ratio;
    if (ratio === 'free') {
      state.cropX = 0;
      state.cropY = 0;
      state.cropW = 100;
      state.cropH = 100;
    } else {
      var target = ratioValues[ratio];
      var imageRatio = sourceImage.naturalWidth / sourceImage.naturalHeight;
      if (imageRatio > target) {
        state.cropW = Math.min(100, Math.round((target / imageRatio) * 100));
        state.cropH = 100;
        state.cropX = Math.round((100 - state.cropW) / 2);
        state.cropY = 0;
      } else {
        state.cropW = 100;
        state.cropH = Math.min(100, Math.round((imageRatio / target) * 100));
        state.cropX = 0;
        state.cropY = Math.round((100 - state.cropH) / 2);
      }
    }
    root.querySelectorAll('[data-ai-image-ratio]').forEach(function (button) {
      button.classList.toggle('is-active', button.dataset.aiImageRatio === ratio);
    });
    setCropBox();
  }

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function openEditor(button) {
    var requestId = ++loadRequestId;
    previousFocus = button;
    state = {
      id: Number(button.dataset.imageId) || 0,
      url: button.dataset.imageUrl || '',
      alt: button.dataset.imageAlt || '',
      filename: button.dataset.imageFilename || 'featured-image',
      cropX: 0,
      cropY: 0,
      cropW: 100,
      cropH: 100,
      ratio: 'free',
      rotation: 0,
      flipX: false,
      flipY: false,
      brightness: 100,
      contrast: 100,
      saturation: 100,
      filter: 'none'
    };
    sourceImage = null;
    preview.removeAttribute('src');
    root.hidden = false;
    root.setAttribute('aria-hidden', 'false');
    document.body.classList.add('dh-ai-image-editor-open');
    resetCollage();
    setEditorMode('edit');
    setActiveTool('crop');
    saveButton.disabled = true;
    say('Loading image…', 'info');
    dialog.focus();

    var image = new Image();
    image.crossOrigin = 'anonymous';
    image.onload = function () {
      if (requestId !== loadRequestId || root.hidden) return;
      sourceImage = image;
      preview.src = state.url;
      preview.alt = state.alt;
      setEditorMode(editorMode);
      resetState();
      window.requestAnimationFrame(fitPreview);
      saveButton.disabled = editorMode === 'collage' ? collage.slots.slice(0, collage.sections).filter(Boolean).length < 2 : false;
      say('Image ready to edit.', 'info');
      root.querySelector('[data-ai-image-editor-close]:not(.dh-ai-image-editor-backdrop)').focus();
    };
    image.onerror = function () {
      if (requestId !== loadRequestId || root.hidden) return;
      sourceImage = null;
      setEditorMode(editorMode);
      saveButton.disabled = editorMode === 'collage' ? collage.slots.slice(0, collage.sections).filter(Boolean).length < 2 : true;
      say('The image could not be loaded for editing. Check that it is available in the WordPress Media Library.', 'error');
    };
    image.src = state.url;
  }

  function openCollage(button) {
    previousFocus = button;
    state = null;
    sourceImage = null;
    preview.removeAttribute('src');
    root.hidden = false;
    root.setAttribute('aria-hidden', 'false');
    document.body.classList.add('dh-ai-image-editor-open');
    resetCollage();
    setEditorMode('collage');
    say('Choose at least two images from your Media Library.', 'info');
    saveButton.disabled = true;
    dialog.focus();
    window.requestAnimationFrame(function () { root.querySelector('[data-ai-collage-select="0"]').focus(); });
  }

  function closeEditor() {
    if (saving) return;
    loadRequestId += 1;
    root.hidden = true;
    root.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('dh-ai-image-editor-open');
    preview.removeAttribute('src');
    sourceImage = null;
    state = null;
    if (previousFocus && document.contains(previousFocus)) previousFocus.focus();
  }

  function drawBlob() {
    return new Promise(function (resolve, reject) {
      if (!sourceImage || !state) return reject(new Error('Load an image before saving edits.'));
      var sourceX = Math.round((state.cropX / 100) * sourceImage.naturalWidth);
      var sourceY = Math.round((state.cropY / 100) * sourceImage.naturalHeight);
      var sourceWidth = Math.max(1, Math.round((state.cropW / 100) * sourceImage.naturalWidth));
      var sourceHeight = Math.max(1, Math.round((state.cropH / 100) * sourceImage.naturalHeight));
      var rotated = Math.abs(state.rotation % 180) === 90;
      var scale = Math.min(1, 2560 / Math.max(sourceWidth, sourceHeight));
      var width = Math.max(1, Math.round((rotated ? sourceHeight : sourceWidth) * scale));
      var height = Math.max(1, Math.round((rotated ? sourceWidth : sourceHeight) * scale));
      var canvas = document.createElement('canvas');
      canvas.width = width;
      canvas.height = height;
      var context = canvas.getContext('2d');
      if (!context) return reject(new Error('This browser could not prepare the image.'));
      context.translate(width / 2, height / 2);
      context.rotate((state.rotation * Math.PI) / 180);
      context.scale(state.flipX ? -1 : 1, state.flipY ? -1 : 1);
      context.filter = filterString();
      context.drawImage(sourceImage, sourceX, sourceY, sourceWidth, sourceHeight, -(sourceWidth * scale) / 2, -(sourceHeight * scale) / 2, sourceWidth * scale, sourceHeight * scale);
      var type = 'image/webp';
      canvas.toBlob(function (blob) {
        if (blob) return resolve(blob);
        type = 'image/jpeg';
        canvas.toBlob(function (fallbackBlob) {
          if (fallbackBlob) resolve(fallbackBlob);
          else reject(new Error('The edited image could not be exported.'));
        }, type, 0.9);
      }, type, 0.92);
    });
  }

  async function saveEditedImage() {
    if (editorMode === 'collage') return saveCollage();
    if (saving || !state || !sourceImage) return;
    saving = true;
    saveButton.disabled = true;
    root.querySelectorAll('button,input').forEach(function (control) {
      if (control !== saveButton) control.disabled = true;
    });
    say('Applying edits and saving a new Media Library image…', 'info');
    try {
      var blob = await drawBlob();
      var extension = blob.type === 'image/webp' ? 'webp' : (blob.type === 'image/png' ? 'png' : 'jpg');
      var filename = (state.filename || 'featured-image').replace(/\.[a-z0-9]+$/i, '').replace(/[^a-z0-9_-]+/gi, '-') || 'featured-image';
      var file = new File([blob], filename + '-edited.' + extension, { type: blob.type, lastModified: Date.now() });
      var form = new FormData();
      form.append('file', file, file.name);
      form.append('alt_text', state.alt || '');
      var response = await fetch(DixcoverHubAI.imageSaveUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': DixcoverHubAI.nonce },
        body: form
      });
      var result = await response.json();
      if (!response.ok || !result.id || !result.url) throw new Error(result.message || 'WordPress could not save the edited image.');
      window.dispatchEvent(new CustomEvent('dixcoverhub-ai-featured-image-update', { detail: {
        id: result.id,
        url: result.url,
        alt: state.alt || result.alt || '',
        thumbnail: result.thumbnail || result.url,
        filename: file.name
      } }));
      say('Edited image saved and selected for this opportunity.', 'success');
      saving = false;
      closeEditor();
    } catch (error) {
      say(error && error.message ? error.message : 'The edited image could not be saved.', 'error');
    } finally {
      saving = false;
      root.querySelectorAll('button,input').forEach(function (control) { control.disabled = false; });
      setEditorMode(editorMode);
    }
  }

  async function saveCollage() {
    var filled = collage.slots.slice(0, collage.sections).filter(Boolean).length;
    if (saving || filled < 2) return;
    saving = true;
    saveButton.disabled = true;
    root.querySelectorAll('button,input').forEach(function (control) { if (control !== saveButton) control.disabled = true; });
    say('Rendering and saving the collage to your Media Library…', 'info');
    try {
      var dimensions = collageDimensions(collage.aspect, false);
      var outputCanvas = document.createElement('canvas');
      await drawCollage(outputCanvas, dimensions.width, dimensions.height, true);
      var blob = await new Promise(function (resolve) { outputCanvas.toBlob(function (result) { resolve(result); }, 'image/webp', 0.92); });
      if (!blob) throw new Error('The collage could not be exported.');
      var file = new File([blob], 'dixcoverhub-collage-' + Date.now() + '.webp', { type: 'image/webp', lastModified: Date.now() });
      var form = new FormData();
      form.append('file', file, file.name);
      form.append('alt_text', root.querySelector('[data-ai-collage-alt]').value.trim());
      var response = await fetch(DixcoverHubAI.imageSaveUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': DixcoverHubAI.nonce }, body: form });
      var result = await response.json();
      if (!response.ok || !result.id || !result.url) throw new Error(result.message || 'WordPress could not save the collage.');
      window.dispatchEvent(new CustomEvent('dixcoverhub-ai-featured-image-update', { detail: { id: result.id, url: result.url, alt: result.alt || root.querySelector('[data-ai-collage-alt]').value.trim(), thumbnail: result.thumbnail || result.url, filename: file.name } }));
      say('Collage created and selected as the featured image.', 'success');
      saving = false;
      closeEditor();
    } catch (error) {
      say(error && error.message ? error.message : 'The collage could not be saved.', 'error');
    } finally {
      saving = false;
      root.querySelectorAll('button,input').forEach(function (control) { control.disabled = false; });
      setEditorMode(editorMode);
    }
  }

  app.addEventListener('click', function (event) {
    var collageButton = event.target.closest('[data-ai-collage-open]');
    if (collageButton) { openCollage(collageButton); return; }
    var button = event.target.closest('[data-ai-image-edit]');
    if (button) openEditor(button);
  });
  var featuredImageButton = app.querySelector('[data-featured-image]');
  if (featuredImageButton) {
    var featuredActions = document.createElement('div');
    featuredActions.className = 'dh-ai-featured-actions';
    var collageOpenButton = document.createElement('button');
    collageOpenButton.type = 'button';
    collageOpenButton.className = 'button';
    collageOpenButton.dataset.aiCollageOpen = '';
    collageOpenButton.textContent = 'Create collage';
    featuredImageButton.parentNode.insertBefore(featuredActions, featuredImageButton);
    featuredActions.append(featuredImageButton, collageOpenButton);
  }
  root.querySelectorAll('[data-ai-image-mode]').forEach(function (button) {
    button.addEventListener('click', function () {
      setEditorMode(button.dataset.aiImageMode);
      say(editorMode === 'collage' ? 'Choose at least two images to create a collage.' : (sourceImage ? 'Image ready to edit.' : 'Choose a featured image before editing.'), 'info');
    });
  });
  root.querySelectorAll('[data-ai-collage-sections]').forEach(function (button) {
    button.addEventListener('click', function () {
      collage.sections = Number(button.dataset.aiCollageSections);
      collage.layout = collage.sections === 2 ? 'split-v' : collage.sections === 3 ? 'columns-3' : 'grid-2x2';
      root.querySelectorAll('[data-ai-collage-sections]').forEach(function (item) { var active = item === button; item.classList.toggle('is-active', active); item.setAttribute('aria-pressed', active ? 'true' : 'false'); });
      root.querySelectorAll('[data-ai-collage-layout-set]').forEach(function (set) { set.hidden = Number(set.dataset.aiCollageLayoutSet) !== collage.sections; });
      root.querySelectorAll('[data-ai-collage-layout]').forEach(function (item) { var active = item.dataset.aiCollageLayout === collage.layout; item.classList.toggle('is-active', active); item.setAttribute('aria-pressed', active ? 'true' : 'false'); });
      renderCollageSlots();
    });
  });
  root.querySelectorAll('[data-ai-collage-layout]').forEach(function (button) {
    button.addEventListener('click', function () {
      collage.layout = button.dataset.aiCollageLayout;
      root.querySelectorAll('[data-ai-collage-layout]').forEach(function (item) { var active = item === button; item.classList.toggle('is-active', active); item.setAttribute('aria-pressed', active ? 'true' : 'false'); });
      updateCollagePreview();
    });
  });
  root.querySelectorAll('[data-ai-collage-select]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (!window.wp || !wp.media) { say('The WordPress Media Library is unavailable on this screen.', 'error'); return; }
      var index = Number(button.dataset.aiCollageSelect);
      var frame = wp.media({ title: 'Choose image for section ' + (index + 1), button: { text: 'Use for collage' }, multiple: false, library: { type: 'image' } });
      frame.on('select', function () {
        var media = frame.state().get('selection').first().toJSON();
        var url = media.url || (media.sizes && media.sizes.full ? media.sizes.full.url : '');
        if (!url) { say('That Media Library item does not have a usable image URL.', 'error'); return; }
        var filename = decodeURIComponent((new URL(url, window.location.href)).pathname.split('/').pop() || 'image');
        collage.slots[index] = { id: Number(media.id) || 0, url: url, alt: media.alt || '', filename: filename, thumbnail: media.sizes && media.sizes.thumbnail ? media.sizes.thumbnail.url : url };
        if (!root.querySelector('[data-ai-collage-alt]').value.trim()) root.querySelector('[data-ai-collage-alt]').value = media.alt || '';
        renderCollageSlots();
        say('Section ' + (index + 1) + ' image added.', 'success');
      });
      frame.open();
    });
  });
  root.querySelectorAll('[data-ai-collage-remove]').forEach(function (button) {
    button.addEventListener('click', function () { collage.slots[Number(button.dataset.aiCollageRemove)] = null; renderCollageSlots(); });
  });
  root.querySelectorAll('[data-ai-collage-aspect]').forEach(function (button) {
    button.addEventListener('click', function () {
      collage.aspect = button.dataset.aiCollageAspect;
      root.querySelectorAll('[data-ai-collage-aspect]').forEach(function (item) { var active = item === button; item.classList.toggle('is-active', active); item.setAttribute('aria-pressed', active ? 'true' : 'false'); });
      updateCollagePreview();
    });
  });
  root.querySelectorAll('[data-ai-collage-adjust]').forEach(function (input) {
    input.addEventListener('input', function () {
      var key = input.dataset.aiCollageAdjust;
      collage[key] = Number(input.value);
      root.querySelector('[data-ai-collage-value="' + key + '"]').textContent = input.value + ' px';
      updateCollagePreview();
    });
  });
  root.querySelectorAll('[data-ai-collage-color]').forEach(function (input) {
    input.addEventListener('input', function () {
      if (input.dataset.aiCollageColor === 'background') collage.background = input.value;
      else collage.borderColor = input.value;
      updateCollagePreview();
    });
  });
  root.querySelectorAll('[data-ai-image-editor-close]').forEach(function (button) { button.addEventListener('click', closeEditor); });
  saveButton.addEventListener('click', saveEditedImage);
  root.querySelector('[data-ai-image-reset]').addEventListener('click', function () {
    if (editorMode === 'collage') { resetCollage(); say('Collage settings and selected images reset.', 'info'); }
    else if (state) { resetState(); say('Edits reset.', 'info'); }
  });

  root.querySelectorAll('[data-ai-image-tool]').forEach(function (button) {
    button.addEventListener('click', function () { setActiveTool(button.dataset.aiImageTool); });
  });
  root.querySelectorAll('[data-ai-image-ratio]').forEach(function (button) {
    button.addEventListener('click', function () { applyRatio(button.dataset.aiImageRatio); });
  });
  root.querySelectorAll('[data-ai-image-rotate]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (!state) return;
      state.rotation = (state.rotation + Number(button.dataset.aiImageRotate) + 360) % 360;
      fitPreview();
    });
  });
  root.querySelectorAll('[data-ai-image-flip]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (!state) return;
      if (button.dataset.aiImageFlip === 'x') state.flipX = !state.flipX;
      else state.flipY = !state.flipY;
      fitPreview();
    });
  });
  root.querySelectorAll('[data-ai-image-adjust]').forEach(function (input) {
    input.addEventListener('input', function () {
      if (!state) return;
      var key = input.dataset.aiImageAdjust;
      state[key] = cleanNumber(input.value, 100);
      var output = root.querySelector('[data-ai-image-value="' + key + '"]');
      if (output) output.textContent = state[key] + '%';
      fitPreview();
    });
  });
  root.querySelectorAll('[data-ai-image-filter]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (!state) return;
      state.filter = button.dataset.aiImageFilter;
      root.querySelectorAll('[data-ai-image-filter]').forEach(function (item) {
        var active = item === button;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      fitPreview();
    });
  });

  cropBox.addEventListener('pointerdown', function (event) {
    if (!state || !sourceImage) return;
    var handle = event.target.closest('[data-ai-crop-handle]');
    drag = {
      mode: handle ? handle.dataset.aiCropHandle : 'move',
      startX: event.clientX,
      startY: event.clientY,
      x: state.cropX,
      y: state.cropY,
      w: state.cropW,
      h: state.cropH
    };
    cropBox.setPointerCapture(event.pointerId);
    event.preventDefault();
  });
  cropBox.addEventListener('pointermove', function (event) {
    if (!drag || !state || !canvasView) return;
    var rect = canvasView.getBoundingClientRect();
    if (!rect.width || !rect.height) return;
    var dx = ((event.clientX - drag.startX) / rect.width) * 100;
    var dy = ((event.clientY - drag.startY) / rect.height) * 100;
    if (drag.mode === 'move') {
      state.cropX = clamp(drag.x + dx, 0, 100 - state.cropW);
      state.cropY = clamp(drag.y + dy, 0, 100 - state.cropH);
    } else if (drag.mode === 'se') {
      state.cropW = clamp(drag.w + dx, 10, 100 - drag.x);
      state.cropH = clamp(drag.h + dy, 10, 100 - drag.y);
    } else if (drag.mode === 'nw') {
      state.cropW = clamp(drag.w - dx, 10, drag.x + drag.w);
      state.cropH = clamp(drag.h - dy, 10, drag.y + drag.h);
      state.cropX = drag.x + drag.w - state.cropW;
      state.cropY = drag.y + drag.h - state.cropH;
    } else if (drag.mode === 'ne') {
      state.cropW = clamp(drag.w + dx, 10, 100 - drag.x);
      state.cropH = clamp(drag.h - dy, 10, drag.y + drag.h);
      state.cropY = drag.y + drag.h - state.cropH;
    } else if (drag.mode === 'sw') {
      state.cropW = clamp(drag.w - dx, 10, drag.x + drag.w);
      state.cropH = clamp(drag.h + dy, 10, 100 - drag.y);
      state.cropX = drag.x + drag.w - state.cropW;
    }
    setCropBox();
  });
  cropBox.addEventListener('pointerup', function () { drag = null; });
  cropBox.addEventListener('pointercancel', function () { drag = null; });
  cropBox.addEventListener('keydown', function (event) {
    if (!state || !['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) return;
    event.preventDefault();
    var step = event.shiftKey ? 5 : 1;
    if (event.key === 'ArrowLeft') state.cropX = clamp(state.cropX - step, 0, 100 - state.cropW);
    if (event.key === 'ArrowRight') state.cropX = clamp(state.cropX + step, 0, 100 - state.cropW);
    if (event.key === 'ArrowUp') state.cropY = clamp(state.cropY - step, 0, 100 - state.cropH);
    if (event.key === 'ArrowDown') state.cropY = clamp(state.cropY + step, 0, 100 - state.cropH);
    setCropBox();
  });
  dialog.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      event.preventDefault();
      closeEditor();
      return;
    }
    if (event.key !== 'Tab') return;
    var focusable = Array.from(dialog.querySelectorAll('button:not(:disabled),input:not(:disabled),[tabindex="0"]')).filter(function (item) { return !item.hidden && item.getClientRects().length > 0; });
    if (!focusable.length) return;
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
  window.addEventListener('resize', fitPreview);
})();
