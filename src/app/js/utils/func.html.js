/* eslint-disable no-lonely-if */
/* eslint-disable max-len */
/* eslint-disable no-cond-assign */
/* eslint indent: ["error", "tab", { "SwitchCase": 1 }] */
/* eslint-disable import/prefer-default-export */
/* eslint-disable no-param-reassign */
/* eslint-disable no-loop-func */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* eslint no-undef: "error" */
/* global Scrollbar, lottie */

// import Scrollbar from 'smooth-scrollbar';
import { eventOn, eventOff, animateCSS } from './func.event';
import { isElement, isVarEmpty } from './func.validate';
import animChart from '../../../asset/json/lottie/53461-chart-webble.json';

/**
 * Fungsi untuk menghapus semua children node element pada DOM
 * dari node parent element yang di pilih.
 * @author Asphira Andreas <arechta911@gmail.com>
 * @param {HTMLElement} element Elemen inputan yang di pilih
 * @return {Boolean} Bernilai TRUE jika fungsi sukses di eksekusi
 */
export const removeAllChildNodes = (element) => {
	while (element.firstChild) { element.removeChild(element.lastChild); }
	return (!document.body.contains(element.firstChild));
};

/**
 * Fungsi untuk menghapus node element pada DOM
 * dari node element yang di pilih.
 * @author Asphira Andreas <arechta911@gmail.com>
 * @param {HTMLElement} element Elemen inputan yang di pilih
 * @return {Boolean} Bernilai TRUE jika fungsi sukses di eksekusi
 */
export const removeNodes = (element) => {
	element.parentNode.removeChild(element);
	return (!document.body.contains(element));
};

/**
 * Fungsi untuk menemukan node elemen leluhur/parent terdekat yang
 * memiliki class/id tertentu
 * @author Asphira Andreas <arechta911@gmail.com>
 * @param {HTMLElement} element Elemen yang di pilih
 * @param {String} selector Elemen yang di cari
 * @return {HTMLElement} Mengembalikan hasil elemen yang di dapat
 */
export const findAncestor = (element, selector) => {
	while ((element = element.parentElement) && !((element.matches || element.matchesSelector).call(element, selector)));
	// while ((element = el.parentElement) && !element.classList.contains(selector));
	return element;
};

/**
 * Fungsi untuk memasukan elemen node setelah elemen referensi
 * @author karim79
 * @url https://stackoverflow.com/questions/4793604/how-to-insert-an-element-after-another-element-in-javascript-without-using-a-lib
 * @param {HTMLElement} newNode Elemen yang di masukan
 * @param {HTMLElement} referenceNode Elemen yang di tuju
 */
export const insertAfter = (newNode, referenceNode) => {
	referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
};

/**
 * Fungsi untuk mebuat ulang elemen node untuk menghapus event listener
 * @author user
 * @url https://stackoverflow.com/questions/9251837/how-to-remove-all-listeners-in-an-element
 * @param {HTMLElement} el Elemen yang di masukan
 * @param {Boolean} withChildren rekursif dengan children?
 */
export const recreateNode = (el, withChildren) => {
	if (withChildren) {
		el.parentNode.replaceChild(el.cloneNode(true), el);
	} else {
		const newEl = el.cloneNode(false);
		while (el.hasChildNodes()) newEl.appendChild(el.firstChild);
		el.parentNode.replaceChild(newEl, el);
	}
};

/**
 * Fungsi untuk mengambil nilai Absolute Height dari node elemen
 * @author thetallweeks
 * @url https://stackoverflow.com/questions/10787782/full-height-of-a-html-element-div-including-border-padding-and-margin
 * @param {HTMLElement} el Elemen yang di pilih
 * @param {Boolean} withMargin berserta hitungan margin elemen
 */
export const getElementWidth = (el, withMargin = false) => {
	// Get the DOM Node if you pass in a string
	el = (typeof el === 'string') ? document.querySelector(el) : el;
	const styles = window.getComputedStyle(el);
	const margin = (withMargin) ? (parseFloat(styles.marginLeft) + parseFloat(styles.marginRight)) : 0;
	return Math.ceil(el.offsetWidth + margin);
};

