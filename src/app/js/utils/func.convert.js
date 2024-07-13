/* eslint-disable import/prefer-default-export */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* eslint no-undef: "error" */

export const ConvertStringToHTML = (html) => {
	const e = document.createElement('div');
	e.innerHTML = html;
	return e;
};

export const stringToCamelize = (str) => str.replace(/(?:^\w|[A-Z]|\b\w)/g, (word, index) => (index === 0 ? word.toLowerCase() : word.toUpperCase())).replace(/\s+/g, '');

export const stringToHTML = (str) => {
	const support = (() => {
		if (!window.DOMParser) return false;
		const parser = new DOMParser();
		try {
			parser.parseFromString('x', 'text/html');
		} catch (err) {
			return false;
		}
		return true;
	})();

	// If DOMParser is supported, use it
	if (support) {
		const parser = new DOMParser();
		const doc = parser.parseFromString(str, 'text/html');
		return doc.body;
	}

	// Otherwise, fallback to old-school method
	const dom = document.createElement('div');
	dom.innerHTML = str;
	return dom;
};

export const parseNumber = (value, locales = navigator.languages) => {
	const example = Intl.NumberFormat(locales).format('1.1');
	const cleanPattern = new RegExp(`[^-+0-9${example.charAt(1)}]`, 'g');
	const cleaned = value.replace(cleanPattern, '');
	const normalized = cleaned.replace(example.charAt(1), '.');
	return parseFloat(normalized);
};
