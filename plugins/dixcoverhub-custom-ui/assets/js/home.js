(function () {
	'use strict';

	document.querySelectorAll('[data-dh-home-deck]').forEach(function (deck) {
		var cards = Array.prototype.slice.call(deck.querySelectorAll('[data-dh-home-card]'));
		var previous = document.querySelector('[data-dh-home-prev]');
		var next = document.querySelector('[data-dh-home-next]');
		var status = document.querySelector('[data-dh-home-deck-status]');
		var current = Math.max(0, cards.findIndex(function (card) { return card.classList.contains('is-current'); }));
		var gesture = null;
		var suppressClickUntil = 0;

		if (!cards.length) return;

		deck.addEventListener('click', function (event) {
			var bookmark = event.target.closest('[data-dh-home-bookmark]');
			if (!bookmark || !deck.contains(bookmark)) return;

			var saved = bookmark.getAttribute('aria-pressed') !== 'true';
			var statusMessage = bookmark.querySelector('[data-dh-home-bookmark-status]');
			bookmark.setAttribute('aria-pressed', saved ? 'true' : 'false');
			bookmark.setAttribute('aria-label', bookmark.getAttribute(saved ? 'data-remove-label' : 'data-save-label') || 'Save opportunity');
			bookmark.classList.toggle('is-saved', saved);
			if (statusMessage) statusMessage.textContent = bookmark.getAttribute(saved ? 'data-saved-message' : 'data-unsaved-message') || '';
		});

		function show(index) {
			current = (index + cards.length) % cards.length;
			cards.forEach(function (card, cardIndex) {
				var active = cardIndex === current;
				card.classList.toggle('is-current', active);
				card.setAttribute('aria-hidden', active ? 'false' : 'true');
				card.inert = !active;
			});
			if (status) status.textContent = (current + 1) + ' / ' + cards.length;
		}

		function clearGestureStyles(card) {
			if (!card) return;
			card.classList.remove('is-dragging');
			card.style.removeProperty('transition');
			card.style.removeProperty('transform');
			card.style.removeProperty('opacity');
		}

		function releasePointer(event) {
			if (deck.hasPointerCapture && deck.hasPointerCapture(event.pointerId)) {
				try { deck.releasePointerCapture(event.pointerId); } catch (error) { /* Pointer capture may already be released. */ }
			}
		}

		function finishGesture(event, cancelled) {
			if (!gesture || gesture.pointerId !== event.pointerId) return;
			var activeGesture = gesture;
			gesture = null;
			releasePointer(event);

			if (cancelled || activeGesture.direction !== 'horizontal') {
				clearGestureStyles(activeGesture.card);
				return;
			}

			var dx = event.clientX - activeGesture.startX;
			var dy = event.clientY - activeGesture.startY;
			if (Math.abs(dx) < 70 || Math.abs(dx) < Math.abs(dy) * 1.2) {
				clearGestureStyles(activeGesture.card);
				return;
			}

			suppressClickUntil = Date.now() + 500;
			var direction = dx < 0 ? -1 : 1;
			if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
				clearGestureStyles(activeGesture.card);
				show(current + 1);
				return;
			}
			var exitX = direction * (Math.max(deck.clientWidth, 320) + 80);
			activeGesture.card.classList.remove('is-dragging');
			activeGesture.card.style.transition = 'transform 320ms cubic-bezier(.22,.8,.2,1), opacity 260ms ease';
			activeGesture.card.style.transform = 'translate3d(' + exitX + 'px, ' + Math.round(dy * 0.1) + 'px, 0) rotate(' + (direction * 11) + 'deg)';
			activeGesture.card.style.opacity = '0';
			show(current + 1);
			window.setTimeout(function () { clearGestureStyles(activeGesture.card); }, 340);
		}

		show(current);
		if (cards.length < 2) {
			if (previous) previous.hidden = true;
			if (next) next.hidden = true;
			return;
		}
		if (previous) previous.addEventListener('click', function () { show(current - 1); });
		if (next) next.addEventListener('click', function () { show(current + 1); });

		deck.addEventListener('keydown', function (event) {
			if (event.key === 'ArrowLeft') { event.preventDefault(); show(current - 1); }
			if (event.key === 'ArrowRight') { event.preventDefault(); show(current + 1); }
		});
		deck.addEventListener('pointerdown', function (event) {
			if (!event.isPrimary || (event.button !== 0 && event.pointerType === 'mouse') || event.target.closest('[data-dh-home-bookmark]')) return;
			gesture = {
				pointerId: event.pointerId,
				startX: event.clientX,
				startY: event.clientY,
				direction: 'pending',
				card: cards[current]
			};
		});
		deck.addEventListener('pointermove', function (event) {
			if (!gesture || gesture.pointerId !== event.pointerId) return;
			var dx = event.clientX - gesture.startX;
			var dy = event.clientY - gesture.startY;

			if (gesture.direction === 'pending' && (Math.abs(dx) > 8 || Math.abs(dy) > 8)) {
				if (Math.abs(dy) >= Math.abs(dx)) {
					gesture.direction = 'vertical';
					return;
				}
				gesture.direction = 'horizontal';
				gesture.card.classList.add('is-dragging');
				if (deck.setPointerCapture) {
					try { deck.setPointerCapture(event.pointerId); } catch (error) { /* Keep the swipe usable without capture. */ }
				}
			}

			if (gesture.direction !== 'horizontal') return;
			event.preventDefault();
			var rotation = Math.max(-9, Math.min(9, dx / 28));
			var fade = Math.max(0.68, 1 - Math.abs(dx) / 850);
			gesture.card.style.transform = 'translate3d(' + dx + 'px, ' + Math.round(dy * 0.1) + 'px, 0) rotate(' + rotation + 'deg)';
			gesture.card.style.opacity = String(fade);
		});
		deck.addEventListener('pointerup', function (event) { finishGesture(event, false); });
		deck.addEventListener('pointercancel', function (event) { finishGesture(event, true); });
		deck.addEventListener('click', function (event) {
			if (!suppressClickUntil || event.detail === 0 || Date.now() > suppressClickUntil) return;
			suppressClickUntil = 0;
			event.preventDefault();
			event.stopPropagation();
		}, true);
	});
}());
