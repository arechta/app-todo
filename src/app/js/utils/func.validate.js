/* eslint-disable max-len */
/* eslint-disable no-restricted-syntax */
/* eslint indent: ["error", "tab", { "SwitchCase": 1 }] */
/* eslint-disable import/prefer-default-export */
/* eslint-disable no-param-reassign */
/* eslint-disable no-loop-func */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* eslint no-undef: "error" */

/**
 * Fungsi untuk memvalidasi suatu variabel
 * benar-benar kosong atau tidak
 * @author Asphira Andreas <arechta911@gmail.com>
 * @param {Number|String|Object|Boolean|Function|Element} check variabel yang di periksa
 */
export const isVarEmpty = (check) => {
	if (check === null || check === undefined || check === '' || check.length === 0) {
		return true;
	}
	return false;
};

/**
 * Fungsi untuk memvalidasi suatu variabel
 * bersifat string atau tidak
 * @author Asphira Andreas <arechta911@gmail.com>
 * @param {String} check variabel yang di periksa
 */
export const isString = (check) => Object.prototype.toString.call(check) === '[object String]';

/**
 * Fungsi untuk memvalidasi suatu variabel
 * bersifat integer atau tidak
 * @author Asphira Andreas <arechta911@gmail.com>
 * @param {String} check variabel yang di periksa
 */
export const isInt = (check) => !isNaN(check) && parseInt(Number(check), 10) == check && !isNaN(parseInt(check, 10));

/**
 * Fungsi untuk memvalidasi suatu variabel
 * bersifat float atau tidak
 * @author Asphira Andreas <arechta911@gmail.com>
 * @param {String} check variabel yang di periksa
 */
export const isFloat = (check) => !isNaN(check) && Number(check) === check && check % 1 !== 0;

/**
 * Fungsi untuk memvalidasi suatu variabel
 * bernilai object atau tidak
 * @author Asphira Andreas <arechta911@gmail.com>
 * @param {Object} check Suatu variabel yang di periksa
 */
export const isObject = (check) => {
	if (typeof check === 'object' && !Array.isArray(check) && check !== null) {
		return true;
	}
	return false;
};

/**
 * Fungsi untuk memvalidasi suatu variabel
 * bernilai boolean atau tidak
 * @author Asphira Andreas <arechta911@gmail.com>
 * @param {Boolean} check Suatu variabel yang di periksa
 */
export const isBoolean = (check) => typeof check === 'boolean' || (typeof check === 'object' && check !== null && typeof check.valueOf() === 'boolean');

/**
 * Fungsi untuk memvalidasi suatu variabel
 * bersifat data/HTML Element
 * @author Asphira Andreas <arechta911@gmail.com>
 * @param {HTMLElement} el Suatu variabel yang di periksa
 */
export const isElement = (el) => {
	try {
		// Using W3 DOM2 (works for FF, Opera and Chrome)
		return el instanceof HTMLElement;
	} catch (e) {
		// Browsers not supporting W3 DOM2 don't have HTMLElement and
		// an exception is thrown and we end up here. Testing some
		// properties that all elements have (works on IE7)
		return (typeof el === 'object') && (el.nodeType === 1) && (typeof el.style === 'object') && (typeof el.ownerDocument === 'object');
	}
};

/**
 * Fungsi untuk memvalidasi suatu variabel
 * bersifat fungsi panggilan atau tidak
 * @author Asphira Andreas <arechta911@gmail.com>
 * @param {Function} check Suatu variabel yang di periksa
 */
export const isFunction = (check) => check && {}.toString.call(check) === '[object Function]';

/**
 * Fungsi untuk memvalidasi suatu variabel
 * bernilai alamat/url website atau tidak
 * @author Asphira Andreas <arechta911@gmail.com>
 * @param {String} check Suatu variabel yang di periksa
 */
export const isURL = (check) => {
	const pattern = new RegExp('^(https?:\\/\\/)?'				// protocol
		+ '((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.)+[a-z]{2,}|'	// domain name
		+ '((\\d{1,3}\\.){3}\\d{1,3}))'							// OR ip (v4) address
		+ '(\\:\\d+)?(\\/[-a-z\\d%_.~+]*)*'						// port and path
		+ '(\\?[;&a-z\\d%_.~+=-]*)?'							// query string
		+ '(\\#[-a-z\\d_]*)?$', 'i');							// fragment locator
	return !!pattern.test(check);
};

/**
 * Fungsi untuk memvalidasi suatu variabel
 * bernilai alamat/url website atau tidak
 * @author Pavlo <https://stackoverflow.com/questions/5717093/check-if-a-javascript-string-is-a-url>
 * @param {String} check Suatu variabel yang di periksa
 * @param {Boolean} withHTPP Periksa dengan menggunakan HTPP/s
 */
export const isValidURL = (check, withHTPP = true) => {
	let url;
	try {
		url = new URL(check);
	} catch (_) {
		return false;
	}

	return (withHTPP) ? (url.protocol === 'http:' || url.protocol === 'https:') : true;
};

/**
 * Fungsi untuk memvalidasi suatu variabel
 * bernilai File objek atau tidak
 * @author Asphira Andreas <arechta911@gmail.com>
 * @param {File} check Suatu variabel yang di periksa
 */
export const isFile = (input) => 'File' in window && input instanceof File;

/**
 * Fungsi untuk memvalidasi suatu variabel
 * bernilai Blob objek atau tidak
 * @author Asphira Andreas <arechta911@gmail.com>
 * @param {Blob} check Suatu variabel yang di periksa
 */
export const isBlob = (input) => 'Blob' in window && input instanceof Blob;

/**
 * Fungsi untuk menyatukan 2 objek properti
 * secara rekursif sampai penggabungan mendalam
 * @author Asphira Andreas <arechta911@gmail.com>
 * @param {Object} target Objek variabel yang menjadi target
 * @param {Object} source Objek variabel yang menjadi sumber
 */
export const mergeObjectRecursive = (target, source) => {
	if (isObject(target) && isObject(source)) {
		for (const key in source) {
			if (isObject(source[key])) {
				if (!target[key]) Object.assign(target, { [key]: {} });
				mergeObjectRecursive(target[key], source[key]);
			} else {
				Object.assign(target, { [key]: source[key] });
			}
		}
	}
	return target;
};
