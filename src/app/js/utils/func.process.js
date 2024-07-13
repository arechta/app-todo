/* eslint-disable no-underscore-dangle */
/* eslint-disable object-shorthand */
/* eslint-disable func-names */
/* eslint-disable arrow-body-style */
/* eslint-disable import/prefer-default-export */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* eslint no-undef: "error" */

export const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

export const lessThanTimeAgo = (date, second) => {
	const theDate = new Date(date);
	const time = 1000 * second;
	const anTimeAgo = Date.now() - time;
	return theDate > anTimeAgo;
};

export const timeSince = (date) => {
	const seconds = Math.floor((new Date() - date) / 1000);

	let interval = seconds / 31536000;
	if (interval > 1) {
		return `${Math.floor(interval)} tahun`;
	}

	interval = seconds / 2592000;
	if (interval > 1) {
		return `${Math.floor(interval)} bulan`;
	}

	interval = seconds / 86400;
	if (interval > 1) {
		return `${Math.floor(interval)} hari`;
	}

	interval = seconds / 3600;
	if (interval > 1) {
		return `${Math.floor(interval)} jam`;
	}

	interval = seconds / 60;
	if (interval > 1) {
		return `${Math.floor(interval)} menit`;
	}

	return `${Math.floor(seconds)} detik`;
};

export function Timer() {
	return {
		running: false,
		iv: 5000,
		timeout: false,
		callback: function () {},
		start: function (cb, iv, sd) {
			const elm = this;
			clearInterval(this.timeout);
			this.running = true;
			if (cb) this.cb = cb;
			if (iv) this.iv = iv;
			if (sd) elm.execute(elm);
			this.timeout = setTimeout(() => {
				elm.execute(elm);
			}, this.iv);
		},
		execute: function (e) {
			if (!e.running) return false;
			e.cb();
			e.start();
			return true;
		},
		stop: function () {
			this.running = false;
		},
		interval: function (iv) {
			clearInterval(this.timeout);
			this.start(false, iv);
		},
	};
}

function CountDownTimer(duration, granularity) {
	this.duration = duration;
	this.granularity = granularity || 1000;
	this.tickFtns = [];
	this.running = false;
}
CountDownTimer.prototype.start = function () {
	if (this.running) { return; }
	this.running = true;
	const start = Date.now();
	const that = this;
	let diff = 0;
	let obj = {};

	(function timer() {
		diff = that.duration - (((Date.now() - start) / 1000) | 0);

		if (diff > 0) {
			that.instance = setTimeout(timer, that.granularity);
		} else {
			diff = 0;
			that.running = false;
		}

		obj = CountDownTimer.parse(diff);
		that.tickFtns.forEach(function (ftn) {
			ftn.call(this, obj.minutes, obj.seconds, obj.raw);
		}, that);
	}());
};
CountDownTimer.prototype.onTick = function (ftn) {
	if (typeof ftn === 'function') {
		this.tickFtns.push(ftn);
	}
	return this;
};
CountDownTimer.prototype.reset = function (duration) {
	this.duration = duration;
};
CountDownTimer.prototype.kill = function () {
	clearTimeout(this.instance);
};
CountDownTimer.prototype.expired = function () {
	return !this.running;
};
CountDownTimer.parse = function (seconds) {
	return {
		minutes: (seconds / 60) | 0,
		seconds: (seconds % 60) | 0,
		raw: seconds,
	};
};
export { CountDownTimer };

export const checkOnlineStatus = async () => {
	try {
		const myHeaders = new Headers();
		const myRequest = new Request('assets/document/test-connection.php');
		const myInit = { method: 'GET', headers: myHeaders };
		myHeaders.append('pragma', 'no-cache');
		myHeaders.append('cache-control', 'no-cache');

		const online = await fetch(myRequest, myInit);
		return online.status >= 200 && online.status < 300; // either true or false
	} catch (err) {
		return false; // definitely offline
	}
};

export const preloadImages = (srcs) => {
	if (!preloadImages.cache) {
		preloadImages.cache = [];
	}
	let img;
	for (let i = 0; i < srcs.length; i += 1) {
		img = new Image();
		img.src = srcs[i];
		preloadImages.cache.push(img);
	}
};

// Session: get from session (if the value expired it is destroyed)
export const sessionGet = (key) => {
	let output = null;
	const stringValue = window.sessionStorage.getItem(key);
	if (stringValue !== null) {
		const value = JSON.parse(stringValue);
		const expirationDate = new Date(value.expirationDate);

		if (expirationDate > new Date()) {
			output = value.value;
		} else {
			window.sessionStorage.removeItem(key);
		}
	}
	return output;
};

