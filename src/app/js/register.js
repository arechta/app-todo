/* eslint-disable object-curly-newline */
/* eslint-disable no-lonely-if */
/* eslint-disable no-param-reassign */
/* eslint-disable max-len */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* global axios */
/* eslint no-undef: "error" */

/* == Module import == */
import 'Styles/register.scss';
import lottie from 'lottie-web/build/player/lottie_light.min';
import IMask from 'imask/esm/imask';
import 'imask/esm/masked/function';
import 'imask/esm/masked/pattern';
import 'imask/esm/masked/number';
import 'imask/esm/masked/dynamic';
import FormValidation from './classes/FormValidation';
import { isVarEmpty, isString, isObject, isElement } from './utils/func.validate';
import { animateCSS, eventOn, eventOff } from './utils/func.event';
import { CountDownTimer } from './utils/func.process';
import sessionStore from './utils/store.build';
import EncryptionVW from './classes/EncryptionVW';
import { clearFormValues, addLeadingZeros } from './utils/func.general';
import { removeAllChildNodes, scrollShadow } from './utils/func.html';
import iconProcessing from '../../asset/image/icons/line-md_uploading-loop.svg';
import lottieComplete from '../../asset/json/lottie/70295-lottie-completed-animation.json';
import lottieFailed from '../../asset/json/lottie/94303-failed.json';
import lottieChecking from '../../asset/json/lottie/83770-checking-doc.json';

