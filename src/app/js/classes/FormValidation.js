/* eslint-disable import/no-named-default */
/* eslint-disable camelcase */
/* eslint-disable func-names */
/* eslint-disable guard-for-in */
/* eslint-disable no-restricted-syntax */
/* eslint-disable object-curly-newline */
/* eslint-disable no-lonely-if */
/* eslint-disable max-len */
/* eslint-disable lines-between-class-members */
/* eslint-disable default-case */
/* eslint-disable import/prefer-default-export */
/* eslint-disable no-param-reassign */
/* eslint-disable no-loop-func */
/* eslint-disable no-new */
/* eslint indent: ["error", "tab", { "SwitchCase": 1 }] */
/* eslint linebreak-style: ["error", "windows"] */
/* eslint no-undef: "error" */
/* eslint no-extend-native: ["error", { "exceptions": ["String"] }] */
/* global moment, bootstrap, validator, Scrollbar */

// import { default as isInteger } from 'validator/es/lib/isInt';
// import isMobilePhone from 'validator/es/lib/isMobilePhone';
// import isEmail from 'validator/es/lib/isEmail';

// import validator from 'validator';
import zxcvbn from 'zxcvbn';
import VanillaCalendar from '@uvarov.frontend/vanilla-calendar';
// import Scrollbar from 'smooth-scrollbar';
import { isVarEmpty, isString, isObject, isBoolean, isElement, isFunction, mergeObjectRecursive, isInt } from '../utils/func.validate';
import { findAncestor, removeNodes, insertAfter, recreateNode } from '../utils/func.html';
import { ConvertStringToHTML, parseNumber, stringToCamelize } from '../utils/func.convert';
import { eventOff, eventOn, inputDoneTyping } from '../utils/func.event';
import { addLeadingZeros } from '../utils/func.general';

// const validator = { isInteger, isMobilePhone, isEmail };

// New String method 'formatUnicorn'
String.prototype.formatUnicorn = String.prototype.formatUnicorn || function () {
	let str = this.toString();
	if (arguments.length) {
		const t = typeof arguments[0];
		let key;
		const args = (t === 'string' || t === 'number') ? Array.prototype.slice.call(arguments) : arguments[0];
		for (key in args) {
			str = str.replace(new RegExp(`\\{${key}\\}`, 'gi'), args[key]);
		}
	}
	return str;
};