// Session: add into session
export const sessionSet = (key, value, expirationInMin = 10) => {
	const expirationDate = new Date(new Date().getTime() + (60000 * expirationInMin));
	const newValue = {
		value: value,
		expirationDate: expirationDate.toISOString(),
	};

	window.sessionStorage.setItem(key, JSON.stringify(newValue));
};

// base64ArrayBuffer
export const base64ArrayBuffer = (arrayBuffer) => {
	let base64 = '';
	const encodings = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

	const bytes = new Uint8Array(arrayBuffer);
	const { byteLength } = bytes;
	const byteRemainder = byteLength % 3;
	const mainLength = byteLength - byteRemainder;

	let a = 0;
	let b = 0;
	let c = 0;
	let d = 0;
	let chunk = null;

	// Main loop deals with bytes in chunks of 3
	for (let i = 0; i < mainLength; i += 3) {
		// Combine the three bytes into a single integer
		chunk = (bytes[i] << 16) | (bytes[i + 1] << 8) | bytes[i + 2];

		// Use bitmasks to extract 6-bit segments from the triplet
		a = (chunk & 16515072) >> 18; // 16515072 = (2^6 - 1) << 18
		b = (chunk & 258048) >> 12; // 258048 = (2^6 - 1) << 12
		c = (chunk & 4032) >> 6; // 4032 = (2^6 - 1) << 6
		d = chunk & 63; // 63 = 2^6 - 1

		// Convert the raw binary segments to the appropriate ASCII encoding
		base64 += encodings[a] + encodings[b] + encodings[c] + encodings[d];
	}

	// Deal with the remaining bytes and padding
	if (byteRemainder === 1) {
		chunk = bytes[mainLength];

		a = (chunk & 252) >> 2; // 252 = (2^6 - 1) << 2

		// Set the 4 least significant bits to zero
		b = (chunk & 3) << 4; // 3   = 2^2 - 1

		base64 += `${encodings[a]}${encodings[b]}==`;
	} else if (byteRemainder === 2) {
		chunk = (bytes[mainLength] << 8) | bytes[mainLength + 1];

		a = (chunk & 64512) >> 10; // 64512 = (2^6 - 1) << 10
		b = (chunk & 1008) >> 4; // 1008  = (2^6 - 1) << 4

		// Set the 2 least significant bits to zero
		c = (chunk & 15) << 2; // 15    = 2^4 - 1

		base64 += `${encodings[a]}${encodings[b]}${encodings[c]}=`;
	}

	return base64;
};

export const _base64ToArrayBuffer = (base64) => {
	const binaryString = window.atob(base64);
	const len = binaryString.length;
	const bytes = new Uint8Array(len);
	for (let i = 0; i < len; i += 1) {
		bytes[i] = binaryString.charCodeAt(i);
	}
	return bytes.buffer;
};

// Save a file
export const saveFile = async (plaintext, fileName, fileType) => {
	return new Promise((resolve, reject) => {
		const dataView = new DataView(plaintext);
		const blob = new Blob([dataView], { type: fileType });

		if (navigator.msSaveBlob) {
			navigator.msSaveBlob(blob, fileName);
			return resolve();
		} else if (/iPhone|fxios/i.test(navigator.userAgent)) {
			// This method is much slower but createObjectURL
			// is buggy on iOS
			const reader = new FileReader();
			reader.addEventListener('loadend', () => {
				if (reader.error) {
					return reject(reader.error);
				}
				if (reader.result) {
					const a = document.createElement('a');
					// @ts-ignore
					a.href = reader.result;
					a.download = fileName;
					document.body.appendChild(a);
					a.click();
				}
				resolve();
			});
			reader.readAsDataURL(blob);
		} else {
			const downloadUrl = URL.createObjectURL(blob);
			const a = document.createElement('a');
			a.href = downloadUrl;
			a.download = fileName;
			document.body.appendChild(a);
			a.click();
			URL.revokeObjectURL(downloadUrl);
			setTimeout(resolve, 100);
		}
	});
};

// Reorder index Modal (useful for Nested Modals)
export const reorderModal = (el) => {
	$(el).on('show.bs.modal', () => {
		const idx = $(el).data('index');
		$(el).attr('style', (i, s) => {
			return `${(s || '')} z-index: ${(idx + 1)} !important;`;
		});
		setTimeout(() => {
			$('body').find('.modal-backdrop:last-child').attr('style', (i, s) =>{
				return `${(s || '')} z-index: ${idx} !important;`;
			});
		}, 200);
	}).on('hidden.bs.modal', _ => {
		$(el).css('z-index', '');
		$('body').find('.modal-backdrop:last-child').css('z-index', '');
		if ($('.modal.in, .modal.show').length) {
			$('body').addClass('modal-open');
		}
	});
};
