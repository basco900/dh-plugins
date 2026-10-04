(function () {
  'use strict';
  var allowed = new Set(['application_click', 'share', 'bookmark', 'community_join']);
  document.addEventListener('click', function (event) {
    var target = event.target && event.target.closest ? event.target.closest('[data-dh-analytics]') : null;
    if (!target || typeof window.gtag !== 'function') return;
    var name = String(target.getAttribute('data-dh-analytics') || '');
    if (!allowed.has(name)) return;
    var contentType = String(target.getAttribute('data-content-type') || '').slice(0, 40);
    var contentId = String(target.getAttribute('data-content-id') || '').slice(0, 80);
    var details = {};
    if (contentType) details.content_type = contentType;
    if (contentId) details.content_id = contentId;
    window.gtag('event', name, details);
  }, true);
})();
