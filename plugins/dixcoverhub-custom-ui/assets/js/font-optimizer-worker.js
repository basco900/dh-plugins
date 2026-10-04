var encoderPromise;

function readWeightAxis(bytes) {
	if (bytes.byteLength < 12) return null;
	var view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
	var tableCount = view.getUint16(4, false);
	var directoryEnd = 12 + tableCount * 16;
	if (!tableCount || tableCount > 2048 || directoryEnd > bytes.byteLength) return null;
	var fvarStart = -1;
	var fvarLength = 0;
	for (var index = 0; index < tableCount; index += 1) {
		var record = 12 + index * 16;
		var tag = String.fromCharCode(bytes[record], bytes[record + 1], bytes[record + 2], bytes[record + 3]);
		if (tag !== 'fvar') continue;
		fvarStart = view.getUint32(record + 8, false);
		fvarLength = view.getUint32(record + 12, false);
		break;
	}
	if (fvarStart < 0 || fvarLength < 16 || fvarStart + fvarLength > bytes.byteLength) return null;
	var axesOffset = view.getUint16(fvarStart + 4, false);
	var axisCount = view.getUint16(fvarStart + 8, false);
	var axisSize = view.getUint16(fvarStart + 10, false);
	if (!axisCount || axisCount > 128 || axisSize < 20 || axesOffset + axisCount * axisSize > fvarLength) return null;
	for (var axisIndex = 0; axisIndex < axisCount; axisIndex += 1) {
		var axis = fvarStart + axesOffset + axisIndex * axisSize;
		var axisTag = String.fromCharCode(bytes[axis], bytes[axis + 1], bytes[axis + 2], bytes[axis + 3]);
		if (axisTag !== 'wght') continue;
		var minimum = Math.round(view.getInt32(axis + 4, false) / 65536);
		var maximum = Math.round(view.getInt32(axis + 12, false) / 65536);
		if (minimum >= 1 && maximum <= 1000 && minimum < maximum) return { min: minimum, max: maximum };
	}
	return null;
}

function loadEncoder() {
	if (!encoderPromise) {
		encoderPromise = import(new URL('../vendor/woff2-encode-wasm/index.js', import.meta.url).toString()).then(function (encoder) {
			var wasmUrl = new URL('../vendor/woff2-encode-wasm/encoder.wasm', import.meta.url);
			return fetch(wasmUrl).then(function (response) {
				if (!response.ok) throw new Error('The local WOFF2 encoder could not be loaded.');
				return encoder.init(response).then(function () { return encoder; });
			});
		});
	}
	return encoderPromise;
}

self.addEventListener('message', async function (event) {
	var request = event.data || {};
	try {
		var bytes = new Uint8Array(request.buffer || new ArrayBuffer(0));
		if (!bytes.length || bytes.length > 10 * 1024 * 1024) throw new Error('The font is empty or too large.');
		var signature = String.fromCharCode(bytes[0], bytes[1], bytes[2], bytes[3]);
		var isSupportedSfnt = signature === '\u0000\u0001\u0000\u0000' || signature === 'true' || signature === 'typ1' || signature === 'OTTO';
		if (!isSupportedSfnt) {
			throw new Error('The file extension does not match a supported TrueType/OpenType font.');
		}

		var weightAxis = readWeightAxis(bytes);
		var encoder = await loadEncoder();
		var output = await encoder.encode(bytes);
		var buffer = output.buffer.slice(output.byteOffset, output.byteOffset + output.byteLength);
		self.postMessage({ type: 'complete', buffer: buffer, inputSize: bytes.byteLength, outputSize: output.byteLength, weightAxis: weightAxis }, [buffer]);
	} catch (error) {
		self.postMessage({ type: 'error' });
	}
});
