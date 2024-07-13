/* eslint-disable object-curly-newline */
/* eslint-disable no-lonely-if */
/* eslint-disable no-param-reassign */
/* eslint-disable max-len */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* global axios, lottie */
/* eslint no-undef: "error" */

/* == Module import == */
import 'Styles/login.scss';
import VanillaTilt from 'vanilla-tilt';
import FormValidation from './classes/FormValidation';
import EncryptionVW from './classes/EncryptionVW';
import DotGridAware from './classes/DotGridAware';
// import sessionStore from './utils/store.build';
import { isVarEmpty } from './utils/func.validate';
import { animateCSS } from './utils/func.event';
import { getAllFormElements } from './utils/func.general';
import iconProcessing from '../../asset/image/icons/line-md_uploading-loop.svg';
import lottieSuprise from '../../asset/json/lottie/101195-confetti.json';

/* == Main script == */
// DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
	const EVW = new EncryptionVW();
	const dotGrid = new DotGridAware(document.getElementById('dotGrid'), {
		width: 1800,
		height: 1800,
		rowSize: 40,
		dotLength: 44,
		dotMin: 0,
		dotSize: 1,
		dotSizeBase: 5,
		dotColor: '#145CA4',
	});
	dotGrid.draw();

	const cardLogin = document.getElementById('cardLogin');

	let formLogin = null;
	let validateLogin = null;

	formLogin = document.getElementById('formLogin');
	if (document.body.contains(formLogin)) {
		if (cardLogin.classList.contains('d-none')) {
			cardLogin.setAttribute('style', 'opacity: 0;');
			cardLogin.classList.remove('d-none');
			let cardLoginShow = false;
			animateCSS(cardLogin, 'fadeInUp', 'animate__', 300, 500).then(() => {
				if (cardLoginShow === false) {
					const card = VanillaTilt.init(
						document.querySelector('#cardLogin'),
						{
							reverse: true,
							// perspective: 800,
							glare: true,
							'max-glare': 0.6,
							gyroscope: true,
							reset: false,
							'full-page-listening': true,
						},
					);
					cardLogin.removeAttribute('style');
					cardLoginShow = true;
				}
			});
		}

		const elemLottie = cardLogin.querySelector('.lottie-animation');
		let animLottie = null;
		if (document.body.contains(elemLottie)) {
			animLottie = lottie.loadAnimation({
				container: elemLottie, // the dom element that will contain the animation
				renderer: 'svg',
				loop: false,
				autoplay: false,
				animationData: lottieSuprise, // the data to the animation json
			});
		}

		validateLogin = new FormValidation(formLogin, {
			strict: {
				message: true,
				input: true,
			},
			rules: {
				'account-id': {
					required: true,
					minLength: 5,
					maxLength: 40,
				},
				'account-password': {
					required: true,
					minLength: 5,
				},
			},
			messages: {
				'account-id': {
					required: 'Please fill in the <b>Your ID</b>, it is required!',
					minLength: 'Please fill in at least {0} characters!',
					maxLength: 'Must not exceed a maximum of {0} characters!',
				},
				'account-password': {
					required: 'Please fill in the <b>Your Password</b>, it is required!',
					minLength: 'Please fill in at least {0} characters!',
					maxLength: 'Must not exceed a maximum of {0} characters!',
				},
			},
			submitHandler: (form, event) => {
				event.preventDefault();
				event.stopPropagation();
				const formData = new FormData(form);
				const formButtonSubmit = event.submitter.innerHTML;

				// Encrypt before submit to Server, for security reasons
				Object.entries(Object.fromEntries(formData.entries())).forEach(([inputName, inputValue]) => {
					if (String(inputName).indexOf('account') > -1) {
						formData.set(inputName, EVW.encrypt(inputValue, process.env.APP.KEY_ENCRYPT));
					}
				});

				formData.append('ajax', true);
				formData.append('action', 'login-account');

				event.submitter.innerHTML = `<img src="${iconProcessing}" height="20px" class="svg-dark me-2 mr-2" style="opacity: 0.65;" />Please wait!`;
				event.submitter.setAttribute('disabled', 'disabled');
				event.submitter.classList.add('disabled');
				setTimeout(() => {
					axios.post(
						'app/includes/login.inc.php',
						formData,
						{
							headers: { 'Content-Type': 'multipart/form-data' },
						},
					).then(({ data: res }) => {
						if (res.success) {
							const doRedirect = (data) => {
								const decryptURL = EVW.decrypt(data, process.env.APP.KEY_ENCRYPT) || null;
								if (!isVarEmpty(decryptURL)) {
									window.location.replace(decryptURL);
									// console.log(decryptURL);
								}
							};
							if (!isVarEmpty(animLottie)) {
								elemLottie.setAttribute('style', 'z-index: 999 !important;');
								elemLottie.classList.add('d-block');
								animLottie.play();
								animLottie.onComplete = () => {
									elemLottie.removeAttribute('style');
									elemLottie.classList.remove('d-block');
									event.submitter.innerHTML = formButtonSubmit;
									event.submitter.removeAttribute('disabled');
									event.submitter.classList.remove('disabled');
									doRedirect(res.data);
								};
								event.submitter.innerHTML = 'Login successful, wait a moment';
								event.submitter.removeAttribute('disabled');
								event.submitter.classList.remove('disabled');
							} else {
								doRedirect(res.data);
							}
							formLogin.querySelector('.alert').classList.remove('show', 'alert-primary', 'alert-warning', 'alert-danger');
							formLogin.querySelector('.alert').classList.add('mb-2');
							formLogin.querySelector('.alert').innerHTML = '';
						} else {
							const inputList = getAllFormElements(form) || [];
							inputList.forEach((perInput) => {
								const tagType = String(perInput.type).toLowerCase() || '';
								if (tagType === 'password') {
									perInput.value = '';
								}
							});
							formLogin.querySelector('.alert').classList.remove('show', 'alert-primary', 'alert-warning', 'alert-danger');
							formLogin.querySelector('.alert').classList.add('mb-2');
							formLogin.querySelector('.alert').innerHTML = res.message;
							setTimeout(() => {
								event.submitter.innerHTML = formButtonSubmit;
								event.submitter.removeAttribute('disabled');
								event.submitter.classList.remove('disabled');
								formLogin.querySelector('.alert').classList.add((parseInt(res.errcode, 10) !== 9) ? 'alert-warning' : 'alert-danger', 'show');
							}, 200);
							validateLogin.validate();
							animateCSS(cardLogin, 'shakeX');
						}
						// event.submitter.innerHTML = formButtonSubmit;
						// event.submitter.removeAttribute('disabled');
						// event.submitter.classList.remove('disabled');
					}).catch((err) => console.error(err));
				}, 1000);
			},
		});
	}
});
