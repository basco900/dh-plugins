(function () {
  'use strict';
  var root = document.querySelector('[data-dh-analytics]');
  if (!root || !window.DixcoverHubAnalytics) return;
  var api = window.DixcoverHubAnalytics;
  var period = root.querySelector('#dh-analytics-period');
  var realtimeWindow = '5m';
  var loading = root.querySelector('[data-analytics-loading]');
  var errorBox = root.querySelector('[data-analytics-error]');
  var numberFormat = new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 });

  function node(tag, className, text) {
    var item = document.createElement(tag);
    if (className) item.className = className;
    if (text !== undefined && text !== null) item.textContent = String(text);
    return item;
  }
  function format(value) { return numberFormat.format(Number(value) || 0); }
  function percent(value) { return (Number(value || 0) * 100).toFixed(1) + '%'; }
  function duration(seconds) {
    seconds = Number(seconds || 0);
    if (!seconds) return '—';
    return Math.floor(seconds / 60) + 'm ' + Math.round(seconds % 60) + 's';
  }
  function showError(message) { errorBox.hidden = false; errorBox.textContent = message; }
  function hideError() { errorBox.hidden = true; errorBox.textContent = ''; }
  function changeLabel(value) {
    if (value === null || value === undefined) return 'No prior period';
    var number = Number(value);
    return (number > 0 ? '+' : '') + number.toFixed(1) + '% vs previous';
  }
  function renderMetrics(data) {
    var host = root.querySelector('[data-metrics]');
    host.replaceChildren();
    var values = data.metrics.current;
    var changes = data.metrics.changes;
    [
      ['Views', values.views, changes.views],
      ['Active users', values.activeUsers, changes.activeUsers],
      ['Sessions', values.sessions, changes.sessions],
      ['Applications', values.applicationClicks, data.periodLabel + ' · GA4'],
      ['Engaged sessions', values.engagedSessions, changes.engagedSessions],
    ].forEach(function (metric) {
      var card = node('article', 'dh-analytics-metric');
      card.append(node('span', 'dh-analytics-metric-label', metric[0]), node('strong', 'dh-analytics-metric-value', format(metric[1])));
      var customNote = typeof metric[2] === 'string';
      var change = node('span', 'dh-analytics-metric-change ' + (!customNote && metric[2] > 0 ? 'is-up' : !customNote && metric[2] < 0 ? 'is-down' : ''), customNote ? metric[2] : changeLabel(metric[2]));
      card.appendChild(change); host.appendChild(card);
    });
    var engagement = node('article', 'dh-analytics-metric');
    engagement.append(node('span', 'dh-analytics-metric-label', 'Engagement rate'), node('strong', 'dh-analytics-metric-value', percent(values.engagementRate)));
    engagement.appendChild(node('span', 'dh-analytics-metric-change', data.periodLabel + ' · GA4')); host.appendChild(engagement);
  }
  function renderEngagementHealth(data) {
    var values = data.metrics.current || {};
    root.querySelector('[data-average-session-duration]').textContent = duration(values.averageSessionDuration);
    root.querySelector('[data-bounce-rate]').textContent = percent(values.bounceRate);
  }
  function makeSvg(name, attrs) {
    var item = document.createElementNS('http://www.w3.org/2000/svg', name);
    Object.keys(attrs).forEach(function (key) { item.setAttribute(key, attrs[key]); });
    return item;
  }
  function renderChart(data) {
    var host = root.querySelector('[data-chart]');
    var values = Array.isArray(data.series) ? data.series : [];
    host.replaceChildren();
    if (!values.length) { host.appendChild(node('p', 'dh-analytics-empty', 'No traffic rows are available for this period.')); return; }
    var width = 900; var height = 250; var padX = 18; var padY = 18;
    var max = Math.max(1, ...values.map(function (item) { return Number(item.views) || 0; }));
    var svg = makeSvg('svg', { viewBox: '0 0 ' + width + ' ' + height, role: 'img', 'aria-label': 'Page views over time', preserveAspectRatio: 'none' });
    [0.25, 0.5, 0.75, 1].forEach(function (step) {
      var y = height - padY - (height - padY * 2) * step;
      svg.appendChild(makeSvg('line', { x1: padX, x2: width - padX, y1: y, y2: y, class: 'dh-chart-gridline' }));
    });
    var points = values.map(function (item, index) {
      var x = padX + (values.length < 2 ? 0 : (width - padX * 2) * index / (values.length - 1));
      var y = height - padY - (height - padY * 2) * (Number(item.views) || 0) / max;
      return [x, y];
    });
    var path = points.map(function (point, index) { return (index ? 'L' : 'M') + point[0].toFixed(1) + ',' + point[1].toFixed(1); }).join(' ');
    svg.appendChild(makeSvg('path', { d: path, class: 'dh-chart-line dh-chart-views' }));
    var userPoints = values.map(function (item, index) {
      var x = padX + (values.length < 2 ? 0 : (width - padX * 2) * index / (values.length - 1));
      var y = height - padY - (height - padY * 2) * (Number(item.activeUsers) || 0) / max;
      return [x, y];
    });
    var userPath = userPoints.map(function (point, index) { return (index ? 'L' : 'M') + point[0].toFixed(1) + ',' + point[1].toFixed(1); }).join(' ');
    svg.appendChild(makeSvg('path', { d: userPath, class: 'dh-chart-line dh-chart-users' }));
    host.appendChild(svg);
    var first = values[0].date; var last = values[values.length - 1].date;
    root.querySelector('[data-chart-total]').textContent = format(values.reduce(function (sum, item) { return sum + (Number(item.views) || 0); }, 0)) + ' views';
    var labelRow = node('div', 'dh-analytics-chart-labels');
    labelRow.append(node('span', '', dateLabel(first)), node('span', '', dateLabel(last)));
    host.appendChild(labelRow);
  }
  function dateLabel(value) {
    var text = String(value || '');
    if (!/^\d{8}$/.test(text)) return text;
    return text.slice(4, 6) + '/' + text.slice(6, 8);
  }
  var contentRows = [];
  var contentSearch = root.querySelector('[data-content-search]');
  var contentScope = 'all';
  var contentPageSize = 10;
  var contentPage = 1;
  var contentPageStatus = root.querySelector('[data-content-page-status]');
  var contentPagePrevious = root.querySelector('[data-content-page-prev]');
  var contentPageNext = root.querySelector('[data-content-page-next]');

  function renderCurrentPages() {
    var host = root.querySelector('[data-top-pages]');
    var query = contentSearch ? contentSearch.value.trim().toLocaleLowerCase() : '';
    var rows = contentRows.filter(function (page) {
      if (contentScope === 'mine' && Number(page.authorId) !== Number(api.currentUserId)) return false;
      if (!query) return true;
      return [page.title, page.path, page.authorName].some(function (value) {
        return String(value || '').toLocaleLowerCase().indexOf(query) !== -1;
      });
    });
    var pageCount = Math.max(1, Math.ceil(rows.length / contentPageSize));
    contentPage = Math.min(Math.max(1, contentPage), pageCount);
    var startIndex = (contentPage - 1) * contentPageSize;
    var visible = rows.slice(startIndex, startIndex + contentPageSize);
    host.replaceChildren();
    if (!visible.length) {
      var empty = document.createElement('tr');
      var cell = node('td', '', rows.length ? 'No content appears on this page.' : 'No content matches this filter.');
      cell.colSpan = 6; empty.appendChild(cell); host.appendChild(empty);
    }
    visible.forEach(function (page) {
      var row = document.createElement('tr'); var title = document.createElement('td');
      var link = document.createElement('a'); link.href = new URL(page.path || '/', window.location.origin).href; link.target = '_blank'; link.rel = 'noopener noreferrer'; link.textContent = page.title || page.path; title.appendChild(link);
      var reportCell = document.createElement('td');
      if (Number(page.postId) > 0) {
        var reportButton = node('button', 'dh-analytics-post-report', 'Open report');
        reportButton.type = 'button'; reportButton.dataset.contentReport = String(page.postId); reportButton.dataset.contentTitle = page.title || page.path;
        reportCell.appendChild(reportButton);
      } else {
        reportCell.textContent = '—'; reportCell.className = 'dh-number-cell';
      }
      row.append(title, node('td', 'dh-analytics-author', page.authorName || 'Unattributed'), node('td', 'dh-number-cell', format(page.views)), node('td', 'dh-number-cell', format(page.activeUsers)), node('td', 'dh-number-cell', percent(page.engagementRate)), reportCell);
      host.appendChild(row);
    });
    var first = rows.length ? startIndex + 1 : 0;
    var last = Math.min(startIndex + visible.length, rows.length);
    contentPageStatus.textContent = 'Showing ' + first + '-' + last + ' of ' + format(rows.length) + ' content items';
    contentPagePrevious.disabled = contentPage <= 1;
    contentPageNext.disabled = contentPage >= pageCount;
  }

  function renderPages(rows) {
    contentRows = Array.isArray(rows) ? rows : [];
    contentPage = 1;
    renderCurrentPages();
  }

  if (contentSearch) contentSearch.addEventListener('input', function () { contentPage = 1; renderCurrentPages(); });
  root.querySelectorAll('[data-content-scope]').forEach(function (button) {
    button.addEventListener('click', function () {
      contentScope = button.dataset.contentScope;
      contentPage = 1;
      root.querySelectorAll('[data-content-scope]').forEach(function (item) {
        var active = item === button;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      renderCurrentPages();
    });
  });
  root.querySelectorAll('[data-content-page-size]').forEach(function (button) {
    button.addEventListener('click', function () {
      contentPageSize = Number(button.dataset.contentPageSize) || 10;
      contentPage = 1;
      root.querySelectorAll('[data-content-page-size]').forEach(function (item) {
        var active = item === button;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      renderCurrentPages();
    });
  });
  contentPagePrevious.addEventListener('click', function () { contentPage -= 1; renderCurrentPages(); });
  contentPageNext.addEventListener('click', function () { contentPage += 1; renderCurrentPages(); });

  var contentModal = root.querySelector('[data-content-modal]');
  var contentLoading = root.querySelector('[data-content-loading]');
  var contentError = root.querySelector('[data-content-error]');
  var contentBody = root.querySelector('[data-content-body]');
  var previousFocus = null;
  function renderContentChart(series) {
    var host = root.querySelector('[data-content-chart]');
    var values = Array.isArray(series) ? series : [];
    host.replaceChildren();
    if (!values.length) { host.appendChild(node('p', 'dh-analytics-empty', 'No daily traffic rows are available for this period.')); return; }
    var width = 900; var height = 190; var padX = 12; var padY = 16;
    var max = Math.max(1, ...values.map(function (item) { return Number(item.value) || 0; }));
    var svg = makeSvg('svg', { viewBox: '0 0 ' + width + ' ' + height, role: 'img', 'aria-label': 'Daily page views for this post', preserveAspectRatio: 'none' });
    [0.25, 0.5, 0.75, 1].forEach(function (step) {
      var y = height - padY - (height - padY * 2) * step;
      svg.appendChild(makeSvg('line', { x1: padX, x2: width - padX, y1: y, y2: y, class: 'dh-chart-gridline' }));
    });
    var path = values.map(function (item, index) {
      var x = padX + (values.length < 2 ? 0 : (width - padX * 2) * index / (values.length - 1));
      var y = height - padY - (height - padY * 2) * (Number(item.value) || 0) / max;
      return (index ? 'L' : 'M') + x.toFixed(1) + ',' + y.toFixed(1);
    }).join(' ');
    svg.appendChild(makeSvg('path', { d: path, class: 'dh-chart-line dh-chart-views' }));
    host.appendChild(svg);
    var labels = node('div', 'dh-analytics-chart-labels');
    labels.append(node('span', '', dateLabel(values[0].label)), node('span', '', dateLabel(values[values.length - 1].label)));
    host.appendChild(labels);
  }
  function renderContentReport(data) {
    root.querySelector('[data-content-title]').textContent = data.title || 'Post analytics';
    root.querySelector('[data-content-path]').textContent = data.path || '';
    var viewLink = node('a', 'dh-analytics-content-view', 'View published page ↗');
    viewLink.href = data.permalink || new URL(data.path || '/', window.location.origin).href;
    viewLink.target = '_blank'; viewLink.rel = 'noopener noreferrer';
    var titleHost = root.querySelector('[data-content-title]').parentElement;
    var oldLink = titleHost.querySelector('.dh-analytics-content-view'); if (oldLink) oldLink.remove();
    titleHost.appendChild(viewLink);
    contentLoading.hidden = true;
    contentError.hidden = true;
    if (!data.configured) {
      contentBody.hidden = true;
      contentError.hidden = false;
      contentError.textContent = 'Google Analytics is not configured. Add the server-side GA4 service account variables to load this report.';
      return;
    }
    contentBody.hidden = false;
    var values = data.summary || {};
    var events = data.events || {};
    var metrics = [
      ['Active now', values.current], ['Views · 5 minutes', values.last5Minutes], ['Views · 30 minutes', values.last30Minutes],
      ['Period views', values.periodViews], ['Visitors', values.visitors],
      ['Today', values.today], ['Last 7 days', values.last7Days], ['Last 30 days', values.last30Days], ['All time', values.allTime],
      ['Application clicks', events.applicationClicks], ['Shares', events.shares], ['Bookmarks', events.bookmarks]
    ];
    var host = root.querySelector('[data-content-metrics]'); host.replaceChildren();
    metrics.forEach(function (item) {
      var card = node('article', 'dh-analytics-content-metric');
      card.append(node('span', '', item[0]), node('strong', '', format(item[1]))); host.appendChild(card);
    });
    var conversionRate = Math.max(0, Math.min(100, Number(data.conversionRate) || 0));
    root.querySelector('[data-content-conversion-value]').textContent = conversionRate.toFixed(1) + '%';
    root.querySelector('[data-content-conversion-bar]').style.width = conversionRate + '%';
    root.querySelector('[data-content-chart-total]').textContent = format(values.periodViews) + ' views · ' + (data.periodLabel || 'selected period');
    renderContentChart(data.series);
    if (data.error || (data.warnings && data.warnings.length)) {
      contentError.hidden = false;
      contentError.textContent = data.error || Array.from(new Set(data.warnings)).join(' ');
    }
  }
  async function openContentReport(postId, title) {
    if (!postId || !contentModal) return;
    previousFocus = document.activeElement;
    contentModal.hidden = false; contentLoading.hidden = false; contentError.hidden = true; contentBody.hidden = true;
    root.querySelector('[data-content-title]').textContent = title || 'Post analytics';
    root.querySelector('[data-content-path]').textContent = '';
    contentModal.querySelector('.dh-analytics-content-close').focus();
    try {
		var url = api.contentUrl.replace(/\/$/, '') + '/' + encodeURIComponent(postId) + '?period=' + encodeURIComponent(period.value || '30d');
      var response = await fetch(url, { credentials: 'same-origin', headers: { 'X-WP-Nonce': api.nonce } });
      var data = await response.json();
      if (!response.ok) throw new Error(data.message || 'The post report could not be loaded.');
      renderContentReport(data);
    } catch (error) {
      contentLoading.hidden = true; contentBody.hidden = true; contentError.hidden = false;
      contentError.textContent = error.message || 'The post report could not be loaded.';
    }
  }
  function closeContentReport() {
    contentModal.hidden = true;
    if (previousFocus && previousFocus.focus) previousFocus.focus();
  }
  contentModal.querySelectorAll('[data-content-close]').forEach(function (button) { button.addEventListener('click', closeContentReport); });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && !contentModal.hidden) closeContentReport(); });
  root.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-content-report]');
    if (trigger) openContentReport(trigger.dataset.contentReport, trigger.dataset.contentTitle);
  });
  function renderMiniTable(selector, rows, key, metric) {
    var host = root.querySelector(selector); host.replaceChildren();
    if (!rows || !rows.length) { host.appendChild(node('p', 'dh-analytics-empty', 'No data for this period yet.')); return; }
    var table = document.createElement('table'); table.className = 'dh-analytics-mini-table';
    rows.forEach(function (row) {
      var tr = document.createElement('tr'); tr.append(node('th', '', row[key] || 'Unknown'), node('td', '', format(row[metric]))); table.appendChild(tr);
    });
    host.appendChild(table);
  }
  function renderRealtime(data) {
    root.querySelector('[data-live-users]').textContent = format(data.activeUsers);
    root.querySelector('[data-live-views]').textContent = format(data.views);
    var windowLabel = String(data.windowLabel || 'the selected window');
    root.querySelector('[data-live-caption]').textContent = 'live' === data.window
      ? 'Activity happening right now'
      : (['today', 'yesterday'].includes(data.window) ? 'Activity ' + windowLabel.toLowerCase() : 'Activity in the ' + windowLabel.toLowerCase());
    root.querySelectorAll('[data-realtime-window]').forEach(function (button) {
      var selected = button.dataset.realtimeWindow === data.window;
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
      button.classList.toggle('is-active', selected);
    });
    var host = root.querySelector('[data-realtime-pages]'); host.replaceChildren();
    (data.pages || []).slice(0, 5).forEach(function (page) {
      var row = node('div', 'dh-analytics-live-row');
      row.append(node('span', '', page.title || page.path), node('strong', '', format(page.activeUsers) + ' users · ' + format(page.views) + ' views'));
      host.appendChild(row);
    });
    if (!data.pages || !data.pages.length) host.appendChild(node('p', 'dh-analytics-empty', 'No users in the realtime window.'));
  }
  function renderEvents(rows) {
    var host = root.querySelector('[data-custom-events]'); host.replaceChildren();
    var map = {};
    (rows || []).forEach(function (item) { map[item.name] = item.count; });
    [['Applications', 'application_click'], ['Shares', 'share'], ['Bookmarks', 'bookmark'], ['Community joins', 'community_join']].forEach(function (entry) {
      var card = node('div', 'dh-analytics-event'); card.append(node('span', '', entry[0]), node('strong', '', format(map[entry[1]] || 0))); host.appendChild(card);
    });
  }
  function render(data) {
    loading.hidden = true;
    var alert = root.querySelector('[data-analytics-alert]');
    if (!data.configured) {
      alert.hidden = false;
      root.querySelector('[data-metrics]').replaceChildren();
      loading.hidden = true;
      return;
    }
    alert.hidden = true;
    renderMetrics(data); renderEngagementHealth(data); renderChart(data); renderPages(data.topPages);
    renderRealtime(data.realtime || { activeUsers: 0, pages: [] });
    renderMiniTable('[data-channels]', data.channels, 'name', 'sessions');
    renderMiniTable('[data-countries]', data.countries, 'name', 'activeUsers');
    renderMiniTable('[data-devices]', data.devices, 'name', 'activeUsers');
    renderEvents(data.events);
    root.querySelector('[data-analytics-updated]').textContent = 'Updated ' + new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit', timeZone: data.timezone || api.timezone }).format(new Date(data.updatedAt));
    if (data.warnings && data.warnings.length) showError(Array.from(new Set(data.warnings)).join(' ')); else hideError();
  }
  async function load(force) {
    loading.hidden = false; hideError();
    try {
      var url = api.reportUrl + '?period=' + encodeURIComponent(period.value) + '&window=' + encodeURIComponent(realtimeWindow) + (force ? '&refresh=' + Date.now() : '');
      var response = await fetch(url, { credentials: 'same-origin', headers: { 'X-WP-Nonce': api.nonce } });
      var data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Analytics could not be loaded.');
      render(data);
    } catch (error) {
      loading.hidden = true;
      showError(error.message || 'Analytics could not be loaded.');
    }
  }
  root.querySelector('[data-analytics-refresh]').addEventListener('click', function () { load(true); });
  period.addEventListener('change', function () { load(false); });
  root.querySelectorAll('[data-realtime-window]').forEach(function (button) {
    button.addEventListener('click', function () {
      realtimeWindow = button.dataset.realtimeWindow;
      load(false);
    });
  });
  var toggle = root.querySelector('[data-config-toggle]');
  if (toggle) toggle.addEventListener('click', function () {
    var config = root.querySelector('[data-config]'); config.hidden = !config.hidden; toggle.textContent = config.hidden ? 'Show setup variables' : 'Hide setup variables';
  });
  load(false);
  if (Number(api.postId) > 0) openContentReport(Number(api.postId), 'Post analytics');
  window.setInterval(function () { if (!document.hidden && api.configured) load(false); }, 60000);
})();