/**
 * Fungsi untuk mengambil nilai Absolute Width dari node elemen
 * @author thetallweeks
 * @url https://stackoverflow.com/questions/10787782/full-height-of-a-html-element-div-including-border-padding-and-margin
 * @param {HTMLElement} el Elemen yang di pilih
 * @param {Boolean} withMargin berserta hitungan margin elemen
 */
export const getElementHeight = (el, withMargin = false) => {
	// Get the DOM Node if you pass in a string
	el = (typeof el === 'string') ? document.querySelector(el) : el;
	const styles = window.getComputedStyle(el);
	const margin = (withMargin) ? (parseFloat(styles.marginTop) + parseFloat(styles.marginBottom)) : 0;
	return Math.ceil(el.offsetHeight + margin);
};

/**
 * Fungsi untuk menampilkan loading pada Chart
 * @author Asphira Andreas <arechta911@gmail.com>
 * @param {HTMLElement} element Elemen inputan yang di pilih
 * @return {Boolean} Bernilai TRUE jika fungsi sukses di eksekusi
 */
export const chartLoading = (element, toggle) => {
	let result = false;
	if (!isElement(element)) return false;
	if (!element.hasAttribute('id')) return false;

	// Create loading animation
	const idChartLoader = `${element.getAttribute('id')}_loading`;
	const parent = findAncestor(element, '.chart-wrapper') || null;
	if (!isVarEmpty(parent)) {
		const chartLoader = document.querySelector(`#${idChartLoader}`);
		if (toggle === 'show' || toggle === true) {
			if (!document.body.contains(chartLoader)) {
				const wrapper = document.createElement('div');
				const lottieWrapper = document.createElement('div');
				const textLoader = document.createElement('p');
				parent.style.position = 'relative';
				wrapper.setAttribute('id', idChartLoader);
				wrapper.setAttribute('style', `position:absolute;top:0;left:0;width:${parent.offsetWidth}px;height:${parent.offsetHeight}px;z-index:1;background-color:rgba(0,0,0,0.8);opacity:0;transition:ease-in-out 300ms opacity;`);
				wrapper.classList.add('center-content');
				lottieWrapper.classList.add('lottie-anim', 'mt-n5');
				lottieWrapper.setAttribute('style', 'width:250px');
				textLoader.innerHTML = 'Please wait, loading the data ...';
				textLoader.classList.add('fnt-style1', 'text-white', 'mt-n5');
				textLoader.setAttribute('data-weight', 'semibold');
				lottie.loadAnimation({
					container: lottieWrapper, // the dom element that will contain the animation
					renderer: 'svg',
					loop: true,
					autoplay: true,
					animationData: animChart, // the path to the animation json
				});
				lottieWrapper.append(textLoader);
				wrapper.appendChild(lottieWrapper);
				parent.appendChild(wrapper);
				animateCSS(wrapper, 'fadeIn', 'animate__', 300, 0).then(() => {
					wrapper.setAttribute('style', `position:absolute;top:0;left:0;width:${parent.offsetWidth}px;height:${parent.offsetHeight}px;z-index:1;background-color:rgba(0,0,0,0.8);opacity:1;transition:ease-in-out 0ms opacity;`);
					result = true;
				});
			}
		}
		if (toggle === 'hide' || toggle === false) {
			if (document.body.contains(chartLoader)) {
				animateCSS(chartLoader, 'fadeOut', 'animate__', 300, 0).then(() => {
					chartLoader.setAttribute('style', `position:absolute;top:0;left:0;width:${parent.offsetWidth}px;height:${parent.offsetHeight}px;background-color:rgba(0,0,0,0.8);opacity:0;transition:ease-in-out 300ms opacity;`);
					removeNodes(chartLoader);
					result = true;
				});
			}
		}
	}
	return result;
};

// ** FADE OUT FUNCTION **
function fadeOut(el) {
	el.style.opacity = 1;
	(function fade() {
		if ((el.style.opacity -= .1) < 0) {
			el.style.display = "none";
		} else {
			requestAnimationFrame(fade);
		}
	})();
};

