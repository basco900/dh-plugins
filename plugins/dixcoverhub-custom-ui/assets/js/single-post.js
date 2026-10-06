(function () {
	'use strict';

	function announce(message) {
		var notice = document.createElement('div');
		notice.className = 'dh-single-post-notice';
		notice.setAttribute('role', 'status');
		notice.textContent = message;
		document.body.appendChild(notice);
		window.setTimeout(function () { notice.remove(); }, 1800);
	}

	function closeShareMenus(restoreFocus) {
		document.querySelectorAll('[data-dh-share-menu]:not([hidden])').forEach(function (menu) {
			menu.hidden = true;
			var share = menu.closest('[data-dh-share]');
			var trigger = share && share.querySelector('[data-dh-share-toggle]');
			if (trigger) {
				trigger.setAttribute('aria-expanded', 'false');
				if (restoreFocus) trigger.focus();
			}
		});
	}

	document.addEventListener('click', function (event) {
		var shareToggle = event.target.closest('[data-dh-share-toggle]');
		if (shareToggle) {
			var share = shareToggle.closest('[data-dh-share]');
			var shareMenu = share && share.querySelector('[data-dh-share-menu]');
			var shouldOpen = shareMenu && shareMenu.hidden;
			closeShareMenus(false);
			if (shouldOpen) {
				shareMenu.hidden = false;
				shareToggle.setAttribute('aria-expanded', 'true');
				var firstAction = shareMenu.querySelector('[data-dh-share-action]');
				if (firstAction) firstAction.focus();
			}
			return;
		}

		var shareAction = event.target.closest('[data-dh-share-action]');
		if (shareAction) closeShareMenus(false);
		else if (!event.target.closest('[data-dh-share]')) closeShareMenus(false);

		var copyButton = event.target.closest('[data-dh-copy-link]');
		if (copyButton) {
			var url = copyButton.getAttribute('data-copy-url') || window.location.href;
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(url).then(function () { announce('Link copied'); }).catch(function () { announce(url); });
			} else {
				var field = document.createElement('textarea');
				field.value = url;
				field.setAttribute('readonly', '');
				field.style.position = 'fixed';
				field.style.opacity = '0';
				document.body.appendChild(field);
				field.select();
				var copied = document.execCommand('copy');
				field.remove();
				announce(copied ? 'Link copied' : url);
			}
		}

		var imageLink = event.target.closest('[data-dh-image-open]');
		if (imageLink && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey) {
			event.preventDefault();
			var lightbox = document.createElement('div');
			lightbox.className = 'dh-single-post-lightbox';
			lightbox.setAttribute('role', 'dialog');
			lightbox.setAttribute('aria-modal', 'true');
			var sourceImage = imageLink.querySelector('img');
			var imageAlt = sourceImage ? sourceImage.alt : '';
			lightbox.setAttribute('aria-label', imageAlt ? 'Enlarged featured image: ' + imageAlt : 'Enlarged featured image');
			lightbox.tabIndex = -1;
			var previousOverflow = document.body.style.overflow;
			var closeButton = document.createElement('button');
			closeButton.className = 'dh-single-post-lightbox-close';
			closeButton.type = 'button';
			closeButton.setAttribute('aria-label', 'Close enlarged image');
			closeButton.textContent = '×';
			var image = document.createElement('img');
			image.src = imageLink.href;
			image.alt = imageAlt;
			image.tabIndex = 0;
			var closeLightbox = function () {
				if (!lightbox.isConnected) return;
				lightbox.remove();
				document.body.style.overflow = previousOverflow;
				imageLink.focus();
			};
			closeButton.addEventListener('click', closeLightbox);
			lightbox.appendChild(image);
			lightbox.appendChild(closeButton);
			lightbox.addEventListener('click', function (clickEvent) {
				if (clickEvent.target === lightbox) closeLightbox();
			});
			lightbox.addEventListener('keydown', function (keyEvent) {
				if (keyEvent.key === 'Escape') {
					keyEvent.preventDefault();
					closeLightbox();
				} else if (keyEvent.key === 'Tab') {
					var focusable = [image, closeButton];
					var first = focusable[0];
					var last = focusable[focusable.length - 1];
					if (keyEvent.shiftKey && document.activeElement === first) {
						keyEvent.preventDefault();
						last.focus();
					} else if (!keyEvent.shiftKey && document.activeElement === last) {
						keyEvent.preventDefault();
						first.focus();
					}
				}
			});
			document.body.appendChild(lightbox);
			document.body.style.overflow = 'hidden';
			closeButton.focus();
		}
	});
	document.addEventListener('copy', function (event) {
		if (event.target.closest('[data-dh-copy-protected]')) event.preventDefault();
	});
	document.addEventListener('cut', function (event) {
		if (event.target.closest('[data-dh-copy-protected]')) event.preventDefault();
	});
	document.addEventListener('focusin', function (event) {
		if (!event.target.closest('[data-dh-share]')) closeShareMenus(false);
	});
	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			closeShareMenus(true);
			var lightbox = document.querySelector('.dh-single-post-lightbox');
			if (lightbox) {
				var closeButton = lightbox.querySelector('.dh-single-post-lightbox-close');
				if (closeButton) closeButton.click();
				else lightbox.remove();
			}
		}
	});
}());
