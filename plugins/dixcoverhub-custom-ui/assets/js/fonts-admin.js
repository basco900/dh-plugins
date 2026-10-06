(function () {
	'use strict';

	var form = document.querySelector('.dh-ui-upload-form');
	if (!form) return;

	var assignments = window.DixcoverHubFontAssignments || {};
	var assignmentNames = { 100: 'Thin', 200: 'Extra light', 300: 'Light', 400: 'Regular', 500: 'Medium', 600: 'Semi bold', 700: 'Bold', 800: 'Extra bold', 900: 'Black' };
	document.querySelectorAll('[data-dh-font-family]').forEach(function (familySelect) {
		var roleCard = familySelect.closest('.dh-ui-role-card');
		var weightSelect = roleCard && roleCard.querySelector('[data-dh-font-weight]');
		if (!weightSelect) return;

		function syncAvailableWeights() {
			var previous = Number(weightSelect.value || 400);
			var family = familySelect.value;
			var weights = family && assignments.weightsByFamily && assignments.weightsByFamily[family]
				? assignments.weightsByFamily[family]
				: (assignments.allWeights || [100, 200, 300, 400, 500, 600, 700, 800, 900]);
			if (!weights.length) weights = assignments.allWeights || [400];
			var selected = weights.reduce(function (nearest, weight) {
				return Math.abs(Number(weight) - previous) < Math.abs(Number(nearest) - previous) ? weight : nearest;
			}, weights[0]);
			weightSelect.textContent = '';
			weights.forEach(function (weight) {
				var option = new Option(weight + ' · ' + (assignmentNames[weight] || 'Regular'), String(weight), false, Number(weight) === Number(selected));
				weightSelect.add(option);
			});
		}

		familySelect.addEventListener('change', syncAvailableWeights);
	});

	var input = form.querySelector('input[name="font_file"]');
	var status = form.querySelector('[data-dh-font-optimization]');
	var submitButton = form.querySelector('button[type="submit"]');
	var settings = window.DixcoverHubFontOptimizer || {};

	function report(message, isError) {
		status.textContent = message;
		status.hidden = !message;
		status.classList.toggle('is-error', Boolean(isError));
	}

	function fail(worker, message) {
		if (worker) worker.terminate();
		submitButton.disabled = false;
		report(message, true);
	}

	function setRangeValue(select, value) {
		var stringValue = String(value);
		var option = Array.prototype.find.call(select.options, function (item) { return item.value === stringValue; });
		if (!option) {
			option = document.createElement('option');
			option.value = stringValue;
			option.textContent = stringValue;
			select.appendChild(option);
		}
		select.value = stringValue;
	}

	form.addEventListener('submit', function (event) {
		if (form.dataset.dhFontOptimized === '1') {
			delete form.dataset.dhFontOptimized;
			return;
		}

		var file = input.files && input.files[0];
		if (!file) return;
		var extension = file.name.split('.').pop().toLowerCase();
		if (extension !== 'ttf' && extension !== 'otf') return;

		event.preventDefault();
		report('', false);
		if (file.size <= 0 || file.size > Number(settings.maxFileBytes || 0)) {
			report('Choose a non-empty TTF/OTF file smaller than 10 MB.', true);
			return;
		}
		if (!window.Worker || !window.DataTransfer || !window.File || !file.arrayBuffer) {
			report('This browser cannot convert fonts locally. Choose a WOFF2 file instead.', true);
			return;
		}

		submitButton.disabled = true;
		report('Converting this font to WOFF2 in your browser. The source file stays on this device.', false);
		var worker;
		try {
			worker = new Worker(settings.workerUrl, { type: 'module' });
		} catch (error) {
			fail(null, 'Font conversion could not start. Choose a WOFF2 file or try another browser.');
			return;
		}

		var finished = false;
		var timeout = window.setTimeout(function () {
			if (finished) return;
			finished = true;
			fail(worker, 'Font conversion took too long. Try a smaller file or choose WOFF2.');
		}, 60000);

		worker.onerror = function () {
			if (finished) return;
			finished = true;
			window.clearTimeout(timeout);
			fail(worker, 'Font conversion failed. The file may be damaged or use an unsupported font table.');
		};
		worker.onmessage = function (message) {
			if (finished) return;
			finished = true;
			window.clearTimeout(timeout);
			var result = message.data || {};
			if (result.type !== 'complete' || !result.buffer) {
				fail(worker, 'This font could not be converted. Check the file and try again.');
				return;
			}

			try {
				var data = new DataTransfer();
				var baseName = file.name.replace(/\.[^.]+$/, '');
				var converted = new File([result.buffer], baseName + '.woff2', { type: 'font/woff2', lastModified: file.lastModified });
				data.items.add(converted);
				input.files = data.files;
			} catch (error) {
				fail(worker, 'The converted font could not be prepared for upload. Choose WOFF2 instead.');
				return;
			}

			worker.terminate();
			submitButton.disabled = false;
			var percent = result.inputSize > 0 ? Math.round((1 - result.outputSize / result.inputSize) * 100) : 0;
			var sizeMessage = percent > 0 ? ' (' + percent + '% smaller)' : '';
			var axisMessage = '';
			if (result.weightAxis && Number(result.weightAxis.min) >= 1 && Number(result.weightAxis.max) <= 1000 && Number(result.weightAxis.min) < Number(result.weightAxis.max)) {
				var faceType = form.querySelector('[data-dh-font-face-type]');
				var minimum = form.querySelector('[name="weight_min"]');
				var maximum = form.querySelector('[name="weight_max"]');
				if (faceType && minimum && maximum) {
					faceType.value = 'variable';
					faceType.dispatchEvent(new Event('change', { bubbles: true }));
					setRangeValue(minimum, result.weightAxis.min);
					setRangeValue(maximum, result.weightAxis.max);
					axisMessage = ' Variable weight range ' + result.weightAxis.min + '–' + result.weightAxis.max + ' detected.';
				}
			}
			report('Converted to WOFF2' + sizeMessage + '.' + axisMessage + ' Saving the optimized file to your WordPress site now.', false);
			form.dataset.dhFontOptimized = '1';
			form.requestSubmit(submitButton);
		};

		file.arrayBuffer().then(function (buffer) {
			worker.postMessage({ buffer: buffer, format: extension }, [buffer]);
		}).catch(function () {
			if (finished) return;
			finished = true;
			window.clearTimeout(timeout);
			fail(worker, 'The selected font could not be read. Choose the file again and retry.');
		});
	});
}());
