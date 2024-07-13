/* eslint-disable no-lonely-if */
/* eslint-disable max-len */
/* eslint-disable no-restricted-syntax */
/* eslint indent: ["error", "tab", { "SwitchCase": 1 }] */
/* eslint-disable import/prefer-default-export */
/* eslint-disable no-param-reassign */
/* eslint-disable no-loop-func */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* eslint no-undef: "error" */

import { isInt, isFloat } from './func.validate';

/**
 * Buat event di HTML elemen inputan, ketika ketikan berhenti
 * jalankan kondisi atau fungsi tertentu
 * @author Asphira Andreas <arechta911@gmail.com>
 * @param {HTMLElement} element Elemen inputan yang di pilih
 * @param {Number} delay Jumkah waktu tunda dalam satuan detik
 * @param {Function} callback Fungsi untuk di panggil
 * @return {Boolean} Bernilai TRUE jika event sukses di terapkan
 */
export const inputDoneTyping = (element, delay = 5, callback = null) => {
	let output = false;
	// if ((element instanceof Element || element instanceof HTMLDocument) && Number.isInteger(delay) && typeof callback === 'function') {
	if ((element instanceof Element || element instanceof HTMLDocument) && (isInt(delay) || isFloat(delay)) && typeof callback === 'function') {
		let typingTimer;
		let isShift = false;
		let isTab = false;
		// const clearShiftTab = 500;
		// let prevElement = null;
		const typingInterval = 1000 * delay;

		// Ketika tombol ditekan keatas, jalankan hitung mundur
		element.addEventListener('keyup', (e) => {
			clearTimeout(typingTimer);
			if ((e.key).toLowerCase() === 'shift') {
				isShift = true;
				// return;
			}
			if ((e.key).toLowerCase() === 'tab' || e.keyCode === 9) {
				isTab = true;
			}
			// if (isShift && isTab) {
			// 	let isFound = false;
			// 	let maxTries = 20;
			// 	let isUpParent = false;
			// 	let elementPointer = element;
			// 	console.log(prevElement);
			// 	do {
			// 		if (isUpParent) {
			// 			elementPointer = elementPointer.parentNode;
			// 			isUpParent = false;
			// 		}
			// 		prevElement = elementPointer.previousElementSibling;
			// 		console.log(prevElement);
			// 		if (!isVarEmpty(prevElement)) {
			// 			if (['input', 'textarea', 'select', 'button', 'a'].includes(String(prevElement.tagName).toLowerCase())) {
			// 				isFound = true;
			// 				maxTries = 0;
			// 			} else {
			// 				if (prevElement.childElementCount >= 1) {
			// 					const listAllInputs = Array.from(prevElement.querySelectorAll('input, textarea, select, button, a')) || [];
			// 					if (listAllInputs.length >= 1) {
			// 						prevElement = listAllInputs[listAllInputs.length - 1];
			// 						isFound = true;
			// 						maxTries = 0;
			// 					}
			// 				} else {
			// 					isUpParent = true;
			// 				}
			// 			}
			// 		} else {
			// 			isUpParent = true;
			// 			console.log('Up 1 element');
			// 		}
			// 		maxTries -= 1;
			// 	} while (!isFound && maxTries >= 1);
			// 	console.log(prevElement);
			// 	if (isFound) {
			// 		prevElement.focus();
			// 	}
			// 	setTimeout(() => {
			// 		isShift = false;
			// 		isTab = false;
			// 	}, clearShiftTab);
			// }
			if ((e.key).toLowerCase() === 'enter' || e.keyCode === 13 /* || (isShift === false && isTab === true) */) {
				// setTimeout(() => {
				// 	isShift = false;
				// 	isTab = false;
				// }, clearShiftTab);
				e.preventDefault();
				e.stopPropagation();
				callback(e);
			} else {
				if (isShift || isTab) {
					clearTimeout(typingTimer);
					isShift = false;
					isTab = false;
				} else {
					typingTimer = setTimeout(callback.bind(e), typingInterval);
				}
			}
		});
		// Ketika tombol ditekan kebawah, bersihkan hitung mundur
		element.addEventListener('keydown', (e) => {
			clearTimeout(typingTimer);
			if ((e.key).toLowerCase() === 'shift') {
				isShift = true;
				// return;
			}
			if ((e.key).toLowerCase() === 'tab' || e.keyCode === 9) {
				isTab = true;
			}
			// if (isTab === true) {
			// 	e.preventDefault();
			// 	e.stopPropagation();
			// }
		});
		output = true;
	}
	return output;
};

