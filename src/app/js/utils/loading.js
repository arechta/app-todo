/* eslint-disable no-loop-func */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* global lottie */
/* eslint no-undef: "error" */

import animTruck from '../../../asset/json/lottie/94994-loading-car.json';
import animText from '../../../asset/json/lottie/4693-loading.json';
import { ConvertStringToHTML } from './func.convert';

/*
	Required plugins:
	- Lottie-web
	- jQuery
*/
let lottieLoading1 = null;
let lottieLoading2 = null;
const HTMLString = '<div id="loadingPopup" style="display: none;"><div class="lottie-truck mb-3"></div><div class="lottie-text"></div><div class="background-blur"></div></div>';
const showLoading = () => {
	if (document.body.contains(document.getElementById('loadingPopup'))) {
		if (document.getElementById('loadingPopup').style.display !== 'block') {
			$('#loadingPopup').find('.lottie-truck, .lottie-text').show();
			$('#loadingPopup').slideDown(400, () => {
				lottieLoading1.play();
				lottieLoading2.playSegments([0, 62], true);
			});
		}
	}
};
const hideLoading = () => {
	if (document.body.contains(document.getElementById('loadingPopup'))) {
		if (document.getElementById('loadingPopup').style.display !== 'none') {
			$('#loadingPopup').find('.lottie-truck, .lottie-text').fadeOut();
			$('#loadingPopup').slideUp(400, () => {
				lottieLoading1.stop();
				lottieLoading2.stop();
			});
		}
	}
};

// If element not exist, create it
if (!document.body.contains(document.getElementById('loadingPopup'))) {
	const html2Node = ConvertStringToHTML(HTMLString);
	while (html2Node.firstChild) {
		document.body.appendChild(html2Node.firstChild);
	}
}

// Init lottie animation
if (document.body.contains(document.getElementById('loadingPopup'))) {
	// Lottie truck
	lottieLoading1 = lottie.loadAnimation({
		container: document.getElementById('loadingPopup').querySelector('.lottie-truck'), // the dom element that will contain the animation
		renderer: 'svg',
		loop: true,
		autoplay: false,
		animationData: animTruck, // the path to the animation json
	});
	// Lottie text
	lottieLoading2 = lottie.loadAnimation({
		container: document.getElementById('loadingPopup').querySelector('.lottie-text'), // the dom element that will contain the animation
		renderer: 'svg',
		loop: false,
		autoplay: false,
		animationData: animText, // the path to the animation json
	});
}

export {
	showLoading,
	hideLoading,
};