// ** FADE IN FUNCTION **
function fadeIn(el, display) {
	el.style.opacity = 0;
	el.style.display = display || "block";
	(function fade() {
		var val = parseFloat(el.style.opacity);
		if (!((val += .1) > 1)) {
			el.style.opacity = val;
			requestAnimationFrame(fade);
		}
	})();
};

export const scrollShadow = (function () {
	let elem; let width; let height; let offset; let shadowTop; let shadowBottom; let timeout;
	const event = document.createEvent('Event');

	function initShadows() {
		shadowTop = document.createElement('div');
		shadowTop.classList.add('shadow-top');
		insertAfter(shadowTop, elem);

		shadowBottom = document.createElement('div');
		shadowBottom.classList.add('shadow-bottom');
		insertAfter(shadowBottom, elem);
	}

	function calcPosition() {
		// width = elem.outerWidth();
		width = elem.offsetWidth;
		// height = elem.outerHeight();
		height = elem.offsetHeight;
		offset = {
			top: elem.offsetTop,
			left: elem.offsetLeft,
		};

		// update
		shadowTop.style.width = `${width}px`;
		shadowTop.style.top = `${offset.top}px`;
		shadowTop.style.left = `${offset.left}px`;

		shadowBottom.style.width = `${width}px`;
		shadowBottom.style.top = `${offset.top + height - 20}px`;
		shadowBottom.style.left = `${offset.left}px`;
	}

	function addScrollListener() {
		event.initEvent('shadow', true, true);
		const doShadow = (ev) => {
			const doShadow2 = () => {
				if (elem.scrollY > 0) {
					shadowTop.style.display = 'block';
					// fadeIn(shadowTop, 'block');
					// shadowTop.fadeIn(125);
				} else {
					shadowTop.style.display = 'none';
					// fadeOut(shadowTop, 'none');
					// shadowTop.fadeOut(125);
				}
				if (elem.scrollY + height >= elem.scrollHeight) {
					shadowBottom.style.display = 'none';
					// fadeIn(shadowBottom, 'none');
					// shadowBottom.fadeOut(125);
				} else {
					shadowBottom.style.display = 'block';
					// fadeOut(shadowBottom, 'block');
					// shadowBottom.fadeIn(125);
				}
			};
			doShadow2();
			eventOff('scroll', ev.currentTarget, doShadow2);
			eventOn('scroll', ev.currentTarget, doShadow2);

			const doShadow3 = (ev, scrollbar) => {
				const scrollHeight = scrollbar.getSize().content.height || 0;
				if (ev.offset.y > 0) {
					if (shadowTop.style.display !== 'block') {
						fadeIn(shadowTop, 'block');
					}
					// shadowTop.style.display = 'block';
				} else {
					if (shadowTop.style.display === 'block') {
						fadeOut(shadowTop, 'none');
					}
					// shadowTop.style.display = 'none';
				}

				if (ev.offset.y + height >= scrollHeight) {
					if (shadowBottom.style.display === 'block') {
						fadeOut(shadowBottom, 'none');
					}
					// shadowBottom.style.display = 'none';
				} else {
					if (shadowBottom.style.display !== 'block') {
						fadeIn(shadowBottom, 'block');
					}
					// shadowBottom.style.display = 'block';
				}
			};
			const smoothScrollbar = Scrollbar.get(ev.currentTarget);
			doShadow3({ offset: { x: 0, y: 0 } }, smoothScrollbar);
			if (smoothScrollbar !== undefined && smoothScrollbar !== null) {
				smoothScrollbar.addListener((status) => { doShadow3(status, smoothScrollbar); });
			}
		};
		eventOff('shadow', elem, doShadow);
		eventOn('shadow', elem, doShadow);
	}

	function addResizeListener() {
		const doShadow = () => {
			clearTimeout(timeout);
			timeout = setTimeout(() => {
				calcPosition();
				elem.dispatchEvent(event);
			}, 10);
		};
		eventOn('resize', window, doShadow);
	}

	return {
		init(par) {
			elem = par;
			initShadows();
			calcPosition();
			addScrollListener();
			addResizeListener();
			elem.dispatchEvent(event);
		},
		update(par) {
			elem = par;
			calcPosition();
			elem.dispatchEvent(event);
		},
	};
}());