export default class FormValidation {
	#default = {
		config: {
			debug: false,
			ignore: ['.ignore'],
			strict: {
				message: false,
				input: false,
			},
			rules: {},
			messages: {},
			// inputIndicator: false,
			onInputError: (input, listMessage, listData) => {
				let anchorElement = input.parentNode;
				let html2Node = null;
				const tagName = String(input.tagName).toLowerCase();
				const tagType = String(input.type).toLowerCase();
				const isCol = (String(input.parentNode.classList.value).indexOf('col') > -1);
				const isFormGroup = (input.parentNode.parentNode.classList.contains('form-group'));
				const isInputGroup = (input.parentNode.classList.contains('input-group'));
				const isFormFloating = (input.parentNode.classList.contains('form-floating'));
				const inputType = input.type || null;

				input.classList.remove('is-valid', 'is-invalid');
				input.classList.add('is-invalid');
				if (isCol) {
					anchorElement = input.parentNode;
				}
				if (isFormGroup) {
					switch (tagType) {
						case 'checkbox':
							anchorElement = input.parentNode;
							break;
						default:
							anchorElement = input.parentNode.parentNode;
							break;
					}
					// console.log();
				}
				if (isInputGroup) {
					anchorElement = input.parentNode;
					if (document.body.contains(anchorElement.querySelector('.input-group-text'))) {
						anchorElement.querySelector('.input-group-text').classList.remove('is-valid', 'is-invalid');
						anchorElement.querySelector('.input-group-text').classList.add('is-invalid');
					}
				}
				if (isFormFloating) {
					switch (tagName) {
						case 'textarea':
							anchorElement = input.parentNode;
							break;
						default:
							anchorElement = input.parentNode.parentNode;
							break;
					}
					if (document.body.contains(anchorElement.querySelector('.input-group-text'))) {
						anchorElement.querySelector('.input-group-text').classList.remove('is-valid', 'is-invalid');
						anchorElement.querySelector('.input-group-text').classList.add('is-invalid');
					}
				}

				// for Specific input types
				if (isObject(listData)) {
					// rule: strongPassword
					// element: input[type="password"]
					if (Object.hasOwnProperty.call(listData, 'strongPassword')) {
						if (listData.strongPassword && !isVarEmpty(inputType) && inputType.toLowerCase() === 'password') {
							const { /* guesses, crack_times_display, */ score, feedback } = listData.strongPassword;
							const bgClassLevel = {
								0: 'bg-danger',
								1: 'bg-danger',
								2: 'bg-danger',
								3: 'bg-warning',
								4: 'bg-success',
							};
							const progressByPercent = ((score / 4) * 100);
							const progressElement = document.getElementById(stringToCamelize(`progress ${input.name}`));

							listMessage.strongPassword = feedback.warning;
							if (!document.body.contains(progressElement)) {
								html2Node = ConvertStringToHTML(`
									<div id="${stringToCamelize(`progress ${input.name}`)}" class="progress mt-2" style="width:100%;height:5px;border-radius:2.5px;overflow:hidden;">
										<div class="progress-bar ${bgClassLevel[score]}" role="progressbar" style="width: ${progressByPercent}%;" aria-valuenow="${progressByPercent}" aria-valuemin="0" aria-valuemax="100"></div>
									</div>
								`);
								while (html2Node.firstChild) { anchorElement.appendChild(html2Node.firstChild); }
							} else {
								progressElement.querySelector('.progress-bar').classList.remove('bg-danger', 'bg-warning', 'bg-success');
								progressElement.querySelector('.progress-bar').classList.add(bgClassLevel[score]);
								progressElement.querySelector('.progress-bar').style.width = `${progressByPercent}%`;
								progressElement.querySelector('.progress-bar').setAttribute('aria-valuenow', progressByPercent);
							}
						}
					}
				}

				if (!isVarEmpty(anchorElement)) {
					// Reset/clear state of HTML (if exist)
					if (anchorElement.contains(anchorElement.querySelector('.invalid-feedback'))) {
						removeNodes(anchorElement.querySelector('.invalid-feedback'));
					}
					if (anchorElement.contains(anchorElement.querySelector('.valid-feedback'))) {
						removeNodes(anchorElement.querySelector('.valid-feedback'));
					}
					// Re-create message
					html2Node = ConvertStringToHTML(`
						<div class="invalid-feedback">
	${((list) => {
		let output = '<ul class="px-4 py-0 m-0">';
		let loopIdx = 1;
		const { message: strictMessage } = this.config.strict;
		let strictMessageCount = Object.keys(list).length || 0;
		Object.entries(list).forEach(([key, val]) => {
			if (isBoolean(strictMessage)) {
				if (strictMessage) {
					if (loopIdx === 1) {
						output += `<li>${val}</li>`;
					}
				} else {
					output += `<li>${val}</li>`;
				}
			} else if (isInt(strictMessage)) {
				if (parseInt(strictMessage, 10) >= 1 && loopIdx <= parseInt(strictMessage, 10)) {
					output += `<li>${val}</li>`;
				} else {
					output += `<li>${val}</li>`;
				}
			} else {
				output += `<li>${val}</li>`;
			}
			loopIdx += 1;
		});
		if (isBoolean(strictMessage)) {
			strictMessageCount -= 1;
			if (strictMessage && strictMessageCount >= 1) {
				output += `<li>and (${strictMessageCount}) errors more!</li>`;
			}
		}
		if (isInt(strictMessage)) {
			strictMessageCount -= parseInt(strictMessage, 10);
			if (parseInt(strictMessage, 10) >= 2 && strictMessageCount >= 1) {
				output += `<li>and (${strictMessageCount}) errors more!</li>`;
			}
		}

		output += '</ul>';
		return output;
	})(listMessage)}
						</div>
					`);
					while (html2Node.firstChild) { anchorElement.appendChild(html2Node.firstChild); }
				}
			},
			onInputSuccess: (input, listMessage, listData) => {
				let anchorElement = input.parentNode;
				let html2Node = null;
				const tagName = String(input.tagName).toLowerCase();
				const tagType = String(input.type).toLowerCase();
				const isCol = (String(input.parentNode.classList.value).indexOf('col') > -1);
				const isFormGroup = (input.parentNode.parentNode.classList.contains('form-group'));
				const isInputGroup = (input.parentNode.classList.contains('input-group'));
				const isFormFloating = (input.parentNode.classList.contains('form-floating'));
				const inputType = input.type || null;

				input.classList.remove('is-valid', 'is-invalid');
				input.classList.add('is-valid');
				if (isCol) {
					anchorElement = input.parentNode;
				}
				if (isFormGroup) {
					switch (tagType) {
						case 'checkbox':
							anchorElement = input.parentNode;
							break;
						default:
							anchorElement = input.parentNode.parentNode;
							break;
					}
				}
				if (isInputGroup) {
					anchorElement = input.parentNode;
					if (document.body.contains(anchorElement.querySelector('.input-group-text'))) {
						anchorElement.querySelector('.input-group-text').classList.remove('is-valid', 'is-invalid');
						anchorElement.querySelector('.input-group-text').classList.add('is-valid');
					}
				}
				if (isFormFloating) {
					switch (tagName) {
						case 'textarea':
							anchorElement = input.parentNode;
							break;
						default:
							anchorElement = input.parentNode.parentNode;
							break;
					}
					if (document.body.contains(anchorElement.querySelector('.input-group-text'))) {
						anchorElement.querySelector('.input-group-text').classList.remove('is-valid', 'is-invalid');
						anchorElement.querySelector('.input-group-text').classList.add('is-valid');
					}
				}

				// for Specific input types
				if (isObject(listData)) {
					// rule: strongPassword
					// element: input[type="password"]
					if (Object.hasOwnProperty.call(listData, 'strongPassword')) {
						if (listData.strongPassword && !isVarEmpty(inputType) && inputType.toLowerCase() === 'password') {
							const { /* guesses, crack_times_display, */ score /* , feedback */ } = listData.strongPassword;
							const bgClassLevel = {
								0: 'bg-danger',
								1: 'bg-danger',
								2: 'bg-danger',
								3: 'bg-warning',
								4: 'bg-success',
							};
							const progressByPercent = ((score / 4) * 100);
							const progressElement = document.getElementById(stringToCamelize(`progress ${input.name}`));
							if (!document.body.contains(progressElement)) {
								html2Node = ConvertStringToHTML(`
									<div id="${stringToCamelize(`progress ${input.name}`)}" class="progress mt-2" style="width:100%;height:5px;border-radius:2.5px;overflow:hidden;">
										<div class="progress-bar ${bgClassLevel[score]}" role="progressbar" style="width: ${progressByPercent}%;" aria-valuenow="${progressByPercent}" aria-valuemin="0" aria-valuemax="100"></div>
									</div>
								`);
								while (html2Node.firstChild) { anchorElement.appendChild(html2Node.firstChild); }
							} else {
								progressElement.querySelector('.progress-bar').classList.remove('bg-danger', 'bg-warning', 'bg-success');
								progressElement.querySelector('.progress-bar').classList.add(bgClassLevel[score]);
								progressElement.querySelector('.progress-bar').style.width = `${progressByPercent}%`;
								progressElement.querySelector('.progress-bar').setAttribute('aria-valuenow', progressByPercent);
							}
						}
					}
				}

				// Reset/clear state of HTML (if exist)
				if (!isVarEmpty(anchorElement)) {
					if (anchorElement.contains(anchorElement.querySelector('.invalid-feedback'))) {
						removeNodes(anchorElement.querySelector('.invalid-feedback'));
					}
					if (anchorElement.contains(anchorElement.querySelector('.valid-feedback'))) {
						removeNodes(anchorElement.querySelector('.valid-feedback'));
					}
					// Re-create message
					html2Node = ConvertStringToHTML(`<div class="valid-feedback">Looks good!</div>`);
					while (html2Node.firstChild) { anchorElement.appendChild(html2Node.firstChild); }
				}
			},
			submitHandler: null,
		},
		validator: {
			required: (val, el) => {
				let result = false;
				const tagName = el.tagName.toLowerCase();
				let tagType = null;
				switch (tagName) {
					case 'input':
						tagType = el.type.toLowerCase();
						if (tagType.match(/(text|password|number|week|time|date|datetime|datetime-local|search|tel|url)/g)) {
							result = (!isVarEmpty(val));
						}
						if (tagType.match(/(checkbox|radio)/g)) {
							const inputName = el.getAttribute('name') || null;
							if (!isVarEmpty(inputName)) {
								const inputList = this.findInputByName(inputName) || [];
								if (inputList.length === 1) {
									result = el.checked;
								}
								if (inputList.length >= 2) {
									let isInputChecked = false;
									inputList.forEach((perInput) => {
										if (perInput.checked) {
											isInputChecked = true;
										}
									});
									result = isInputChecked;
								}
							}
							// if (tagType === 'checkbox') {
							// } else {
							// }
						}
						break;
					case 'textarea':
						result = (!isVarEmpty(val));
						// console.log(result);
						break;
					case 'select': {
						let selectedValue = el.value;
						if (el.options[el.selectedIndex].text === selectedValue) {
							selectedValue = null;
						} else {
							const listOption = Array.from(el.options) || [];
							if (listOption.length >= 1) {
								const indexOption = el.selectedIndex;
								listOption.forEach((perOption) => perOption.removeAttribute('selected'));
								if (indexOption > -1) {
									el.value = listOption[indexOption].value;
									el.options[indexOption].selected = true;
									listOption[indexOption].setAttribute('selected', 'selected');
								}
							}
						}
						result = (!isVarEmpty(selectedValue));
						// console.log(selectedValue);
						// console.log(result);
						// console.log(el.value);
						break;
					}
					default:
						result = false;
						break;
				}
				return result;
			},
			minLength: (val, el) => {
				let result = false;
				const tagName = el.tagName.toLowerCase();
				let tagType = null;
				switch (tagName) {
					case 'input':
						tagType = el.type.toLowerCase();
						if (tagType.match(/(text|password|number|week|time|date|datetime|datetime-local|search|tel|url)/g)) {
							result = (!isVarEmpty(val) && String(el.value).length >= val);
						}
						break;
					case 'textarea':
						result = (!isVarEmpty(val) && String(el.value).length >= val);
						break;
					default:
						result = false;
						break;
				}
				return result;
			},
			minValue: (val, el) => {
				let result = false;
				const tagName = el.tagName.toLowerCase();
				let tagType = null;
				switch (tagName) {
					case 'input':
						tagType = el.type.toLowerCase();
						if (tagType.match(/(text|number)/g)) {
							result = (!isVarEmpty(val) && parseNumber(el.value, 'id-ID') >= val);
						}
						break;
					default:
						result = false;
						break;
				}
				return result;
			},
			maxLength: (val, el) => {
				let result = false;
				const tagName = el.tagName.toLowerCase();
				let tagType = null;
				switch (tagName) {
					case 'input':
						tagType = el.type.toLowerCase();
						if (tagType.match(/(text|password|number|week|time|date|datetime|datetime-local|search|tel|url)/g)) {
							result = (!isVarEmpty(val) && String(el.value).length <= val);
						}
						break;
					case 'textarea':
						result = (!isVarEmpty(val) && String(el.value).length <= val);
						break;
					default:
						result = false;
						break;
				}
				return result;
			},
			maxValue: (val, el) => {
				let result = false;
				const tagName = el.tagName.toLowerCase();
				let tagType = null;
				switch (tagName) {
					case 'input':
						tagType = el.type.toLowerCase();
						if (tagType.match(/(text|number)/g)) {
							result = (!isVarEmpty(val) && parseNumber(el.value, 'id-ID') <= val);
						}
						break;
					default:
						result = false;
						break;
				}
				return result;
			},
			strongPassword: (val, el) => {
				const result = [false, null];
				const tagType = el.type.toLowerCase();
				if (tagType === 'password') {
					const passwordValidator = zxcvbn(val);
					if (isObject(passwordValidator)) {
						if (Object.hasOwnProperty.call(passwordValidator, 'score') && Object.hasOwnProperty.call(passwordValidator, 'feedback')) {
							const { guesses, crack_times_display, score, feedback } = passwordValidator;
							if (score <= 2) {
								result[0] = false;
							}
							if (score >= 3) {
								result[0] = true;
							}
							result[1] = {
								guesses,
								crack_times_display,
								score,
								feedback,
							};
						}
					}
				}
				return result;
			},
			isDate: (config, el) => {
				const configDefault = {
					formatDate: 'YYYY-MM-DD',
					rangeCalendar: false,
				};
				const configMain = mergeObjectRecursive(configDefault, config);

				let result = false;
				const currentValue = el.value.toString();
				if (!configMain.rangeCalendar) {
					const dateValidator = moment(currentValue, configMain.formatDate.toString(), true);
					result = dateValidator.isValid();
				} else {
					if (currentValue.indexOf('~') > -1) {
						const currentValueArr = currentValue.split('~');
						if (currentValueArr.length === 2) {
							const dateValidator1 = moment(currentValueArr[0].trim().toString(), configMain.formatDate.toString(), true);
							const dateValidator2 = moment(currentValueArr[1].trim().toString(), configMain.formatDate.toString(), true);
							result = (dateValidator1.isValid() && dateValidator2.isValid());
						}
					}
				}
				return result;
			},
			isNumericDay: (val, el) => validator.isInteger(val, {
				min: 1,
				max: 31,
				allow_leading_zeroes: true,
			}),
			isNumericMonth: (val, el) => validator.isInteger(val, {
				min: 1,
				max: 12,
				allow_leading_zeroes: true,
			}),
			isNumericYear: (val, el) => validator.isIntegereger(val, {
				min: moment().subtract(100, 'years').format('Y'),
				max: moment().add(100, 'years').format('Y'),
				allow_leading_zeroes: false,
			}),
			isPhoneNumber: (val, el) => {
				let result = false;
				const tagName = el.tagName.toLowerCase();
				let tagType = null;
				switch (tagName) {
					case 'input':
						tagType = el.type.toLowerCase();
						if (tagType.match(/(text|tel)/g)) {
							let inputValues = val;
							inputValues = inputValues.replace(/[-_ ]+/g, '');
							inputValues = inputValues.replace(/[^0-9+]/g, '');
							result = validator.isMobilePhone(inputValues, ['id-ID', 'en-US', 'ja-JP', 'zh-CN', 'ru-RU', 'el-GR'], { strictMode: true });
						}
						break;
					default:
						result = false;
						break;
				}
				return result;
			},
			isEmail: (val, el) => {
				let result = false;
				const tagName = el.tagName.toLowerCase();
				let tagType = null;
				switch (tagName) {
					case 'input':
						tagType = el.type.toLowerCase();
						if (tagType.match(/(text|email|mail)/g)) {
							result = validator.isEmail(val);
						}
						break;
					default:
						result = false;
						break;
				}
				return result;
			},
			numericOnly: (val, el) => {
				let result = false;
				const tagName = el.tagName.toLowerCase();
				let tagType = null;
				switch (tagName) {
					case 'input':
						tagType = el.type.toLowerCase();
						if (tagType.match(/(text)/g)) {
							result = validator.isNumeric(val, { no_symbols: true });
						}
						break;
					default:
						result = false;
						break;
				}
				return result;
			},
		},
		behaviour: {
			datePicker: (config, el) => {
				const configDefault = { popper: {}, calendar: null };
				const configMain = mergeObjectRecursive(configDefault, config);
				const instanceList = {};

				const currentInput = el;
				const isElementExist = currentInput.parentNode.querySelector('.dropdown-menu');
				if (currentInput.parentNode.contains(isElementExist)) {
					removeNodes(isElementExist);
				}
				let calendarHTML = '<div class="dropdown-menu box-shadows p-3"><div class="d-flex align-items-center">';
				if (Array.isArray(configMain.calendar)) {
					if (configMain.calendar.length >= 1) {
						for (let i = 0; i < configMain.calendar.length; i += 1) {
							calendarHTML += `<div class="vanilla-calendar-${i + 1}"></div>`;
						}
					}
				}
				calendarHTML += '</div><div data-popper-arrow></div></div>';

				if (!currentInput.parentNode.contains(currentInput.parentNode.querySelector('.dropdown-menu'))) {
					const html2Node = ConvertStringToHTML(calendarHTML);
					while (html2Node.firstChild) { currentInput.parentNode.appendChild(html2Node.firstChild); }
				}

				const dropdownNode = currentInput.parentNode.querySelector('.dropdown-menu');
				if (currentInput.parentNode.contains(dropdownNode)) {
					instanceList.dropdown = bootstrap.Dropdown.getOrCreateInstance(currentInput.parentNode, configMain.popper);
					if (Array.isArray(configMain.calendar)) {
						if (configMain.calendar.length >= 1) {
							instanceList.calendar = [];
							for (let i = 0; i < configMain.calendar.length; i += 1) {
								const calendarNode = currentInput.parentNode.querySelector(`.vanilla-calendar-${i + 1}`);
								instanceList.calendar.push(new VanillaCalendar(calendarNode, configMain.calendar[i]));
								instanceList.calendar[i].init();
							}
						}
					}

					// Event
					eventOn('focus', currentInput, () => {
						instanceList.dropdown.show();
					});
					eventOn('click', document.body, (e) => {
						const clickElement = e.target;
						const checkParent1 = findAncestor(clickElement, '.input-group') || null;
						const checkParent2 = findAncestor(clickElement, '.dropdown-menu') || null;
						if ((isVarEmpty(checkParent1) || !isElement(checkParent1)) && (isVarEmpty(checkParent2) || !isElement(checkParent2)) && clickElement.className.indexOf('vanilla-calendar') < 0 && !clickElement.classList.contains('dropdown-menu')) {
							if (clickElement !== currentInput) {
								instanceList.dropdown.hide();
							}
						}
					});
				}
				return (Object.keys(instanceList).length >= 2) ? [true, instanceList] : false;
			},
		},
		messages: {
			error: {
				required: 'The {input} is required, please fill in the value.',
				minLength: 'The {input} must have a minimum length of {0} characters.',
				minValue: 'The {input} must have a minimum value to {0}',
				maxLength: 'The {input} must have a maximum length of {0} characters.',
				maxValue: 'The {input} must have a maximum value to {0}',
				strongPassword: 'Your password is weak, please change it for your safety!',
				isDate: 'Wrong format dates or the {input} is still empty.',
				isNumericDay: 'Wrong format numeric day, 01 - 31 only',
				isNumericMonth: 'Wrong format numeric day, 01 - 12 only',
				isNumericYear: 'Wrong format numeric day, YYYY (-+ 100) only',
				isPhoneNumber: 'Invalid Phone-number, please add your country code. e.g: 62 (Indonesia)',
				isEmail: 'Wrong format Email address.',
				numericOnly: 'Sorry {input} is only numeric values!',
			},
			success: {
				required: 'Looks good!',
				minLength: 'Looks good!',
				maxLength: 'Looks good!',
			},
		},
	};
	#inputs = [];
	#validated = {};
	#behaviour = {};
	#submitHandler = {
		default: (e) => {
			e.currentTarget.submit();
		},
		blank: (e) => {
			e.preventDefault();
			e.stopPropagation();
		},
		custom: (e) => false,
	};

	constructor(element, config) {
		this.form = (isElement(element)) ? element : null;
		this.config = (isObject(config)) ? mergeObjectRecursive(this.#default.config, config) : null;
		this.validator = {};
		this.#inputs = FormValidation.getAllFormElements(this.form);
		this.totalValid = 0;
		this.totalInvalid = 0;

		// Strict mode
		if (isObject(this.config)) {
			if (Object.hasOwnProperty.call(this.config, 'strict')) {
				const { strict } = this.config;
				const submitHandler = this.form.querySelector('[type="submit"]');
				if (isBoolean(strict.input)) {
					if (strict.input) {
						// Form submit action
						if (this.totalInvalid > 0) {
							if (isElement(submitHandler)) {
								submitHandler.classList.add('disabled');
								submitHandler.setAttribute('disabled', 'disabled');
								Object.entries(this.#submitHandler).forEach(([key, value]) => {
									this.form.removeEventListener('submit', value);
								});
								this.form.addEventListener('submit', this.#submitHandler.blank, false);
							}
						} else {
							if (isElement(submitHandler)) {
								submitHandler.classList.remove('disabled');
								submitHandler.removeAttribute('disabled');
								if (Object.hasOwnProperty.call(config, 'submitHandler')) {
									if (!isVarEmpty(config.submitHandler) && isFunction(config.submitHandler)) {
										Object.entries(this.#submitHandler).forEach(([key, value]) => {
											this.form.removeEventListener('submit', value);
										});
										this.#submitHandler.custom = (e) => config.submitHandler(this.form, e);
										this.form.addEventListener('submit', this.#submitHandler.custom, false);
									} else {
										Object.entries(this.#submitHandler).forEach(([key, value]) => {
											this.form.removeEventListener('submit', value);
										});
										this.form.addEventListener('submit', (e) => this.#submitHandler.default, false);
									}
								} else {
									Object.entries(this.#submitHandler).forEach(([key, value]) => {
										this.form.removeEventListener('submit', value);
									});
									this.form.addEventListener('submit', (e) => this.#submitHandler.default, false);
								}
							}
						}

						// Each input, on done typing
						let isShift = false;
						let isTab = false;
						// console.log(this.#inputs);
						this.#inputs.forEach((input, index) => {
							const tagName = input.tagName.toLowerCase();
							const inputGroup = findAncestor(input, '.input-group') || null;
							let tagType = null;
							const selectOnChange = (ev) => {
								this.validate();
							};
							const inputOnKeydown = (ev) => {
								if ((ev.key).toLowerCase() === 'shift') {
									isShift = true;
								}
								if ((ev.key).toLowerCase() === 'tab' || ev.keyCode === 9) {
									isTab = true;
								}
								if (isTab === true) {
									ev.preventDefault();
									ev.stopPropagation();
								}
							};
							const inputOnKeyup = (ev) => {
								if ((ev.key).toLowerCase() === 'shift') {
									isShift = true;
								}
								if ((ev.key).toLowerCase() === 'tab' || ev.keyCode === 9) {
									isTab = true;
								}
								if (isShift === false && isTab === true) {
									// setTimeout(() => {
									this.validate();
									isTab = false;
									isShift = false;
									// }, 1000);
								}
								if (isShift && isTab) {
									const prevElement = this.#inputs[(index - 1)] || null;
									if (!isVarEmpty(prevElement) && isElement(prevElement)) {
										let scrollWrapper = findAncestor(prevElement, '[data-scrollbar]') || null;
										if (!isVarEmpty(scrollWrapper) && isElement(scrollWrapper)) {
											scrollWrapper = scrollWrapper.querySelector('.scroll-content');
											const wrapRect = scrollWrapper.getBoundingClientRect();
											const elemRect = prevElement.getBoundingClientRect();
											scrollWrapper = Scrollbar.get(findAncestor(prevElement, '[data-scrollbar]'));
											scrollWrapper.scrollTo(0, elemRect.top - wrapRect.top, 500);
										} else {
											prevElement.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
										}
										prevElement.focus();

										isTab = false;
										// isShift = false;
									}
								}
								if (isShift === true) {
									setTimeout(() => {
										isShift = false;
									}, 1000);
								}
							};
							const inputOnFocus = (ev) => {
								const currElement = ev.currentTarget;
								const currBoundRect = currElement.getBoundingClientRect();
								const isAREWrapper = currElement.parentNode.classList.contains('are-input-1');
								const html2Node = ConvertStringToHTML('<span class="change-focus">Tab</span>');
								while (html2Node.firstChild) { insertAfter(html2Node.firstChild, currElement); }
								const node2Select = currElement.parentNode.querySelector('.change-focus');
								if (currElement.parentNode.contains(node2Select)) {
									const node2BoundRect = node2Select.getBoundingClientRect();
									let calcTopOffset = currBoundRect.height - node2BoundRect.height;
									calcTopOffset = (calcTopOffset > 0) ? calcTopOffset / 2 : 8;
									node2Select.setAttribute('style', `top: ${calcTopOffset}px !important;`);
									if (isAREWrapper) {
										currElement.parentNode.classList.add('position-relative');
									}
									setTimeout(() => {
										node2Select.classList.add('show');
									}, 50);
								}
							};
							const inputOnFocusout = (ev) => {
								const currElement = ev.currentTarget;
								const node2Remove = currElement.parentNode.querySelector('.change-focus');
								if (currElement.parentNode.contains(node2Remove)) {
									node2Remove.classList.remove('show');
									setTimeout(() => {
										removeNodes(node2Remove);
									}, 300);
								}
							};
							if (index !== 0) {
								if (!isVarEmpty(inputGroup)) {
									const inputGroupText = inputGroup.querySelector('.input-group-text');
									if (isElement(inputGroup)) {
										inputGroupText.classList.add('bg-gray-200');
									}
								}
								if (!input.classList.contains('input-sv')) {
									input.setAttribute('disabled', 'disabled');
								}
							}
							switch (tagName) {
								case 'select':
									eventOff('change', input, selectOnChange);
									eventOn('change', input, selectOnChange);
									break;
								case 'input':
									tagType = input.type.toLowerCase();
									if (tagType.match(/(checkbox|radio)/g)) {
										eventOff('change', input, selectOnChange);
										eventOn('change', input, selectOnChange);
									} else {
										inputDoneTyping(input, 3, () => {
											this.validate();
										});
										eventOff('focus', input, inputOnFocus);
										eventOn('focus', input, inputOnFocus);
										eventOff('focusout', input, inputOnFocusout);
										eventOn('focusout', input, inputOnFocusout);
									}
									break;
								case 'textarea':
									eventOff('focus', input, inputOnFocus);
									eventOn('focus', input, inputOnFocus);
									eventOff('focusout', input, inputOnFocusout);
									eventOn('focusout', input, inputOnFocusout);
									break;
								default:
									inputDoneTyping(input, 3, () => {
										this.validate();
									});
									break;
							}
							eventOff('keydown', input, inputOnKeydown);
							eventOn('keydown', input, inputOnKeydown);
							eventOff('keyup', input, inputOnKeyup);
							eventOn('keyup', input, inputOnKeyup);
						});
						if (isElement(submitHandler)) {
							submitHandler.classList.add('disabled');
							submitHandler.setAttribute('disabled', 'disabled');
							Object.entries(this.#submitHandler).forEach(([key, value]) => {
								this.form.removeEventListener('submit', value);
							});
							this.form.addEventListener('submit', this.#submitHandler.blank, false);
						}
					}
				}
			}
		}
	}
	updateEvent() {
		this.#inputs = FormValidation.getAllFormElements(this.form);
		this.#inputs.forEach((input) => {
			recreateNode(input);
		});
		this.#inputs = FormValidation.getAllFormElements(this.form);
		this.#inputs.forEach((perInput) => {
			const tagName = perInput.tagName.toLowerCase();
			switch (tagName) {
				case 'select': {
					const listOption = Array.from(perInput.options) || [];
					const selectedOption = perInput.querySelector('option[selected]') || null;
					if (isElement(selectedOption) && perInput.contains(selectedOption)) {
						if (listOption.length >= 1) {
							const indexOption = listOption.indexOf(selectedOption);
							if (indexOption > -1) {
								perInput.value = listOption[indexOption].value;
							}
						}
					}
					break;
				}
				default: {
					break;
				}
			}
		});

		// Strict mode
		if (isObject(this.config)) {
			if (Object.hasOwnProperty.call(this.config, 'strict')) {
				const { strict } = this.config;
				const submitHandler = this.form.querySelector('[type="submit"]');
				if (isBoolean(strict.input)) {
					if (strict.input) {
						// Form submit action
						if (this.totalInvalid > 0) {
							if (isElement(submitHandler)) {
								submitHandler.classList.add('disabled');
								submitHandler.setAttribute('disabled', 'disabled');
								Object.entries(this.#submitHandler).forEach(([key, value]) => {
									this.form.removeEventListener('submit', value);
								});
								this.form.addEventListener('submit', this.#submitHandler.blank, false);
							}
						} else {
							if (isElement(submitHandler)) {
								submitHandler.classList.remove('disabled');
								submitHandler.removeAttribute('disabled');
								if (Object.hasOwnProperty.call(this.config, 'submitHandler')) {
									if (!isVarEmpty(this.config.submitHandler) && isFunction(this.config.submitHandler)) {
										Object.entries(this.#submitHandler).forEach(([key, value]) => {
											this.form.removeEventListener('submit', value);
										});
										this.#submitHandler.custom = (e) => config.submitHandler(this.form, e);
										this.form.addEventListener('submit', this.#submitHandler.custom, false);
									} else {
										Object.entries(this.#submitHandler).forEach(([key, value]) => {
											this.form.removeEventListener('submit', value);
										});
										this.form.addEventListener('submit', (e) => this.#submitHandler.default, false);
									}
								} else {
									Object.entries(this.#submitHandler).forEach(([key, value]) => {
										this.form.removeEventListener('submit', value);
									});
									this.form.addEventListener('submit', (e) => this.#submitHandler.default, false);
								}
							}
						}

						// Each input, on done typing
						let isShift = false;
						let isTab = false;
						// console.log(this.#inputs);
						this.#inputs.forEach((input, index) => {
							const tagName = input.tagName.toLowerCase();
							const inputGroup = findAncestor(input, '.input-group') || null;
							let tagType = null;
							const selectOnChange = (ev) => {
								this.validate();
							};
							const inputOnKeydown = (ev) => {
								if ((ev.key).toLowerCase() === 'shift') {
									isShift = true;
								}
								if ((ev.key).toLowerCase() === 'tab' || ev.keyCode === 9) {
									isTab = true;
								}
								if (isTab === true) {
									ev.preventDefault();
									ev.stopPropagation();
								}
							};
							const inputOnKeyup = (ev) => {
								if ((ev.key).toLowerCase() === 'shift') {
									isShift = true;
								}
								if ((ev.key).toLowerCase() === 'tab' || ev.keyCode === 9) {
									isTab = true;
								}
								if (isShift === false && isTab === true) {
									// setTimeout(() => {
									this.validate();
									isTab = false;
									isShift = false;
									// }, 1000);
								}
								if (isShift && isTab) {
									const prevElement = this.#inputs[(index - 1)] || null;
									if (!isVarEmpty(prevElement) && isElement(prevElement)) {
										let scrollWrapper = findAncestor(prevElement, '[data-scrollbar]') || null;
										if (!isVarEmpty(scrollWrapper) && isElement(scrollWrapper)) {
											scrollWrapper = scrollWrapper.querySelector('.scroll-content');
											const wrapRect = scrollWrapper.getBoundingClientRect();
											const elemRect = prevElement.getBoundingClientRect();
											scrollWrapper = Scrollbar.get(findAncestor(prevElement, '[data-scrollbar]'));
											scrollWrapper.scrollTo(0, elemRect.top - wrapRect.top, 500);
										} else {
											prevElement.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
										}
										prevElement.focus();

										isTab = false;
										// isShift = false;
									}
								}
								if (isShift === true) {
									setTimeout(() => {
										isShift = false;
									}, 1000);
								}
							};
							const inputOnFocus = (ev) => {
								const currElement = ev.currentTarget;
								const currBoundRect = currElement.getBoundingClientRect();
								const isAREWrapper = currElement.parentNode.classList.contains('are-input-1');
								const html2Node = ConvertStringToHTML('<span class="change-focus">Tab</span>');
								while (html2Node.firstChild) { insertAfter(html2Node.firstChild, currElement); }
								const node2Select = currElement.parentNode.querySelector('.change-focus');
								if (currElement.parentNode.contains(node2Select)) {
									const node2BoundRect = node2Select.getBoundingClientRect();
									let calcTopOffset = currBoundRect.height - node2BoundRect.height;
									calcTopOffset = (calcTopOffset > 0) ? calcTopOffset / 2 : 8;
									node2Select.setAttribute('style', `top: ${calcTopOffset}px !important;`);
									if (isAREWrapper) {
										currElement.parentNode.classList.add('position-relative');
									}
									setTimeout(() => {
										node2Select.classList.add('show');
									}, 50);
								}
							};
							const inputOnFocusout = (ev) => {
								const currElement = ev.currentTarget;
								const node2Remove = currElement.parentNode.querySelector('.change-focus');
								if (currElement.parentNode.contains(node2Remove)) {
									node2Remove.classList.remove('show');
									setTimeout(() => {
										removeNodes(node2Remove);
									}, 300);
								}
							};
							if (index !== 0) {
								if (!isVarEmpty(inputGroup)) {
									const inputGroupText = inputGroup.querySelector('.input-group-text');
									if (isElement(inputGroup)) {
										inputGroupText.classList.add('bg-gray-200');
									}
								}
								if (!input.classList.contains('input-sv')) {
									input.setAttribute('disabled', 'disabled');
								}
							}
							switch (tagName) {
								case 'select':
									eventOff('change', input, selectOnChange);
									eventOn('change', input, selectOnChange);
									break;
								case 'input':
									tagType = input.type.toLowerCase();
									if (tagType.match(/(checkbox|radio)/g)) {
										eventOff('change', input, selectOnChange);
										eventOn('change', input, selectOnChange);
									} else {
										inputDoneTyping(input, 3, () => {
											this.validate();
										});
										eventOff('focus', input, inputOnFocus);
										eventOn('focus', input, inputOnFocus);
										eventOff('focusout', input, inputOnFocusout);
										eventOn('focusout', input, inputOnFocusout);
									}
									break;
								case 'textarea':
									eventOff('focus', input, inputOnFocus);
									eventOn('focus', input, inputOnFocus);
									eventOff('focusout', input, inputOnFocusout);
									eventOn('focusout', input, inputOnFocusout);
									break;
								default:
									inputDoneTyping(input, 3, () => {
										this.validate();
									});
									break;
							}
							eventOff('keydown', input, inputOnKeydown);
							eventOn('keydown', input, inputOnKeydown);
							eventOff('keyup', input, inputOnKeyup);
							eventOn('keyup', input, inputOnKeyup);
						});
						if (isElement(submitHandler)) {
							submitHandler.classList.add('disabled');
							submitHandler.setAttribute('disabled', 'disabled');
							Object.entries(this.#submitHandler).forEach(([key, value]) => {
								this.form.removeEventListener('submit', value);
							});
							this.form.addEventListener('submit', this.#submitHandler.blank, false);
						}
					}
				}
			}
		}
	}
	behaviourInit() {
		const { config } = this;
		// const listValidators = [...Object.keys(this.#default.validator), ...Object.keys(this.validator)];
		const listBehaviours = [...Object.keys(this.#default.behaviour)];
		if (Object.keys(this.#behaviour).length >= 1) {
			Object.entries(this.#behaviour).forEach(([inputName, behaviour]) => {
				if (Object.hasOwnProperty.call(behaviour, 'listInstance')) {
					if (behaviour.listPassed.length >= 1) {
						Object.entries(behaviour.listInstance).forEach(([key, val]) => {
							if (isObject(val)) {
								Object.entries(val).forEach(([key2, val2]) => {
									switch (key2) {
										case 'calendar': {
											if (Array.isArray(val2) && val2.length >= 1) {
												val2.forEach((perInstance) => {
													perInstance.reset();
												});
												this.#behaviour[inputName].listInstance.calendar = [];
											}
											break;
										}
										case 'dropdown': {
											val2.dispose();
											break;
										}
										default: break;
									}
								});
							}
						});
					}
				}
			});
		}

		if (isObject(config)) {
			// Process form to be validated by rules
			if (Object.hasOwnProperty.call(config, 'rules') && Object.hasOwnProperty.call(config, 'behaviour')) {
				if (Object.keys(config.behaviour).length > 0) {
					Object.entries(config.behaviour).forEach(([key, item]) => {
						this.#behaviour[key] = {};
						const listElementBehaviours = this.findInputByName(key) || [];
						if (Array.isArray(listElementBehaviours) && listElementBehaviours.length > 0) {
							listElementBehaviours.forEach((eleBehaviour) => {
								if (isElement(eleBehaviour) && isObject(item)) {
									// List the valid rules
									const listBehaviour = Object.keys(item).filter((behaviour) => listBehaviours.includes(behaviour));
									item = listBehaviour.reduce((cur, name) => Object.assign(cur, { [name]: item[name] }), {});
									// Set up record for each Input to be add behaviour
									this.#behaviour[key].listBehaviour = Array.from(Object.keys(item));
									this.#behaviour[key].listPassed = [];
									this.#behaviour[key].listInstance = {};
									Object.entries(item).forEach(([behaviour, set]) => {
										// Run validating process
										if (isBoolean(set)) {
											if (set === true) {
												if (Object.hasOwnProperty.call(this.#default.behaviour, behaviour)) {
													const result = this.#default.behaviour[behaviour](eleBehaviour.value, eleBehaviour);
													if (isBoolean(result) && !Array.isArray(result)) {
														if (result) {
															this.#behaviour[key].listPassed.push(behaviour);
														}
													}
													if (Array.isArray(result) && (result.length >= 1 && result.length <= 2)) {
														if (isBoolean(result[0]) && !Array.isArray(result[0])) {
															if (result[0]) {
																this.#behaviour[key].listPassed.push(behaviour);
															}
														}
														if (isObject(result[1])) {
															Object.assign(this.#behaviour[key].listInstance, { [behaviour]: result[1] });
														}
													}
												}
											} else {
												const idxRule = this.#behaviour[key].listBehaviour.indexOf(behaviour);
												if (idxRule > -1) {
													this.#behaviour[key].listBehaviour.splice(idxRule, 1);
												}
											}
										}
										if (isObject(set)) {
											if (Object.hasOwnProperty.call(this.#default.behaviour, behaviour)) {
												const result = this.#default.behaviour[behaviour](set, eleBehaviour);
												if (isBoolean(result) && !Array.isArray(result)) {
													if (result) {
														this.#behaviour[key].listPassed.push(behaviour);
													}
												}
												if (Array.isArray(result) && (result.length >= 1 && result.length <= 2)) {
													if (isBoolean(result[0]) && !Array.isArray(result[0])) {
														if (result[0]) {
															this.#behaviour[key].listPassed.push(behaviour);
														}
													}
													if (isObject(result[1])) {
														Object.assign(this.#behaviour[key].listInstance, { [behaviour]: result[1] });
													}
												}
											}
										}
									});
									// this.#validated[key].isValid = (this.#validated[key].listPassed.length === this.#validated[key].listRules.length);
								}
							});
						}
					});
				}
			}
		}
	}

	get totalInputs() { return this.#inputs.length; }
	get listInputs() { return this.#inputs; }
	get currentConfig() { return mergeObjectRecursive(this.#default.config, this.config); }
	get behaviourInstances() {
		const result = {};
		if (Object.keys(this.#behaviour).length >= 1) {
			Object.entries(this.#behaviour).forEach(([key, val]) => {
				if (isObject(val) && Object.hasOwnProperty.call(val, 'listInstance')) {
					result[key] = val.listInstance;
				}
			});
		}
		return result;
		// return null;
	}

	validate() {
		// Reset state
		this.totalValid = 0;
		this.totalInvalid = 0;
		this.#validated = {};

		const { config } = this;
		const listValidators = [...Object.keys(this.#default.validator), ...Object.keys(this.validator)];
		if (isObject(config)) {
			// Process form to be validated by rules
			if (Object.hasOwnProperty.call(config, 'rules')) {
				if (Object.keys(config.rules).length > 0) {
					Object.entries(config.rules).forEach(([key, item]) => {
						this.#validated[key] = {};
						const listElementToValidate = this.findInputByName(key) || [];
						if (Array.isArray(listElementToValidate) && listElementToValidate.length > 0) {
							listElementToValidate.forEach((eleValidate) => {
								if (isElement(eleValidate) && isObject(item)) {
									// List the valid rules
									const listRules = Object.keys(item).filter((rule) => listValidators.includes(rule));
									item = listRules.reduce((cur, name) => Object.assign(cur, { [name]: item[name] }), {});
									// Set up record for each Input to be validated
									this.#validated[key].listRules = Array.from(Object.keys(item));
									this.#validated[key].listPassed = [];
									this.#validated[key].listReturn = {};
									Object.entries(item).forEach(([rule, set]) => {
										// Run validating process
										if (isBoolean(set)) {
											if (set === true) {
												if (Object.hasOwnProperty.call(this.#default.validator, rule)) {
													const result = this.#default.validator[rule](eleValidate.value, eleValidate);
													if (isBoolean(result) && !Array.isArray(result)) {
														if (result) {
															this.#validated[key].listPassed.push(rule);
														}
													}
													if (Array.isArray(result) && (result.length >= 1 && result.length <= 2)) {
														if (isBoolean(result[0]) && !Array.isArray(result[0])) {
															if (result[0]) {
																this.#validated[key].listPassed.push(rule);
															}
														}
														if (isObject(result[1])) {
															Object.assign(this.#validated[key].listReturn, { [rule]: result[1] });
														}
													}
												}
												if (Object.hasOwnProperty.call(this.validator, rule)) {
													const result = this.validator[rule](eleValidate.value, eleValidate);
													if (isBoolean(result) && !Array.isArray(result)) {
														if (result) {
															this.#validated[key].listPassed.push(rule);
														}
													}
													if (Array.isArray(result) && (result.length >= 1 && result.length <= 2)) {
														if (isBoolean(result[0]) && !Array.isArray(result[0])) {
															if (result[0]) {
																this.#validated[key].listPassed.push(rule);
															}
														}
														if (isObject(result[1])) {
															Object.assign(this.#validated[key].listReturn, { [rule]: result[1] });
														}
													}
												}
											} else {
												const idxRule = this.#validated[key].listRules.indexOf(rule);
												if (idxRule > -1) {
													this.#validated[key].listRules.splice(idxRule, 1);
												}
											}
										}
										if (isInt(set) || isString(set)) {
											if (Object.hasOwnProperty.call(this.#default.validator, rule)) {
												const result = this.#default.validator[rule](set, eleValidate);
												if (isBoolean(result) && !Array.isArray(result)) {
													if (result) {
														this.#validated[key].listPassed.push(rule);
													}
												}
												if (Array.isArray(result) && (result.length >= 1 && result.length <= 2)) {
													if (isBoolean(result[0]) && !Array.isArray(result[0])) {
														if (result[0]) {
															this.#validated[key].listPassed.push(rule);
														}
													}
													if (isObject(result[1])) {
														Object.assign(this.#validated[key].listReturn, { [rule]: result[1] });
													}
												}
											}
											if (Object.hasOwnProperty.call(this.validator, rule)) {
												const result = this.validator[rule](set, eleValidate);
												if (isBoolean(result) && !Array.isArray(result)) {
													if (result) {
														this.#validated[key].listPassed.push(rule);
													}
												}
												if (Array.isArray(result) && (result.length >= 1 && result.length <= 2)) {
													if (isBoolean(result[0]) && !Array.isArray(result[0])) {
														if (result[0]) {
															this.#validated[key].listPassed.push(rule);
														}
													}
													if (isObject(result[1])) {
														Object.assign(this.#validated[key].listReturn, { [rule]: result[1] });
													}
												}
											}
										}
										if (isObject(set)) {
											if (Object.hasOwnProperty.call(set, 'config')) {
												if (Object.hasOwnProperty.call(this.#default.validator, rule)) {
													const result = this.#default.validator[rule](set.config, eleValidate);
													if (isBoolean(result) && !Array.isArray(result)) {
														if (result) {
															this.#validated[key].listPassed.push(rule);
														}
													}
													if (Array.isArray(result) && (result.length >= 1 && result.length <= 2)) {
														if (isBoolean(result[0]) && !Array.isArray(result[0])) {
															if (result[0]) {
																this.#validated[key].listPassed.push(rule);
															}
														}
														if (isObject(result[1])) {
															Object.assign(this.#validated[key].listReturn, { [rule]: result[1] });
														}
													}
												}
												if (Object.hasOwnProperty.call(this.validator, rule)) {
													const result = this.validator[rule](set.config, eleValidate);
													if (isBoolean(result) && !Array.isArray(result)) {
														if (result) {
															this.#validated[key].listPassed.push(rule);
														}
													}
													if (Array.isArray(result) && (result.length >= 1 && result.length <= 2)) {
														if (isBoolean(result[0]) && !Array.isArray(result[0])) {
															if (result[0]) {
																this.#validated[key].listPassed.push(rule);
															}
														}
														if (isObject(result[1])) {
															Object.assign(this.#validated[key].listReturn, { [rule]: result[1] });
														}
													}
												}
											}
										}
									});
									this.#validated[key].isValid = (this.#validated[key].listPassed.length === this.#validated[key].listRules.length);
									// Add some specific logic when valid or not
									if (this.#validated[key].isValid) {
										if (Object.hasOwnProperty.call(config, 'onInputSuccess')) {
											if (!isVarEmpty(config.onInputSuccess) && isFunction(config.onInputSuccess)) {
												const listSuccessRule = this.#validated[key].listRules.filter((eachRule) => this.#validated[key].listPassed.includes(eachRule));
												const listSuccessMessage = {};
												const listSuccessData = {};
												if (listSuccessRule.length > 0) {
													listSuccessRule.forEach((eachRule) => {
														// Serve success message, from default or pre-defined message
														let successMessage = this.#default.messages.success[eachRule] || 'Looks good!';
														if (Object.hasOwnProperty.call(config, 'messages')) {
															if (Object.keys(config.messages).length > 0) {
																if (Object.hasOwnProperty.call(config.messages, key)) {
																	if (Object.keys(config.messages[key]).includes(eachRule)) {
																		if (isObject(config.messages[key][eachRule])) {
																			if (Object.hasOwnProperty.call(config.messages[key][eachRule], 'success')) {
																				successMessage = config.messages[key][eachRule].success;
																			}
																		}
																	}
																}
															}
														}
														// Format RAW success message, into actual message
														const lenMatchRegex = (successMessage.match(/\{[0-9]+\}/gi) || []).length;
														if (/\{[0-9]+\}/gi.test(successMessage)) {
															const itemData = item[eachRule];
															if (isObject(itemData)) {
																if (Object.hasOwnProperty.call(itemData, 'orderFormatMessage')) {
																	if (Object.hasOwnProperty.call(itemData.orderFormatMessage, 'success')) {
																		if (lenMatchRegex === 1) {
																			if (isString(itemData.orderFormatMessage.success)) {
																				successMessage = successMessage.formatUnicorn((!isVarEmpty(itemData)) ? String(itemData.orderFormatMessage.success) : '-');
																			}
																		}
																		if (lenMatchRegex > 1) {
																			if (Array.isArray(itemData.orderFormatMessage.success) && itemData.orderFormatMessage.success.length > 1) {
																				successMessage = successMessage.formatUnicorn(...itemData.orderFormatMessage.success);
																			}
																		}
																	}
																}
															} else {
																if (lenMatchRegex === 1) {
																	successMessage = successMessage.formatUnicorn((!(isElement(itemData) || isFunction(itemData) || isObject(itemData) || isVarEmpty(itemData))) ? String(itemData) : '-');
																}
															}
														}
														if (/\{(input)\}/gi.test(successMessage)) {
															let inputTitle = eleValidate.parentNode.querySelector('label');
															inputTitle = (isElement(inputTitle)) ? inputTitle.textContent.trim() : eleValidate.name.trim();
															if (inputTitle.slice(-1) === ':') {
																inputTitle = inputTitle.slice(0, -1);
															}
															// successMessage = successMessage.replace(new RegExp('\\{(input)\\}', 'gi'), inputTitle);
															successMessage = successMessage.replace(/\{(input)\}/gi, inputTitle);
														}
														listSuccessMessage[eachRule] = successMessage;

														// Serve success data, from each rule validator result
														if (Object.hasOwnProperty.call(this.#validated[key].listReturn, eachRule)) {
															listSuccessData[eachRule] = this.#validated[key].listReturn[eachRule];
														} else {
															listSuccessData[eachRule] = null;
														}
													});
												}

												config.onInputSuccess(eleValidate, listSuccessMessage, listSuccessData);
											}
										}
									} else {
										if (Object.hasOwnProperty.call(config, 'onInputError')) {
											if (!isVarEmpty(config.onInputError) && isFunction(config.onInputError)) {
												const listErrorRule = this.#validated[key].listRules.filter((eachRule) => !this.#validated[key].listPassed.includes(eachRule));
												const listErrorMessage = {};
												const listErrorData = {};
												if (listErrorRule.length > 0) {
													listErrorRule.forEach((eachRule) => {
														// Serve error message, from default or pre-defined message
														let errorMessage = this.#default.messages.error[eachRule] || `Validation on "${eachRule}" data error!`;
														if (Object.hasOwnProperty.call(config, 'messages')) {
															if (Object.keys(config.messages).length > 0) {
																if (Object.hasOwnProperty.call(config.messages, key)) {
																	if (Object.keys(config.messages[key]).includes(eachRule)) {
																		if (isObject(config.messages[key][eachRule])) {
																			if (Object.hasOwnProperty.call(config.messages[key][eachRule], 'error')) {
																				errorMessage = config.messages[key][eachRule].error;
																			}
																		}
																		if (isString(config.messages[key][eachRule])) {
																			errorMessage = config.messages[key][eachRule];
																		}
																	}
																}
															}
														}
														// Format RAW error message, into actual message
														const lenMatchRegex = (errorMessage.match(/\{[0-9]+\}/gi) || []).length;
														if (/\{[0-9]+\}/gi.test(errorMessage)) {
															const itemData = item[eachRule];
															if (isObject(itemData)) {
																if (Object.hasOwnProperty.call(itemData, 'orderFormatMessage')) {
																	if (Object.hasOwnProperty.call(itemData.orderFormatMessage, 'error')) {
																		if (lenMatchRegex === 1) {
																			if (isString(itemData.orderFormatMessage.error)) {
																				errorMessage = errorMessage.formatUnicorn((!isVarEmpty(itemData)) ? String(itemData.orderFormatMessage.error) : '-');
																			}
																		}
																		if (lenMatchRegex > 1) {
																			if (Array.isArray(itemData.orderFormatMessage.error) && itemData.orderFormatMessage.error.length > 1) {
																				errorMessage = errorMessage.formatUnicorn(...itemData.orderFormatMessage.error);
																			}
																		}
																	}
																}
															} else {
																if (lenMatchRegex === 1) {
																	let messageValue = '-';
																	if (!(isElement(itemData) || isFunction(itemData) || isObject(itemData) || isVarEmpty(itemData))) {
																		if (isInt(itemData)) {
																			messageValue = parseFloat(itemData).toLocaleString('id-ID');
																		} else {
																			messageValue = String(itemData).toString();
																		}
																	}
																	errorMessage = errorMessage.formatUnicorn(messageValue);
																}
															}
														}
														if (/\{(input)\}/gi.test(errorMessage)) {
															let inputTitle = eleValidate.parentNode.querySelector('label');
															inputTitle = (isElement(inputTitle)) ? inputTitle.textContent.trim() : eleValidate.name.trim();
															if (inputTitle.slice(-1) === ':') {
																inputTitle = inputTitle.slice(0, -1);
															}
															// errorMessage = errorMessage.replace(new RegExp('\\{(input)\\}', 'gi'), inputTitle);
															errorMessage = errorMessage.replace(/\{(input)\}/gi, inputTitle);
														}
														listErrorMessage[eachRule] = errorMessage;

														// Serve error data, from each rule validator result
														if (Object.hasOwnProperty.call(this.#validated[key].listReturn, eachRule)) {
															listErrorData[eachRule] = this.#validated[key].listReturn[eachRule];
														} else {
															listErrorData[eachRule] = null;
														}
													});
												}
												config.onInputError(eleValidate, listErrorMessage, listErrorData);
											}
										}
									}
								}
							});
						}
					});
				}
			}
			// Calculate total valid/invalid
			if (Object.keys(this.#validated).length > 0) {
				Object.values(this.#validated).forEach((item) => {
					if (Object.hasOwnProperty.call(item, 'isValid')) {
						if (item.isValid) {
							this.totalValid += 1;
						} else {
							this.totalInvalid += 1;
						}
					}
				});
			}
			// Strict mode
			if (Object.hasOwnProperty.call(config, 'strict')) {
				const { strict } = config;
				const submitHandler = this.form.querySelector('[type="submit"]');
				if (isBoolean(strict.input)) {
					if (strict.input) {
						const totalInput = this.listInputs.filter((eachInput) => Object.keys(config.rules).includes(eachInput.name.replace(/[[]]+/g, '')));
						let nextFocus = null;
						let isLockNext = false;
						let tagName = '';
						let tagType = '';
						let inputName = '';
						totalInput.every((eachInput) => {
							if (isLockNext === false && eachInput.classList.contains('is-invalid')) {
								nextFocus = eachInput;
								tagName = String(eachInput.tagName).toLowerCase() || '';
								tagType = String(eachInput.type).toLowerCase() || '';
								inputName = String(eachInput.getAttribute('name')) || '';
								isLockNext = true;
							}
							if (eachInput.classList.contains('is-valid')) {
								const inputGroup = findAncestor(eachInput, '.input-group') || null;
								if (!isVarEmpty(inputGroup)) {
									const inputGroupText = inputGroup.querySelector('.input-group-text');
									if (isElement(inputGroup)) {
										inputGroupText.classList.remove('bg-gray-200');
									}
								}
								eachInput.classList.remove('disabled');
								eachInput.removeAttribute('disabled');
							}
							return true;
						});
						if (isElement(nextFocus)) {
							const inputGroup = findAncestor(nextFocus, '.input-group') || null;
							if (!isVarEmpty(inputGroup)) {
								const inputGroupText = inputGroup.querySelector('.input-group-text');
								if (isElement(inputGroup)) {
									inputGroupText.classList.remove('bg-gray-200');
								}
							}
							if (tagType.match(/(checkbox|radio)/g)) {
								if (!isVarEmpty(inputName)) {
									const inputList = this.findInputByName(inputName) || [];
									if (inputList.length === 1) {
										nextFocus.classList.remove('disabled');
										nextFocus.removeAttribute('disabled');
									}
									if (inputList.length >= 2) {
										inputList.forEach((perInput) => {
											perInput.classList.remove('disabled');
											perInput.removeAttribute('disabled');
										});
									}
								}
							} else {
								nextFocus.classList.remove('disabled');
								nextFocus.removeAttribute('disabled');
							}
							setTimeout(() => {
								nextFocus.focus();
							}, 500);
							let scrollWrapper = findAncestor(nextFocus, '[data-scrollbar]') || null;
							if (!isVarEmpty(scrollWrapper) && isElement(scrollWrapper)) {
								scrollWrapper = scrollWrapper.querySelector('.scroll-content');
								const wrapRect = scrollWrapper.getBoundingClientRect();
								const elemRect = nextFocus.getBoundingClientRect();
								scrollWrapper = Scrollbar.get(findAncestor(nextFocus, '[data-scrollbar]'));
								scrollWrapper.scrollTo(0, elemRect.top - wrapRect.top, 500);
							} else {
								nextFocus.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
							}
						}
					}
				}
				if (this.totalInvalid > 0) {
					if (isElement(submitHandler)) {
						submitHandler.classList.add('disabled');
						submitHandler.setAttribute('disabled', 'disabled');
						Object.entries(this.#submitHandler).forEach(([key, value]) => {
							this.form.removeEventListener('submit', value);
						});
						this.form.addEventListener('submit', this.#submitHandler.blank, false);
					}
				} else {
					if (isElement(submitHandler)) {
						submitHandler.classList.remove('disabled');
						submitHandler.removeAttribute('disabled');
						submitHandler.focus();
						if (Object.hasOwnProperty.call(config, 'submitHandler')) {
							if (!isVarEmpty(config.submitHandler) && isFunction(config.submitHandler)) {
								Object.entries(this.#submitHandler).forEach(([key, value]) => {
									this.form.removeEventListener('submit', value);
								});
								this.#submitHandler.custom = (e) => config.submitHandler(this.form, e);
								this.form.addEventListener('submit', this.#submitHandler.custom, false);
							} else {
								this.form.addEventListener('submit', this.#submitHandler.default, false);
							}
						} else {
							this.form.addEventListener('submit', this.#submitHandler.default, false);
						}
					}
				}
			}
		}
	}

	addValidator(name, callback) {
		try {
			const listDefaultRules = Object.keys(this.#default.validator) || [];
			if (Array.isArray(listDefaultRules) && listDefaultRules.length >= 1) {
				if (!listDefaultRules.includes(name)) {
					if (isFunction(callback)) {
						this.validator[name] = callback;
					} else {
						throw new Error('Argument 2 must be callable Function!');
					}
				} else {
					throw new Error('Argument 1 cannot be the same as the default validator name!');
				}
			} else {
				throw new Error('Default validator not found or empty!');
			}
		} catch (err) {
			console.warn(err);
		}
	}

	addRule(input, rules) {
		try {
			if (isString(input) && isObject(rules)) {
				const availableInput = this.#inputs.map((el) => ((!isVarEmpty(el.name)) ? el.name : '-')) || [];
				if (availableInput.includes(input)) {
					const currentRules = Object.keys(this.config.rules[input]) || [];
					const rulesNotSets = Object.keys(rules).filter((newRule) => !currentRules.includes(newRule));
					rules = rulesNotSets.reduce((cur, name) => Object.assign(cur, { [name]: rules[name] }), {});
					this.config.rules[input] = mergeObjectRecursive(this.config.rules[input], rules);
				} else {
					throw new Error('Argument 1 input not found/registered!');
				}
			} else {
				throw new Error('Please fill Argument 1(string) and 2(object) correctly!');
			}
		} catch (err) {
			console.warn(err);
		}
	}

	static getAllFormElements(element, excludeHiddens = true) {
		return Array.from(element.querySelectorAll(`*${(excludeHiddens) ? '[name]:not([type="hidden"])' : '[name]'}`)).filter((tag) => ['select', 'textarea', 'input'].includes(tag.tagName.toLowerCase()));
	}

	findInputByName(item) {
		return this.#inputs.filter((el) => {
			let inputName = el.getAttribute('name');
			inputName = inputName.replace(/[[]]+/g, '');
			return inputName === item.replace(/[[]]+/g, '');
		});
	}
}

/*
	{
		rules: {
			username: {
				required: true,

			}
		},
		message: {}
	}
*/
