/* eslint-disable no-lonely-if */
/* eslint-disable max-len */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* global axios */
/* eslint no-undef: "error" */

/* == Module import == */
import 'Styles/session-list.scss';
import { scrollShadow } from './utils/func.html';
import { getTransitionEndEventName } from './utils/func.event';

/* == Main script == */
// DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
	const shadowOverflow = Array.from(document.getElementsByClassName('shadow-scroll')) || [];
	shadowOverflow.forEach((el) => {
		scrollShadow.init(el);
	});

	// Tombol aksi
	const deviceList = Array.from(document.getElementsByClassName('device-item')) || [];
	if (deviceList.length > 1) {
		deviceList.forEach((device, index) => {
			device.querySelector('.btn').addEventListener('click', (e) => {
				const deviceBtn = e.currentTarget;
				const identity = deviceBtn.getAttribute('data-identity') || null;
				axios.post(window.location.pathname, {
					ajax: true,
					session_clear: true,
					session_index: index,
					session_identity: identity,
				}).then(({ data: res }) => {
					if (res.success) {
						const transitionEndEventName = getTransitionEndEventName();
						deviceList[index].addEventListener(transitionEndEventName, (el) => {
							el.currentTarget.parentNode.removeChild(el.currentTarget);
						});
						deviceList[index].classList.add('slideUp');
						// if (data.hasOwnProperty('toast')) {
						// 	if (data.toast != '' && data.toast != null && data.toast != undefined) { sendToastByB64(data.toast); }
						// }
					} else {
						// if (data.hasOwnProperty('toast')) {
						// 	if (data.toast != '' && data.toast != null && data.toast != undefined) { sendToastByB64(data.toast); }
						// }
					}
				}).catch((err) => console.error(err));
			});
		});
	}
	const sessionThisUse = document.getElementById('sessionsThisUse');
	if (document.body.contains(sessionThisUse)) {
		sessionThisUse.addEventListener('click', () => {
			// $.ajax({
			// 		method: 'POST',
			// 		url: window.location.pathname,
			// 		data: { ajax: true, session_take: true },
			// 		dataType: 'json',
			// 		success: function (data) {
			// 			if (data.success) {
			// 				if (data.hasOwnProperty('redirect')) {
			// 					window.location.href = data.redirect;
			// 				}
			// 			} else {
			// 				if (data.hasOwnProperty('redirect')) {
			// 					window.location.href = data.redirect;
			// 				}
			// 			}
			// 		},
			// 		error: function (jqXHR, textStatus, errorThrown) {
			// 			console.log(`Error Ajax(jQuery): ${errorThrown}, Please try again!`);
			// 		}
			// 	});
			axios.post(window.location.pathname, {
				ajax: true,
				session_clear: true,
				session_take: true,
			}).then(({ data: res }) => {
				if (res.success) {
					if (Object.hasOwnProperty.call(res, 'redirect')) {
						window.location.href = res.redirect;
					}
				} else {
					if (Object.hasOwnProperty.call(res, 'redirect')) {
						window.location.href = res.redirect;
					}
				}
			}).catch((err) => console.error(err));
		});
	}
	// if ($('.device-item').length > 1) {
	// 	$('.device-item').each(function(e) {
	// 		$(this).find('.btn').on('click', function(e) {
	// 			let $this = $(this);
	// 			let idx = $(this).parents('.device-item').index();
	// 			let identity = $this.data('identity');
	// 			$.ajax({
	// 				method: 'POST',
	// 				url: window.location.pathname,
	// 				data: { ajax: true, session_clear: true, session_index: idx, session_identity: identity },
	// 				dataType: 'json',
	// 				success: function (data) {
	// 					if (data.success) {
	// 						$this.parents('.session-devices').find('.device-item').eq(idx).slideUp().promise().done(function(e) { e.currentTarget.remove(); });
	// 						if (data.hasOwnProperty('toast')) {
	// 							if (data.toast != '' && data.toast != null && data.toast != undefined) { sendToastByB64(data.toast); }
	// 						}
	// 					} else {
	// 						if (data.hasOwnProperty('toast')) {
	// 							if (data.toast != '' && data.toast != null && data.toast != undefined) { sendToastByB64(data.toast); }
	// 						}
	// 					}
	// 				},
	// 				error: function (jqXHR, textStatus, errorThrown) {
	// 					console.log(`Error Ajax(jQuery): ${errorThrown}, Please try again!`);
	// 				}
	// 			});
	// 		});
	// 	});
	// }
	// $('#sessionsThisUse').on('click', function (e) {
	// 	$.ajax({
	// 		method: 'POST',
	// 		url: window.location.pathname,
	// 		data: { ajax: true, session_take: true },
	// 		dataType: 'json',
	// 		success: function (data) {
	// 			if (data.success) {
	// 				if (data.hasOwnProperty('redirect')) {
	// 					window.location.href = data.redirect;
	// 				}
	// 			} else {
	// 				if (data.hasOwnProperty('redirect')) {
	// 					window.location.href = data.redirect;
	// 				}
	// 			}
	// 		},
	// 		error: function (jqXHR, textStatus, errorThrown) {
	// 			console.log(`Error Ajax(jQuery): ${errorThrown}, Please try again!`);
	// 		}
	// 	});
	// });
});