/* == Main script == */
// DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
	const EVW = new EncryptionVW();

	const cardRegister = document.getElementById('cardRegister');
	const cardOTP = document.getElementById('cardOTP');
	const cardSubmit = document.getElementById('cardSubmit');

	let formRegister1 = null;
	let validateRegister1 = null;
	let formOTP = null;
	let validateOTP = null;
	let buttonResendOTP = null;
	let timerResendOTP = null;

	const dataRegister = sessionStore.get(`${process.env.APP.PREFIX}register_data`) || null;
	const dataVerifyOTP = sessionStore.get(`${process.env.APP.PREFIX}verify_otp`) || null;

	const checkProgressStep = (jsonRegister, jsonVerifyOTP) => {
		let dataFormRegister = jsonRegister;
		let dataFormVerifyOTP = jsonVerifyOTP;
		if (!isVarEmpty(dataFormRegister) && isString(dataFormRegister)) {
			dataFormRegister = JSON.parse(dataFormRegister) || null;
		}
		if (!isVarEmpty(dataFormVerifyOTP) && isString(dataFormVerifyOTP)) {
			dataFormVerifyOTP = JSON.parse(dataFormVerifyOTP) || null;
		}

		// Progress to OTP
		if (isObject(dataFormRegister) && isObject(dataFormVerifyOTP)) {
			if (document.body.contains(cardOTP)) {
				buttonResendOTP = cardOTP.querySelector('.btn-resend-otp');
				const buttonResendOTPContent = buttonResendOTP.innerHTML;
				const resendOTP = () => {
					if (dataFormVerifyOTP.timeout === 0) {
						const formAPI = new FormData();
						formAPI.append('action', 'request');
						formAPI.append('method', 'short');
						formAPI.append('token', EVW.encrypt(process.env.APP.API_TOKEN, process.env.APP.KEY_ENCRYPT));
						formAPI.append('data', JSON.stringify({ phone_number: String(dataFormRegister['customer-phone']).replace(/[^0-9+]/g, ''), message: '{template_registration}', customer: dataFormRegister['customer-name'] }));

						buttonResendOTP.innerHTML = `${buttonResendOTPContent} (Please wait)`;
						buttonResendOTP.classList.remove('text-primary', 'text-secondary', 'text-orange', 'text-blue', 'text-info', 'text-danger');
						buttonResendOTP.classList.add('disabled', 'text-gray');
						buttonResendOTP.setAttribute('disabled', 'disabled');

						axios.post(
							`${process.env.APP.API_URL}/auth/verify-otp.inc.php`,
							formAPI,
							{
								headers: { 'Content-Type': 'multipart/form-data' },
							},
						).then(({ data: res }) => {
							if (res.success) {
								buttonResendOTP.innerHTML = buttonResendOTPContent;
								sessionStore.set(`${process.env.APP.PREFIX}verify_otp`, res.data);
								checkProgressStep(dataFormRegister, res.data);
								// eventOff('click', buttonResendOTP, resendOTP);
							}
						}).catch((err) => console.error(err));
					}
				};
				eventOff('click', buttonResendOTP, resendOTP);
				eventOn('click', buttonResendOTP, resendOTP);

				timerResendOTP = new CountDownTimer(dataFormVerifyOTP.timeout);
				timerResendOTP.onTick((minutes, seconds, raw) => {
					dataFormVerifyOTP.timeout = raw;
					sessionStore.set(`${process.env.APP.PREFIX}verify_otp`, JSON.stringify(dataFormVerifyOTP));
					buttonResendOTP.innerHTML = `${buttonResendOTPContent} <b>(${addLeadingZeros(minutes, 2)}:${addLeadingZeros(seconds, 2)})</b>`;
					if (raw >= 1) {
						buttonResendOTP.classList.remove('text-primary', 'text-secondary', 'text-orange', 'text-blue', 'text-info', 'text-danger', 'text-gray');
						buttonResendOTP.classList.add('disabled', 'text-danger');
						buttonResendOTP.setAttribute('disabled', 'disabled');
					} else {
						buttonResendOTP.classList.remove('text-primary', 'text-secondary', 'text-orange', 'text-blue', 'text-info', 'text-danger', 'text-gray');
						buttonResendOTP.classList.remove('disabled');
						buttonResendOTP.classList.add('text-orange');
						buttonResendOTP.removeAttribute('disabled');
						buttonResendOTP.innerHTML = buttonResendOTPContent;
					}
				});
				timerResendOTP.start();

				const doFormInput = () => {
					if (isVarEmpty(validateOTP)) {
						if (document.body.contains(formOTP)) {
							validateOTP = new FormValidation(formOTP, {
								strict: {
									message: true,
									input: true,
								},
								rules: {
									'verify-otp_1': {
										required: true,
										maxLength: 1,
									},
									'verify-otp_2': {
										required: true,
										maxLength: 1,
									},
									'verify-otp_3': {
										required: true,
										maxLength: 1,
									},
									'verify-otp_4': {
										required: true,
										maxLength: 1,
									},
								},
								messages: {
									'verify-otp_1': {
										required: {
											error: 'Fill!',
											success: '',
										},
										maxLength: {
											error: '',
											success: '',
										},
									},
									'verify-otp_2': {
										required: {
											error: 'Fill!',
											success: '',
										},
										maxLength: {
											error: '',
											success: '',
										},
									},
									'verify-otp_3': {
										required: {
											error: 'Fill!',
											success: '',
										},
										maxLength: {
											error: '',
											success: '',
										},
									},
									'verify-otp_4': {
										required: {
											error: 'Fill!',
											success: '',
										},
										maxLength: {
											error: '',
											success: '',
										},
									},
								},
								submitHandler: (form, event) => {
									event.preventDefault();
									event.stopPropagation();
									const formData = new FormData(form);
									const formAPI = new FormData();
									const formRegister = new FormData();
									const codeOTP = String(formData.get('verify-otp_1')) + String(formData.get('verify-otp_2')) + String(formData.get('verify-otp_3')) + String(formData.get('verify-otp_4'));
									const submitButton = form.querySelector('[type="submit"]');
									let submitContent = '';

									// Server data for API
									dataFormVerifyOTP = sessionStore.get(`${process.env.APP.PREFIX}verify_otp`);
									if (!isVarEmpty(dataFormVerifyOTP) && isString(dataFormVerifyOTP)) {
										dataFormVerifyOTP = JSON.parse(dataFormVerifyOTP) || null;
									}
									formAPI.append('action', 'verify');
									formAPI.append('method', 'short');
									formAPI.append('token', EVW.encrypt(process.env.APP.API_TOKEN, process.env.APP.KEY_ENCRYPT));
									formAPI.append('data', JSON.stringify({ otp: codeOTP, uid: dataFormVerifyOTP.uid }));

									// Serve data for Register
									for (const key in dataFormRegister) { formRegister.append(key, dataFormRegister[key]); }
									formRegister.append('ajax', true);
									formRegister.append('action', 'register-account');

									if (document.body.contains(submitButton)) {
										submitContent = submitButton.innerHTML;
										submitButton.classList.add('disabled');
										submitButton.setAttribute('disabled', 'disabled');
										submitButton.innerHTML = `<img src="${iconProcessing}" height="20px" class="svg-dark me-2 mr-2" style="opacity: 0.65;" />Please wait!`;
									}

									axios.post(
										`${process.env.APP.API_URL}/auth/verify-otp.inc.php`,
										formAPI,
										{
											headers: { 'Content-Type': 'multipart/form-data' },
										},
									).then(({ data: res }) => {
										if (res.success) {
											animateCSS(cardOTP, 'fadeOutDown').then(() => {
												cardOTP.classList.add('d-none');
												cardSubmit.setAttribute('style', 'opacity: 0;');
												cardSubmit.classList.remove('d-none');
												animateCSS(cardSubmit, 'fadeInUp').then(() => {
													cardSubmit.removeAttribute('style');
													axios.post(
														'app/includes/register.inc.php',
														formRegister,
														{
															headers: { 'Content-Type': 'multipart/form-data' },
														},
													).then(({ data: res2 }) => {
														let animLottie = null;
														const elCardLottie = cardSubmit.querySelector('.lottie-animation');
														const elCardContent = cardSubmit.querySelector('.cardview-content');
														const messageCard = elCardContent.querySelector('p').innerHTML;
														if (res2.success) {
															elCardContent.querySelector('p').setAttribute('style', 'font-size: 14px;');
															animateCSS(elCardLottie, 'fadeOut').then(() => {
																elCardLottie.setAttribute('style', 'opacity: 0; width: 60%; max-height: 200px; z-index: -1;');
																removeAllChildNodes(elCardLottie);
																animLottie = lottie.loadAnimation({
																	container: elCardLottie, // the dom element that will contain the animation
																	renderer: 'svg',
																	loop: false,
																	autoplay: false,
																	animationData: lottieComplete, // the data to the animation json
																});
																animLottie.play();
																elCardLottie.classList.add('my-n5', 'mx-auto');
																animateCSS(elCardLottie, 'fadeIn').then(() => {
																	elCardLottie.setAttribute('style', 'width: 60%; max-height: 200px; z-index: -1;');
																});
																// animLottie.play();
															});
															animateCSS(elCardContent.querySelector('p'), 'fadeOut').then(() => {
																elCardContent.querySelector('p').innerHTML = 'We have received your data successfully, please wait for the further checking process we will send the status of your data via registered<br/>Email/Whatsapp';
																animateCSS(elCardContent.querySelector('p'), 'fadeIn');
																cardSubmit.querySelector('.btn-finish').classList.add('is-show', 'mt-5', 'position-relative');
																cardSubmit.querySelector('.illust-email').classList.add('is-show');
																const doGoBack = () => {
																	eventOff('click', cardSubmit.querySelector('.btn-finish'), doGoBack);
																	animateCSS(cardSubmit, 'fadeOutDown').then(() => {
																		cardSubmit.classList.add('d-none');
																		// checkProgressStep(null, null);
																		window.location.reload();
																	});
																};
																eventOn('click', cardSubmit.querySelector('.btn-finish'), doGoBack);
															});
															timerResendOTP.kill();
															sessionStore.remove(`${process.env.APP.PREFIX}register_data`);
															sessionStore.remove(`${process.env.APP.PREFIX}verify_otp`);
														} else {
															elCardContent.querySelector('p').setAttribute('style', 'font-size: 14px;');
															animateCSS(elCardLottie, 'fadeOut').then(() => {
																elCardLottie.setAttribute('style', 'opacity: 0; width: 30%; max-height: 200px; z-index: -1;');
																removeAllChildNodes(elCardLottie);
																animLottie = lottie.loadAnimation({
																	container: elCardLottie, // the dom element that will contain the animation
																	renderer: 'svg',
																	loop: (res2.errcode >= 3),
																	autoplay: false,
																	animationData: (res2.errcode >= 3) ? lottieChecking : lottieFailed, // the data to the animation json
																});
																animLottie.play();
																elCardLottie.classList.add('my-n4', 'mx-auto');
																animateCSS(elCardLottie, 'fadeIn').then(() => {
																	elCardLottie.setAttribute('style', 'width: 30%; max-height: 200px; z-index: -1;');
																});
																// animLottie.play();
															});
															animateCSS(elCardContent.querySelector('p'), 'fadeOut').then(() => {
																let resMessage = res2.message;
																if (res2.errcode >= 3) {
																	resMessage = resMessage.replace('PENDING APPROVAL', '<b>PENDING APPROVAL</b>');
																	resMessage = resMessage.replace('Email/Whatsapp', '<b>Email/Whatsapp</b>');
																	resMessage = resMessage.replace('\\n', '<br/>');
																}
																elCardContent.querySelector('p').innerHTML = resMessage;
																animateCSS(elCardContent.querySelector('p'), 'fadeIn');
																cardSubmit.querySelector('.btn-finish').innerHTML = 'Go back';
																cardSubmit.querySelector('.btn-finish').classList.remove('btn-orange');
																cardSubmit.querySelector('.btn-finish').classList.add('is-show', 'mt-5', 'btn-dark', 'position-relative');
																const doGoBack = () => {
																	eventOff('click', cardSubmit.querySelector('.btn-finish'), doGoBack);
																	animateCSS(cardSubmit, 'fadeOutDown').then(() => {
																		cardSubmit.classList.add('d-none');
																		checkProgressStep(JSON.stringify(dataFormRegister), null);
																		window.location.reload();
																	});
																};
																eventOn('click', cardSubmit.querySelector('.btn-finish'), doGoBack);
															});
															timerResendOTP.kill();
															sessionStore.remove(`${process.env.APP.PREFIX}verify_otp`);
														}
													}).catch((err) => console.error(err));
												// cardSubmit.
												});
											});
										} else {
											cardOTP.querySelector('.alert').classList.remove('show', 'alert-primary', 'alert-warning', 'alert-danger');
											cardOTP.querySelector('.alert').innerHTML = res.message;
											setTimeout(() => {
												cardOTP.querySelector('.alert').classList.add((parseInt(res.errcode, 10) !== 9) ? 'alert-warning' : 'alert-danger', 'show');
											}, 200);

											if (parseInt(res.errcode, 10) === 6 || parseInt(res.errcode, 10) === 7 || parseInt(res.errcode, 10) === 8) {
												const theForm = validateOTP.form;
												if (isElement(theForm)) {
													if (document.body.contains(theForm)) {
														clearFormValues(theForm);
														validateOTP.validate();
													}
												}
											}
										}

										if (document.body.contains(submitButton)) {
											submitButton.classList.remove('disabled');
											submitButton.removeAttribute('disabled');
											submitButton.innerHTML = submitContent;
										}
									}).catch((err) => console.error(err));
								},
							});
						}
					}
				};
				if (cardOTP.classList.contains('d-none')) {
					cardOTP.setAttribute('style', 'opacity: 0;');
					cardOTP.classList.remove('d-none');
					animateCSS(cardOTP, 'fadeInUp').then(() => {
						cardOTP.removeAttribute('style');
						formOTP = cardOTP.querySelector('#formOTP');
						doFormInput();
					});
				} else {
					doFormInput();
				}
			}
		} else {
			if (document.body.contains(cardRegister)) {
				if (cardRegister.classList.contains('d-none')) {
					cardRegister.setAttribute('style', 'opacity: 0;');
					cardRegister.classList.remove('d-none');
					animateCSS(cardRegister, 'fadeInUp').then(() => {
						cardRegister.removeAttribute('style');
						scrollShadow.init(cardRegister.querySelector('.shadow-element'));
					});
				}
			}
		}
	};
	checkProgressStep(dataRegister, dataVerifyOTP);

	formRegister1 = document.getElementById('formRegister1');
	if (document.body.contains(formRegister1)) {
		validateRegister1 = new FormValidation(formRegister1, {
			strict: {
				message: true,
				input: true,
			},
			rules: {
				'customer-name': {
					required: true,
					minLength: 5,
					maxLength: 100,
				},
				'customer-address': {
					required: true,
					minLength: 20,
				},
				'customer-product': {
					required: true,
				},
				'customer-phone': {
					required: true,
					minLength: 10,
					maxLength: 20,
					isPhoneNumber: true,
				},
				'customer-email': {
					required: true,
					minLength: 5,
					maxLength: 40,
					isEmail: true,
				},
				'customer-reference': {
					required: true,
				},
				'customer-agree-toc': {
					required: true,
				},
			},
			messages: {
				'customer-name': {
					required: 'Please fill in the <b>Company Name</b>, it is mandatory!',
					minLength: 'Please fill in at least {0} characters!',
					maxLength: 'Must not exceed a maximum of {0} characters!',
				},
				'customer-address': {
					required: 'Please fill in the <b>Company Address</b>, it is mandatory!',
					minLength: 'Please fill in at least {0} characters!',
					maxLength: 'Must not exceed a maximum of {0} characters!',
				},
				'customer-product': {
					required: 'Required.',
				},
				'customer-phone': {
					required: 'Please fill in the <b>Company Phone-number</b>, it is mandatory!',
					minLength: 'Please fill in at least {0} characters!',
					maxLength: 'Must not exceed a maximum of {0} characters!',
				},
				'customer-email': {
					required: 'Please fill in the <b>Company Email</b>, it is mandatory!',
					minLength: 'Please fill in at least {0} characters!',
					maxLength: 'Must not exceed a maximum of {0} characters!',
				},
				'customer-reference': {
					required: 'Please select the <b>Reference</b>, it is mandatory!',
				},
				'customer-agree-toc': {
					required: 'Please tick the <b>Terms and Condition</b>, it is mandatory!',
				},
			},
			submitHandler: (form, event) => {
				event.preventDefault();
				event.stopPropagation();
				const formData = new FormData(form);
				const formAPI = new FormData();
				let formRegister = {};
				const formButtonSubmit = event.submitter.innerHTML;

				formData.append('ajax', true);
				formData.append('action', 'register-account');
				formRegister = Object.fromEntries(formData.entries());
				formRegister['customer-product[]'] = formData.getAll('customer-product[]');

				formAPI.append('action', 'request');
				formAPI.append('method', 'short');
				formAPI.append('token', EVW.encrypt(process.env.APP.API_TOKEN, process.env.APP.KEY_ENCRYPT));
				formAPI.append('data', JSON.stringify({ phone_number: String(formRegister['customer-phone']).replace(/[^0-9+]/g, ''), message: '{template_registration}', customer: formRegister['customer-name'] }));

				sessionStore.set(`${process.env.APP.PREFIX}register_data`, JSON.stringify(formRegister), new Date().getTime() + ((1000 * 60) * 480)); // 480-min expires

				event.submitter.innerHTML = `<img src="${iconProcessing}" height="20px" class="svg-dark me-2 mr-2" style="opacity: 0.65;" />Please wait!`;
				event.submitter.setAttribute('disabled', 'disabled');
				event.submitter.classList.add('disabled');
				setTimeout(() => {
					axios.post(
						`${process.env.APP.API_URL}/auth/verify-otp.inc.php`,
						formAPI,
						{
							headers: { 'Content-Type': 'multipart/form-data' },
						},
					).then(({ data: res }) => {
						if (res.success) {
							sessionStore.set(`${process.env.APP.PREFIX}verify_otp`, res.data);
							if (document.body.contains(cardRegister)) {
								animateCSS(cardRegister, 'fadeOutDown').then(() => {
									cardRegister.classList.add('d-none');
									event.submitter.innerHTML = formButtonSubmit;
									event.submitter.removeAttribute('disabled');
									event.submitter.classList.remove('disabled');
									checkProgressStep(sessionStore.get(`${process.env.APP.PREFIX}register_data`), sessionStore.get(`${process.env.APP.PREFIX}verify_otp`));
								});
							}
						}
						event.submitter.innerHTML = formButtonSubmit;
						event.submitter.removeAttribute('disabled');
						event.submitter.classList.remove('disabled');
					}).catch((err) => console.error(err));
				}, 1000);
			},
		});
		const formInputs = validateRegister1.listInputs;

		// IMask and Refill form value
		const maskInputs = {};
		const dataFormRegister = (!isVarEmpty(dataRegister) && isString(dataRegister)) ? JSON.parse(dataRegister) || null : null;
		formInputs.forEach((eachInput) => {
			const inputTag = String(eachInput.tagName).toLowerCase() || null;
			const inputType = String(eachInput.type).toLowerCase() || null;
			const inputName = eachInput.getAttribute('name') || null;
			if (!isVarEmpty(inputTag)) {
				switch (inputTag) {
				case 'input':
					if (!isVarEmpty(inputName)) {
						// IMask
						if (inputName === 'customer-name') {
							maskInputs[inputName] = IMask(eachInput, {
								mask: /^[- .a-zA-Z0-9]+$/,
								prepare: (str) => str.toUpperCase(),
							});
						}
						if (inputName === 'customer-phone') {
							maskInputs[inputName] = IMask(eachInput, {
								mask: [
									{
										mask: '+00 {21} 0 000 0000',
										startsWith: '30',
										lazy: false,
										country: 'Greece',
									},
									{
										mask: '+0 000 000-00-00',
										startsWith: '7',
										lazy: false,
										country: 'Russia',
									},
									{
										mask: '+00-0000-000000',
										startsWith: '91',
										lazy: false,
										country: 'India',
									},
									{
										mask: '+62000-0000-0000[00]',
										startsWith: '62',
										lazy: false,
										country: 'Indonesia',
									},
									{
										mask: '0000000000000',
										startsWith: '',
										country: 'unknown',
									},
								],
								dispatch: (appended, dynamicMasked) => {
									const number = (dynamicMasked.value + appended).replace(/\D/g, '');
									return dynamicMasked.compiledMasks.find((m) => number.indexOf(m.startsWith) === 0);
								},
								// eslint-disable-next-line func-names, object-shorthand
								prepare: function (str, masked) {
									console.log(masked);
									// if (value.length >= 2) {
									// 	if (value.substring(0, 2) === '08') {
									// 		e.currentTarget.value = `628${value.substring(2)}`;
									// 		maskInputs[inputName].onChange()
									// 	}
									// }
									// if (str.length >= 2) {
									// 	if (str.substring(0, 2) === '08') {
									// 		masked._str = `628${str.substring(2)}`;
									// 	} else {
									// 		masked._str = str;
									// 	}
									// } else {
									// 	masked._str = str;
									// }
									return str;
								},
								// eslint-disable-next-line func-names, object-shorthand
								commit: function (value, masked) {
									// Don't change value manually! All changes should be done in mask!
									// This example helps to understand what is really changes, only for demo
									if (value.length >= 2) {
										if (value.substring(0, 2) === '08') {
											masked._value = `628${value.substring(2)}`;
										} else {
											masked._value = value;
										}
									} else {
										masked._value = value;
									}
									// masked._value = value.toLowerCase();  // Don't do it
								},
							});
							const checkTransformPhoneNumber = (e) => {
								const { value } = e.currentTarget;
								if (value.length >= 2) {
									if (value.substring(0, 2) === '08') {
										maskInputs[inputName].value = `628${value.substring(2)}`;
										// maskInputs[inputName].value = 
									}
								}
							};
							eventOff('keyup', eachInput, checkTransformPhoneNumber);
							eventOn('keyup', eachInput, checkTransformPhoneNumber);
						}
						if (inputName === 'customer-email') {
							maskInputs[inputName] = IMask(eachInput, {
								mask: (value) => {
									if (/^[a-z0-9_.-]+$/.test(value)) return true;
									if (/^[a-z0-9_.-]+@$/.test(value)) return true;
									if (/^[a-z0-9_.-]+@[a-z0-9-]+$/.test(value)) return true;
									if (/^[a-z0-9_.-]+@[a-z0-9-]+\.$/.test(value)) return true;
									if (/^[a-z0-9_.-]+@[a-z0-9-]+\.[a-z]{1,4}$/.test(value)) return true;
									if (/^[a-z0-9_.-]+@[a-z0-9-]+\.[a-z]{1,4}\.$/.test(value)) return true;
									if (/^[a-z0-9_.-]+@[a-z0-9-]+\.[a-z]{1,4}\.[a-z]{1,4}$/.test(value)) return true;
									return false;
								},
							});
						}

						// Refill value
						if (isObject(dataFormRegister) && !isVarEmpty(dataFormRegister)) {
							if (inputType === 'text') {
								eachInput.value = dataFormRegister[inputName];
							}
							if (inputType === 'checkbox') {
								const checkboxValues = dataFormRegister[inputName] || [];
								if (Array.isArray(checkboxValues) && checkboxValues.length >= 1) {
									checkboxValues.forEach((val) => {
										if (eachInput.value === val) {
											eachInput.checked = true;
										}
									});
								}
								if (isString(checkboxValues)) {
									if (eachInput.value === checkboxValues) {
										eachInput.checked = true;
									}
								}
							}
						}
					}
					break;
				case 'textarea':
					if (!isVarEmpty(inputName)) {
						// IMask
						if (inputName === 'customer-address') {
							maskInputs[inputName] = IMask(eachInput, {
								mask: /^[-/.,a-zA-Z0-9 ]+$/,
								prepare: (str) => str.toUpperCase(),
							});
						}

						// Refill value
						if (isObject(dataFormRegister) && !isVarEmpty(dataFormRegister)) {
							eachInput.value = dataFormRegister[inputName];
						}
					}
					break;
				case 'select':
					if (!isVarEmpty(inputName)) {
						// Refill value
						if (isObject(dataFormRegister) && !isVarEmpty(dataFormRegister)) {
							eachInput.value = dataFormRegister[inputName];
						}
					}
					break;
				default:
					break;
				}
			}
		});
		// Validate form if Refill value exist
		if (isObject(dataFormRegister) && !isVarEmpty(dataFormRegister)) {
			setTimeout(() => {
				validateRegister1.validate();
			}, 1000);
		}
	}
});
