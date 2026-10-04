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

	document.addEventListener('click', function (event) {
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
			lightbox.setAttribute('aria-label', 'Featured image');
			lightbox.tabIndex = -1;
			var image = document.createElement('img');
			image.src = imageLink.href;
			image.alt = imageLink.querySelector('img') ? imageLink.querySelector('img').alt : '';
			lightbox.appendChild(image);
			lightbox.addEventListener('click', function () { lightbox.remove(); });
			lightbox.addEventListener('keydown', function (keyEvent) { if (keyEvent.key === 'Escape') lightbox.remove(); });
			document.body.appendChild(lightbox);
			lightbox.focus();
		}
	});
	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			var lightbox = document.querySelector('.dh-single-post-lightbox');
			if (lightbox) lightbox.remove();
		}
	});
}());
