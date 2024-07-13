/* eslint-disable no-underscore-dangle */
/* eslint-disable object-curly-newline */
/* eslint-disable no-lonely-if */
/* eslint-disable no-param-reassign */
/* eslint-disable max-len */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* global axios, lottie */
/* eslint no-undef: "error" */

/* == Module import == */
import 'Styles/forgot-account.scss';
import FormValidation from './classes/FormValidation';
import EncryptionVW from './classes/EncryptionVW';
import sessionStore from './utils/store.build';
import { isVarEmpty, isString, isInt, isObject } from './utils/func.validate';
import { animateCSS, eventOff, eventOn } from './utils/func.event';
import { addLeadingZeros } from './utils/func.general';
import { CountDownTimer } from './utils/func.process';
import iconLoading from '../../asset/image/icons/line-md_loading-twotone-loop.svg';
import iconConfirm from '../../asset/image/icons/line-md_confirm.svg';
import iconFailed from '../../asset/image/icons/line-md_close.svg';
import lottieResetPassword from '../../asset/json/lottie/112416-create-new-password.json';

/* == Main script == */
// DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
	const EVW = new EncryptionVW();

	const cardForgotAccount = document.getElementById('cardForgotAccount');
	const dataProgress = sessionStore.get(`${process.env.APP.PREFIX}forgot_account`) || null;

	const validateForm = {
		enterCredentials: null,
		confirmAccount: {
			sendCode: null,
			recoveryKeys: null,
		},
		createPassword: null,
	};
	const timerInstance = {
		resendCode: null,
		redirected: null,
	};

	if (document.body.contains(cardForgotAccount)) {
		const checkProgressStep = (progress, skipHideStep = false) => {
			let data = progress;
			if (!isVarEmpty(data) && isString(data)) {
				data = JSON.parse(data) || null;
			}

			// Serve data for API
			const formAPI = new FormData();
			formAPI.append('ajax', true);
			formAPI.append('action', 'forgot-account');
			formAPI.append('method', 'status-progress');
			axios.post(
				'app/includes/accounts.inc.php',
				formAPI,
				{
					headers: { 'Content-Type': 'multipart/form-data' },
				},
			).then(({ data: res }) => {
				if (res.success) {
					const _data = res.data;
					if (data !== null && isObject(data)) {
						if (Object.prototype.hasOwnProperty.call(data, 'step')) {
							if (!isVarEmpty(data.step)) {
								if (parseInt(data.step, 10) !== parseInt(_data.step, 10)) {
									data.step = _data.step;
								}
							}
						} else {
							data = { step: _data.step };
						}
						data.redirect = _data.redirect;
					} else {
						data = { step: _data.step, resend: 0, redirect: _data.redirect };
					}
				} else {
					data = { step: 1 };
				}
				sessionStore.set(`${process.env.APP.PREFIX}forgot_account`, JSON.stringify(data));

				// Hide all step Progress
				let waitUntil = 1200;
				const listStepProgress = Array.from(cardForgotAccount.querySelectorAll('.step-progress')) || [];
				if (skipHideStep === false) {
					if (listStepProgress.length >= 1) {
						listStepProgress.forEach((perStep) => {
							if (!perStep.classList.contains('d-none')) {
								cardForgotAccount.setAttribute('style', `min-height: ${perStep.offsetHeight}px;`);
								if (perStep.hasAttribute('style') && parseInt(window.getComputedStyle(perStep).getPropertyValue('opacity'), 10) === 0) {
									perStep.classList.add('d-none');
									perStep.removeAttribute('style');
									waitUntil = 10;
								} else {
									animateCSS(perStep, 'fadeOut').then(() => {
										perStep.classList.add('d-none');
									});
									waitUntil = 1200;
								}
							}
						});
					}
				}

				const doCancelState = (ev) => {
					sessionStore.remove(`${process.env.APP.PREFIX}forgot_account`);
					const formAPI2 = new FormData();
					formAPI2.append('ajax', true);
					formAPI2.append('action', 'forgot-account');
					formAPI2.append('method', 'reset-progress');
					axios.post(
						'app/includes/accounts.inc.php',
						formAPI2,
						{
							headers: { 'Content-Type': 'multipart/form-data' },
						},
					).then(({ data: res2 }) => {
						data = null;
						if (!isVarEmpty(ev)) {
							eventOff('click', ev.currentTarget, doCancelState);
						}
						checkProgressStep(data);
					}).catch((err) => console.error(err));
				};
				const doRedirectLogin = (ev) => {
					if (Object.prototype.hasOwnProperty.call(data, 'redirect')) {
						sessionStore.remove(`${process.env.APP.PREFIX}forgot_account`);
						const formAPI2 = new FormData();
						formAPI2.append('ajax', true);
						formAPI2.append('action', 'forgot-account');
						formAPI2.append('method', 'reset-progress');
						axios.post(
							'app/includes/accounts.inc.php',
							formAPI2,
							{
								headers: { 'Content-Type': 'multipart/form-data' },
							},
						).then(({ data: res2 }) => {
							animateCSS(cardForgotAccount, 'fadeOutDown').then(() => {
								cardForgotAccount.classList.add('d-none');
								window.location.href = data.redirect;
							});
						}).catch((err) => console.error(err));
					}
				};
				setTimeout(() => {
					document.dispatchEvent(new Event('AREDOMInserted'));
					// Progress section
					if (data !== null && isObject(data) && Object.prototype.hasOwnProperty.call(data, 'step')) {
						if (!isVarEmpty(data.step)) {
							switch (parseInt(data.step, 10)) {
							case 1: {
								const stepProgress = cardForgotAccount.querySelector('#enterYourCredentials');
								if (document.body.contains(stepProgress)) {
									stepProgress.setAttribute('style', 'opacity:0;');
									stepProgress.classList.remove('d-none');
									cardForgotAccount.removeAttribute('style');
									if (skipHideStep) {
										stepProgress.setAttribute('style', 'opacity:1;animation-duration:10ms !important;');
									}
									animateCSS(stepProgress, 'fadeIn').then(() => {
										stepProgress.removeAttribute('style');
										const formProgress = stepProgress.querySelector('#formCredentials');
										if (document.body.contains(formProgress)) {
											if (isVarEmpty(validateForm.enterCredentials)) {
												validateForm.enterCredentials = new FormValidation(formProgress, {
													strict: {
														message: true,
														input: true,
													},
													rules: {
														'account-id': {
															required: true,
															minLength: 5,
														},
													},
													messages: {
														'account-id': {
															required: {
																error: 'This input is Required!',
																success: '',
															},
															minLength: {
																error: 'Make sure the input is filled at least 5 characters',
																success: '',
															},
														},
													},
													submitHandler: (form, event) => {
														event.preventDefault();
														event.stopPropagation();
														const formAPI2 = new FormData();
														const inputElement = form.querySelector('input[name="account-id"]');
														const submitButton = form.querySelector('[type="submit"]');
														let submitContent = '';

														// Serve data for API
														formAPI2.append('ajax', true);
														formAPI2.append('action', 'forgot-account');
														formAPI2.append('method', 'enter-credentials');
														formAPI2.append('data', EVW.encrypt(JSON.stringify({ 'account-id': inputElement.value || null }), process.env.APP.KEY_ENCRYPT));

														if (document.body.contains(submitButton)) {
															submitContent = submitButton.innerHTML;
															submitButton.classList.add('disabled');
															submitButton.setAttribute('disabled', 'disabled');
															submitButton.innerHTML = `<img src="${iconLoading}" height="20px" class="svg-dark me-2 mr-2" style="opacity: 0.65;" />`;
														}

														setTimeout(() => {
															axios.post(
																'app/includes/accounts.inc.php',
																formAPI2,
																{
																	headers: { 'Content-Type': 'multipart/form-data' },
																},
															).then(({ data: res2 }) => {
																const alertMessage = form.querySelector('.alert');
																if (res2.success) {
																	if (document.body.contains(alertMessage)) {
																		alertMessage.classList.remove('show', 'alert-primary', 'alert-warning', 'alert-danger');
																		alertMessage.innerHTML = '';
																	}
																	if (document.body.contains(submitButton)) {
																		submitButton.innerHTML = `<img src="${iconConfirm}" height="20px" class="svg-dark me-2 mr-2" style="opacity: 0.65;" />`;
																	}
																	setTimeout(() => {
																		if (document.body.contains(submitButton)) {
																			submitButton.classList.remove('disabled');
																			submitButton.removeAttribute('disabled');
																			submitButton.innerHTML = submitContent;
																		}
																		checkProgressStep(data);
																	}, 1000 * 2); // Wait 3 seconds
																} else {
																	if (document.body.contains(alertMessage)) {
																		alertMessage.classList.remove('show', 'alert-primary', 'alert-warning', 'alert-danger');
																		alertMessage.innerHTML = res2.message;
																		alertMessage.classList.add((parseInt(res.errcode, 10) !== 9) ? 'alert-warning' : 'alert-danger', 'show');
																	}
																	if (document.body.contains(submitButton)) {
																		submitButton.innerHTML = `<img src="${iconFailed}" height="20px" class="svg-dark me-2 mr-2" style="opacity: 0.65;" />`;
																	}
																	setTimeout(() => {
																		if (document.body.contains(submitButton)) {
																			submitButton.classList.remove('disabled');
																			submitButton.removeAttribute('disabled');
																			submitButton.innerHTML = submitContent;
																		}
																	}, 1000 * 3); // Wait 3 seconds
																}
															}).catch((err) => console.error(err));
														}, 1000 * 3); // Wait 3 seconds
													},
												});
											}
										}
									});
								}
								break;
							}
							case 2: {
								const stepProgress = cardForgotAccount.querySelector('#confirmItsYou');
								if (document.body.contains(stepProgress)) {
									stepProgress.setAttribute('style', 'opacity:0;');
									stepProgress.classList.remove('d-none');
									cardForgotAccount.removeAttribute('style');
									if (skipHideStep) {
										stepProgress.setAttribute('style', 'opacity:1;animation-duration:10ms !important;');
									}
									animateCSS(stepProgress, 'fadeIn').then(() => {
										stepProgress.removeAttribute('style');

										// Form action
										const formMethod = {
											'send-code': stepProgress.querySelector('#formSendCode'),
											'recovery-keys': stepProgress.querySelector('#formRecoveryKeys'),
										};
										if (document.body.contains(formMethod['send-code'])) {
											// Resend code
											const buttonResendCode = formMethod['send-code'].querySelector('.btn-send-code');
											const buttonResendCodeContent = buttonResendCode.innerHTML;
											const resendCode = () => {
												if (!Object.prototype.hasOwnProperty.call(data, 'resend') || data.resend === 0) {
													const formAPI2 = new FormData();
													formAPI2.append('ajax', true);
													formAPI2.append('action', 'forgot-account');
													formAPI2.append('method', 'confirm-account');
													formAPI2.append('data', EVW.encrypt(JSON.stringify({ 'request-method': 'send-code' }), process.env.APP.KEY_ENCRYPT));

													buttonResendCode.innerHTML = `${buttonResendCodeContent} (Please wait)`;
													buttonResendCode.classList.remove('text-primary', 'text-secondary', 'text-orange', 'text-blue', 'text-info', 'text-danger');
													buttonResendCode.classList.add('disabled', 'text-gray');
													buttonResendCode.setAttribute('disabled', 'disabled');

													axios.post(
														'app/includes/accounts.inc.php',
														formAPI2,
														{
															headers: { 'Content-Type': 'multipart/form-data' },
														},
													).then(({ data: res2 }) => {
														if (res2.success) {
															buttonResendCode.innerHTML = buttonResendCodeContent;
															data.resend = 120;
															sessionStore.set(`${process.env.APP.PREFIX}forgot_account`, JSON.stringify(data));
															// eventOff('click', buttonResendCode, resendCode);
															checkProgressStep(data, true);
														}
													}).catch((err) => console.error(err));
												}
											};
											eventOff('click', buttonResendCode, resendCode);
											eventOn('click', buttonResendCode, resendCode);
											resendCode(); // First init

											timerInstance.resendCode = new CountDownTimer(data.resend);
											timerInstance.resendCode.onTick((minutes, seconds, raw) => {
												data.resend = raw;
												sessionStore.set(`${process.env.APP.PREFIX}forgot_account`, JSON.stringify(data));
												buttonResendCode.innerHTML = `${buttonResendCodeContent} <b>(${addLeadingZeros(minutes, 2)}:${addLeadingZeros(seconds, 2)})</b>`;
												if (raw >= 1) {
													buttonResendCode.classList.remove('text-primary', 'text-secondary', 'text-orange', 'text-blue', 'text-info', 'text-danger', 'text-gray');
													buttonResendCode.classList.add('disabled', 'text-danger');
													buttonResendCode.setAttribute('disabled', 'disabled');
												} else {
													buttonResendCode.classList.remove('text-primary', 'text-secondary', 'text-orange', 'text-blue', 'text-info', 'text-danger', 'text-gray');
													buttonResendCode.classList.remove('disabled');
													buttonResendCode.classList.add('text-primary');
													buttonResendCode.removeAttribute('disabled');
													buttonResendCode.innerHTML = buttonResendCodeContent;
												}
											});
											timerInstance.resendCode.start();

											// Form behaviour
											if (isVarEmpty(validateForm.confirmAccount.sendCode)) {
												validateForm.confirmAccount.sendCode = new FormValidation(formMethod['send-code'], {
													strict: {
														message: true,
														input: true,
													},
													rules: {
														'verification-code': {
															required: true,
															minLength: 10,
														},
													},
													messages: {
														'verification-code': {
															required: {
																error: 'This input is Required!',
																success: '',
															},
															minLength: {
																error: 'Make sure the input is filled at least 10 characters',
																success: '',
															},
														},
													},
													submitHandler: (form, event) => {
														event.preventDefault();
														event.stopPropagation();
														const formAPI2 = new FormData();
														const inputElement = form.querySelector('input[name="verification-code"]');
														const submitButton = form.querySelector('[type="submit"]');
														let submitContent = '';

														// Serve data for API
														formAPI2.append('ajax', true);
														formAPI2.append('action', 'forgot-account');
														formAPI2.append('method', 'confirm-account');
														formAPI2.append('data', EVW.encrypt(JSON.stringify({ 'confirm-method': 'send-code', 'verification-code': inputElement.value || null }), process.env.APP.KEY_ENCRYPT));

														if (document.body.contains(submitButton)) {
															submitContent = submitButton.innerHTML;
															submitButton.classList.add('disabled');
															submitButton.setAttribute('disabled', 'disabled');
															submitButton.innerHTML = `<img src="${iconLoading}" height="20px" class="svg-dark me-2 mr-2" style="opacity: 0.65;" />`;
														}

														axios.post(
															'app/includes/accounts.inc.php',
															formAPI2,
															{
																headers: { 'Content-Type': 'multipart/form-data' },
															},
														).then(({ data: res2 }) => {
															const alertMessage = form.querySelector('.alert');
															if (res2.success) {
																if (document.body.contains(alertMessage)) {
																	alertMessage.classList.remove('show', 'alert-primary', 'alert-warning', 'alert-danger');
																	alertMessage.innerHTML = '';
																}
																if (document.body.contains(submitButton)) {
																	submitButton.innerHTML = `<img src="${iconConfirm}" height="20px" class="svg-dark me-2 mr-2" style="opacity: 0.65;" />`;
																}
																setTimeout(() => {
																	if (document.body.contains(submitButton)) {
																		submitButton.classList.remove('disabled');
																		submitButton.removeAttribute('disabled');
																		submitButton.innerHTML = submitContent;
																	}
																	checkProgressStep(data);
																}, 1000 * 2); // Wait 3 seconds
															} else {
																if (document.body.contains(alertMessage)) {
																	alertMessage.classList.remove('show', 'alert-primary', 'alert-warning', 'alert-danger');
																	alertMessage.innerHTML = res2.message;
																	alertMessage.classList.add((parseInt(res2.errcode, 10) !== 9) ? 'alert-warning' : 'alert-danger', 'show');
																}
																if (document.body.contains(submitButton)) {
																	submitButton.innerHTML = `<img src="${iconFailed}" height="20px" class="svg-dark me-2 mr-2" style="opacity: 0.65;" />`;
																}
																setTimeout(() => {
																	if (document.body.contains(submitButton)) {
																		submitButton.classList.remove('disabled');
																		submitButton.removeAttribute('disabled');
																		submitButton.innerHTML = submitContent;
																	}
																}, 1000 * 3); // Wait 3 seconds
															}
														}).catch((err) => console.error(err));
													},
												});
											}
										}
										if (document.body.contains(formMethod['recovery-keys'])) {
											// Form behaviour
											if (isVarEmpty(validateForm.confirmAccount.recoveryKeys)) {
												validateForm.confirmAccount.recoveryKeys = new FormValidation(formMethod['recovery-keys'], {
													strict: {
														message: true,
														input: true,
													},
													rules: {
														'recovery-keys': {
															required: true,
															minLength: 10,
														},
													},
													messages: {
														'recovery-keys': {
															required: {
																error: 'This input is Required!',
																success: '',
															},
															minLength: {
																error: 'Make sure the input is filled at least 10 characters',
																success: '',
															},
														},
													},
													submitHandler: (form, event) => {
														event.preventDefault();
														event.stopPropagation();
														const formAPI2 = new FormData();
														const inputElement = form.querySelector('input[name="recovery-keys"]');
														const submitButton = form.querySelector('[type="submit"]');
														let submitContent = '';

														// Serve data for API
														formAPI2.append('ajax', true);
														formAPI2.append('action', 'forgot-account');
														formAPI2.append('method', 'confirm-account');
														formAPI2.append('data', EVW.encrypt(JSON.stringify({ 'confirm-method': 'recovery-keys', 'recovery-keys': inputElement.value || null }), process.env.APP.KEY_ENCRYPT));

														if (document.body.contains(submitButton)) {
															submitContent = submitButton.innerHTML;
															submitButton.classList.add('disabled');
															submitButton.setAttribute('disabled', 'disabled');
															submitButton.innerHTML = `<img src="${iconLoading}" height="20px" class="svg-dark me-2 mr-2" style="opacity: 0.65;" />`;
														}

														axios.post(
															'app/includes/accounts.inc.php',
															formAPI2,
															{
																headers: { 'Content-Type': 'multipart/form-data' },
															},
														).then(({ data: res2 }) => {
															const alertMessage = form.querySelector('.alert');
															if (res2.success) {
																if (document.body.contains(alertMessage)) {
																	alertMessage.classList.remove('show', 'alert-primary', 'alert-warning', 'alert-danger');
																	alertMessage.innerHTML = '';
																}
																if (document.body.contains(submitButton)) {
																	submitButton.innerHTML = `<img src="${iconConfirm}" height="20px" class="svg-dark me-2 mr-2" style="opacity: 0.65;" />`;
																}
																setTimeout(() => {
																	if (document.body.contains(submitButton)) {
																		submitButton.classList.remove('disabled');
																		submitButton.removeAttribute('disabled');
																		submitButton.innerHTML = submitContent;
																	}
																	checkProgressStep(data);
																}, 1000 * 2); // Wait 3 seconds
															} else {
																if (document.body.contains(alertMessage)) {
																	alertMessage.classList.remove('show', 'alert-primary', 'alert-warning', 'alert-danger');
																	alertMessage.innerHTML = res2.message;
																	alertMessage.classList.add((parseInt(res2.errcode, 10) !== 9) ? 'alert-warning' : 'alert-danger', 'show');
																}
																if (document.body.contains(submitButton)) {
																	submitButton.innerHTML = `<img src="${iconFailed}" height="20px" class="svg-dark me-2 mr-2" style="opacity: 0.65;" />`;
																}
																setTimeout(() => {
																	if (document.body.contains(submitButton)) {
																		submitButton.classList.remove('disabled');
																		submitButton.removeAttribute('disabled');
																		submitButton.innerHTML = submitContent;
																	}
																}, 1000 * 3); // Wait 3 seconds
															}
														}).catch((err) => console.error(err));
													},
												});
											}
										}

										// Switch method
										const switchToggle = stepProgress.querySelector('.switch-toggle');
										const switchOption = stepProgress.querySelector('.switch-option');
										const toggleList = Array.from(switchToggle.querySelectorAll('.switcher-input')) || [];
										const methodList = Array.from(stepProgress.querySelectorAll('.switcher-form')) || [];
										if (toggleList.length >= 1 && methodList.length >= 1) {
											toggleList.forEach((perToggle) => {
												const doChange = (ev) => {
													const selectedValue = ev.currentTarget.value || null;
													const availableForm = Object.keys(formMethod) || [];
													if (!isVarEmpty(selectedValue) && availableForm.length >= 1) {
														if (availableForm.includes(selectedValue)) {
															methodList.forEach((perMethod) => {
																if (!perMethod.classList.contains('d-none')) {
																	switchOption.setAttribute('style', `min-height: ${perMethod.offsetHeight}px`);
																	animateCSS(perMethod, 'fadeOut').then(() => {
																		perMethod.classList.add('d-none');
																	});
																}
															});
															setTimeout(() => {
																const methodSelected = formMethod[selectedValue];
																methodSelected.setAttribute('style', 'opacity:0;');
																methodSelected.classList.remove('d-none');
																switchOption.setAttribute('style', `min-height: ${methodSelected.offsetHeight}px;`);
																animateCSS(formMethod[selectedValue], 'fadeIn').then(() => {
																	methodSelected.removeAttribute('style');
																});
															}, 1200);
														}
													}
												};
												eventOff('change', perToggle, doChange);
												eventOn('change', perToggle, doChange);
											});
										}

										// Button cancel
										const buttonCancel = stepProgress.querySelector('.btn-cancel');
										if (document.body.contains(buttonCancel)) {
											eventOff('click', buttonCancel, doCancelState);
											eventOn('click', buttonCancel, doCancelState);
										}
									});
								}
								break;
							}
							case 3: {
								const stepProgress = cardForgotAccount.querySelector('#createNewPassword');
								if (document.body.contains(stepProgress)) {
									stepProgress.setAttribute('style', 'opacity:0;');
									stepProgress.classList.remove('d-none');
									cardForgotAccount.removeAttribute('style');
									if (skipHideStep) {
										stepProgress.setAttribute('style', 'opacity:1;animation-duration:10ms !important;');
									}
									animateCSS(stepProgress, 'fadeIn').then(() => {
										stepProgress.removeAttribute('style');

										const formProgress = stepProgress.querySelector('#formCreateNewPassword');
										if (document.body.contains(formProgress)) {
											if (isVarEmpty(validateForm.createPassword)) {
												validateForm.createPassword = new FormValidation(formProgress, {
													strict: {
														message: true,
														input: true,
													},
													rules: {
														'new-password': {
															required: true,
															minLength: 6,
															maxLength: 20,
															strongPassword: true,
														},
														'new-password-confirm': {
															required: true,
															minLength: 6,
															maxLength: 20,
														},
													},
													messages: {
														'new-password': {
															required: {
																error: 'This input is Required!',
																success: '',
															},
															minLength: {
																error: 'Make sure the input is filled at least 6 characters',
																success: '',
															},
															maxLength: {
																error: 'The maximum number of input is 20 characters long',
																success: '',
															},
														},
														'new-password-confirm': {
															required: {
																error: 'This input is Required!',
																success: '',
															},
															minLength: {
																error: 'Make sure the input is filled at least 6 characters',
																success: '',
															},
															maxLength: {
																error: 'The maximum number of input is 20 characters long',
																success: '',
															},
														},
													},
													submitHandler: (form, event) => {
														event.preventDefault();
														event.stopPropagation();
														const formAPI2 = new FormData();
														const inputElement = {
															'new-password': form.querySelector('input[name="new-password"]'),
															'new-password-confirm': form.querySelector('input[name="new-password-confirm"]'),
														};
														const alertMessage = form.querySelector('.alert');
														const submitButton = form.querySelector('[type="submit"]');
														let submitContent = '';

														if (inputElement['new-password'].value === inputElement['new-password-confirm'].value) {
															// Serve data for API
															formAPI2.append('ajax', true);
															formAPI2.append('action', 'forgot-account');
															formAPI2.append('method', 'reset-password');
															formAPI2.append('data', EVW.encrypt(JSON.stringify({ 'new-password': inputElement['new-password'].value || null }), process.env.APP.KEY_ENCRYPT));

															if (document.body.contains(submitButton)) {
																submitContent = submitButton.innerHTML;
																submitButton.classList.add('disabled');
																submitButton.setAttribute('disabled', 'disabled');
																submitButton.innerHTML = `<img src="${iconLoading}" height="20px" class="svg-dark me-2 mr-2" style="opacity: 0.65;" />`;
															}

															axios.post(
																'app/includes/accounts.inc.php',
																formAPI2,
																{
																	headers: { 'Content-Type': 'multipart/form-data' },
																},
															).then(({ data: res2 }) => {
																if (res2.success) {
																	if (document.body.contains(alertMessage)) {
																		alertMessage.classList.remove('show', 'alert-primary', 'alert-warning', 'alert-danger');
																		alertMessage.innerHTML = '';
																	}
																	if (document.body.contains(submitButton)) {
																		submitButton.innerHTML = `<img src="${iconConfirm}" height="20px" class="svg-dark me-2 mr-2" style="opacity: 0.65;" />`;
																	}
																	setTimeout(() => {
																		if (document.body.contains(submitButton)) {
																			submitButton.classList.remove('disabled');
																			submitButton.removeAttribute('disabled');
																			submitButton.innerHTML = submitContent;
																		}
																		checkProgressStep(data);
																	}, 1000 * 2); // Wait 3 seconds
																} else {
																	if (document.body.contains(alertMessage)) {
																		alertMessage.classList.remove('show', 'alert-primary', 'alert-warning', 'alert-danger');
																		alertMessage.innerHTML = res2.message;
																		alertMessage.classList.add((parseInt(res2.errcode, 10) !== 9) ? 'alert-warning' : 'alert-danger', 'show');
																	}
																	if (document.body.contains(submitButton)) {
																		submitButton.innerHTML = `<img src="${iconFailed}" height="20px" class="svg-dark me-2 mr-2" style="opacity: 0.65;" />`;
																	}
																	setTimeout(() => {
																		if (document.body.contains(submitButton)) {
																			if (parseInt(res2.errcode, 10) !== 9) {
																				submitButton.classList.remove('disabled');
																				submitButton.removeAttribute('disabled');
																				submitButton.innerHTML = submitContent;
																			} else {
																				timerInstance.redirected = new CountDownTimer(5);
																				timerInstance.redirected.onTick((minutes, seconds, raw) => {
																					submitButton.innerHTML = `<b>Redirected in (${addLeadingZeros(seconds, 2)})</b>`;
																					if (raw === 0) {
																						submitButton.classList.remove('disabled');
																						submitButton.removeAttribute('disabled');
																						submitButton.innerHTML = submitContent;
																						timerInstance.redirected = null;
																						doCancelState();
																					}
																				});
																				timerInstance.redirected.start();
																			}
																		}
																	}, 1000 * 3); // Wait 3 seconds
																}
															}).catch((err) => console.error(err));
														} else {
															if (document.body.contains(alertMessage)) {
																alertMessage.classList.remove('show', 'alert-primary', 'alert-warning', 'alert-danger');
																alertMessage.innerHTML = 'The passwords you entered do not match. <br> Please re-enter again.';
																alertMessage.classList.add('alert-warning', 'show');
																inputElement['new-password-confirm'].value = '';
																inputElement['new-password-confirm'].focus();
															}
														}
													},
												});
											}
										}

										const buttonCancel = stepProgress.querySelector('.btn-cancel');
										if (document.body.contains(buttonCancel)) {
											eventOff('click', buttonCancel, doCancelState);
											eventOn('click', buttonCancel, doCancelState);
										}
									});
								}
								break;
							}
							case 4: {
								const stepProgress = cardForgotAccount.querySelector('#completeMessage');
								if (document.body.contains(stepProgress)) {
									stepProgress.setAttribute('style', 'opacity:0;');
									stepProgress.classList.remove('d-none');
									cardForgotAccount.removeAttribute('style');
									if (skipHideStep) {
										stepProgress.setAttribute('style', 'opacity:1;animation-duration:10ms !important;');
									}
									lottie.loadAnimation({
										container: stepProgress.querySelector('.lottie-animation'), // the dom element that will contain the animation
										renderer: 'svg',
										loop: false,
										autoplay: true,
										animationData: lottieResetPassword, // the data to the animation json
									});
									animateCSS(stepProgress, 'fadeIn').then(() => {
										stepProgress.removeAttribute('style');

										const buttonComplete = stepProgress.querySelector('.btn-complete');
										if (document.body.contains(buttonComplete)) {
											eventOff('click', buttonComplete, doRedirectLogin);
											eventOn('click', buttonComplete, doRedirectLogin);
										}
									});
								}
								break;
							}
							default:
								break;
							}
						}
					} else {
						sessionStore.set(`${process.env.APP.PREFIX}forgot_account`, JSON.stringify({ step: 1 }));
						const stepProgress = cardForgotAccount.querySelector('#enterYourCredentials');
						if (document.body.contains(stepProgress)) {
							stepProgress.setAttribute('style', 'opacity:0;');
							stepProgress.classList.remove('d-none');
							animateCSS(stepProgress, 'fadeIn').then(() => {
								stepProgress.removeAttribute('style');
							});
						}
					}
				}, waitUntil);
			});
		};
		setTimeout(() => {
			checkProgressStep(dataProgress);
		}, 1000);

		if (cardForgotAccount.classList.contains('d-none')) {
			cardForgotAccount.setAttribute('style', 'opacity: 0;');
			cardForgotAccount.classList.remove('d-none');
			animateCSS(cardForgotAccount, 'fadeInUp').then(() => {
				cardForgotAccount.removeAttribute('style');
			});
		}
	}
});
