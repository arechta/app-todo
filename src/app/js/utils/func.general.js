/* eslint indent: ["error", "tab", { "SwitchCase": 1 }] */
/* eslint-disable import/prefer-default-export */
/* eslint-disable no-param-reassign */
/* eslint-disable no-loop-func */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* eslint no-undef: "error" */

import { eventOn, eventOff } from "./func.event";

export const storeValue = (key, value) => {
	if (localStorage) {
		localStorage.setItem(key, value);
	} else {
		$.cookies.set(key, value);
	}
};
export const getStoredValue = (key) => {
	if (localStorage) {
		return localStorage.getItem(key);
	}
	return $.cookies.get(key);
};
export const isInt = (value) => {
	let x = 0;
	return isNaN(value) ? !1 : (x = parseFloat(value), (0 | x) === x);
};

/**
 * Returns a random integer between min (inclusive) and max (inclusive).
 * The value is no lower than min (or the next integer greater than min
 * if min isn't an integer) and no greater than max (or the next integer
 * lower than max if max isn't an integer).
 * Using Math.round() will give you a non-uniform distribution!
 */
export const getRandomInt = (min, max) => {
	min = Math.ceil(min);
	max = Math.floor(max);
	return Math.floor(Math.random() * (max - min + 1)) + min;
};

export const getDayName = (dateStr, locale) => {
	const date = new Date(dateStr);
	return date.toLocaleDateString(locale, { weekday: 'long' });
};

export const addLeadingZeros = (num, totalLength) => String(num).padStart(totalLength, '0');
export const monthNumberFromString = (str) => new Date(`${str} 01 ${new Date().getFullYear()}`).toLocaleDateString('en', { month: '2-digit' });
export const getAllFormElements = (element) => Array.from(element.querySelectorAll('*:not([type="hidden"])')).filter((tag) => ['select', 'textarea', 'input'].includes(tag.tagName.toLowerCase()));
export const clearFormValues = (element) => {
	if (document.body.contains(element)) {
		const inputElements = getAllFormElements(element);
		if (inputElements.length >= 1) {
			inputElements.forEach((input) => {
				// Reset all input value
				const inputTagName = input.tagName.toLowerCase();
				let inputType = null;
				switch (inputTagName) {
					case 'input':
						inputType = input.type.toLowerCase();
						if (inputType.match(/(text|password|number|week|time|date|datetime|datetime-local|search|tel|url)/g)) {
							input.value = '';
						}
						if (inputType.match(/(checkbox|radio)/g)) {
							input.checked = false;
						}
						break;
					case 'textarea':
						input.value = '';
						break;
					case 'select':
						input.selectedIndex = 0;
						break;
					default:
						break;
				}
			});
		}
	}
};
export const randomRGB = () => {
	const o = Math.round;
	const r = Math.random;
	const s = 255;
	return `rgb(${o(r() * s)}, ${o(r() * s)}, ${o(r() * s)})`;
};
export const blockRefresh = (mode = false) => {
	window.ctrlKeyDown = false;
	window.allowReload = true;
	window.doCheckReload = (e) => {
		if (window.allowReload === false) {
			// Cancel the event
			e.preventDefault();
			e.stopPropagation();
			e.stopImmediatePropagation();
			// Chrome requires returnValue to be set
			e.returnValue = 'You cannot reload the page, because there is a process currently running!';
			return false;
		} else {
			e.cancelBubble = false;
			e.cancelable = false;
			e.defaultPrevented = false;
			e.returnValue = '';
			window.location.reload();
			return true;
		}
	};
	window.allowRefresh = (e) => {
		if ((e.which || e.keyCode) === 116 || ((e.which || e.keyCode) === 82 && window.ctrlKeyDown)) {
			delete window.ctrlKeyDown;
			eventOff('keydown', document, window.allowRefresh);
			delete window.allowRefresh;
			delete window.onCtrlPressed;
			// Pressing F5 or Ctrl+R
			window.location.reload();
			return true;
		} else if ((e.which || e.keyCode) === 17) {
			// Pressing  only Ctrl
			window.ctrlKeyDown = true;
		}
		return true;
	};
	window.disableRefresh = (e) => {
		if ((e.which || e.keyCode) === 116 || ((e.which || e.keyCode) === 82 && window.ctrlKeyDown)) {
			// Pressing F5 or Ctrl+R
			e.preventDefault();
		} else if ((e.which || e.keyCode) === 17) {
			// Pressing  only Ctrl
			window.ctrlKeyDown = true;
		}
	};
	window.onCtrlPressed = (e) => {
		// Key up Ctrl
		if ((e.which || e.keyCode) === 17) {
			window.ctrlKeyDown = false;
		}
	};
	if (mode) {
		// Block refresh window
		window.allowReload = false;
		eventOff('beforeunload', window, window.doCheckReload);
		eventOn('beforeunload', window, window.doCheckReload);
		eventOff('keydown', document, window.disableRefresh);
		eventOn('keydown', document, window.disableRefresh);
		eventOff('keyup', document, window.onCtrlPressed);
		eventOn('keyup', document, window.onCtrlPressed);
	} else {
		// delete window.ctrlKeyDown;
		window.allowReload = true;
		eventOff('keydown', document, window.disableRefresh);
		eventOn('keydown', document, window.allowRefresh);
		delete window.disableRefresh;
		// delete window.onCtrlPressed;
	}
};