/**
 * Fungsi untuk mendeteksi jenis Event transition
 * tergantung dengan browser yang di pakai
 * @author Asphira Andreas <arechta911@gmail.com>
 * @return {String} Mengembalikan nilai nama/jenis Event
 */
export const getTransitionEndEventName = () => {
	const transitions = {
		transition: 'transitionend',
		OTransition: 'oTransitionEnd',
		MozTransition: 'transitionend',
		WebkitTransition: 'webkitTransitionEnd',
	};
	const bodyStyle = document.body.style;
	for (const transition in transitions) {
		if (bodyStyle[transition] !== undefined) {
			return transitions[transition];
		}
	}
	return 'transitionend';
};

/**
 * Fungsi untuk mendeteksi jenis Event animation
 * tergantung dengan browser yang di pakai
 * @author Asphira Andreas <arechta911@gmail.com>
 * @return {String} Mengembalikan nilai nama/jenis Event
 */
export const getAnimationEndEventName = () => {
	const animations = {
		animation: 'animationend',
		msAnimation: 'MSAnimationEnd',
		OAnimation: 'oAnimationEnd oanimationend',
		MozAnimation: 'animationend',
		WebkitAnimation: 'webkitAnimationEnd',
	};
	const bodyStyle = document.body.style;
	for (const animation in animations) {
		if (bodyStyle[animation] !== undefined) {
			return animations[animation];
		}
	}
	return 'animationend';
};

/**
 * Add an event listener
 * (c) 2017 Chris Ferdinandi, MIT License, https://gomakethings.com
 * @param  {String}   event    The event type
 * @param  {Node}     elem     The element to attach the event to (optional, defaults to window)
 * @param  {Function} callback The callback to run on the event
 * @param  {Boolean}  capture  If true, forces bubbling on non-bubbling events
 */
export const eventOn = (event, elem, callback, capture) => {
	if (typeof (elem) === 'function') {
		capture = callback;
		callback = elem;
		elem = window;
	}
	capture = !!capture;
	elem = typeof elem === 'string' ? document.querySelector(elem) : elem;
	if (!elem) return;
	elem.addEventListener(event, callback, capture);
};

/**
 * Remove an event listener
 * (c) 2017 Chris Ferdinandi, MIT License, https://gomakethings.com
 * @param  {String}   event    The event type
 * @param  {Node}     elem     The element to remove the event to (optional, defaults to window)
 * @param  {Function} callback The callback that ran on the event
 * @param  {Boolean}  capture  If true, forces bubbling on non-bubbling events
 */
export const eventOff = (event, elem, callback, capture) => {
	if (typeof (elem) === 'function') {
		capture = callback;
		callback = elem;
		elem = window;
	}
	capture = !!capture;
	elem = typeof elem === 'string' ? document.querySelector(elem) : elem;
	if (!elem) return;
	elem.removeEventListener(event, callback, capture);
};

/**
 * AnimateCSS
 * Simple function to run animation with promise
 * @author	Daniel Eden
 * @param	{HTMLElement}	element		Element to play with Animate.css
 * @param	{String}		animation	Animation name, see https://animate.style/ for more information
 * @param	{String}		prefix		Prefix of Animation, default: animate__
 * @return	{Promise}		return Resolve
 */

export const animateCSS = (element, animation, prefix = 'animate__', duration = 300, delay = 0) => new Promise((resolve, reject) => {
	// We create a Promise and return it
	const animationName = `${prefix}${animation}`;
	const node = element;

	node.classList.add(`${prefix}animated`, animationName);
	if (isInt(duration) && duration > 50) {
		node.setAttribute('animate-duration', duration);
	}
	if (isInt(delay) && delay > 50) {
		node.setAttribute('animate-delay', delay);
	}

	// When the animation ends, we clean the classes and resolve the Promise
	function handleAnimationEnd(event) {
		event.stopPropagation();
		node.classList.remove(`${prefix}animated`, animationName);
		resolve('Animation ended');
	}

	node.addEventListener('animationend', handleAnimationEnd, { once: true });
});
