/* eslint-disable max-classes-per-file */
/* eslint-disable class-methods-use-this */
/* eslint-disable no-underscore-dangle */
/* eslint-disable no-param-reassign */
/* eslint-disable object-curly-newline */
/* eslint-disable no-lonely-if */
/* eslint-disable max-len */
/* eslint-disable no-loop-func */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* eslint no-undef: "error" */
/* global axios, bootstrap, moment, Scrollbar */

/* == Module import == */
// import { initializeApp } from 'firebase/app';
import 'animate.css';
// import 'datatables.net/js/jquery.dataTables.min';
import 'datatables.net-bs5/js/dataTables.bootstrap5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';
import 'datatables.net-buttons-bs5/js/buttons.bootstrap5';
import 'datatables.net-buttons-bs5/css/buttons.bootstrap5.css';
import 'datatables.net-fixedcolumns-bs5/js/fixedColumns.bootstrap5';
import 'datatables.net-fixedcolumns-bs5/css/fixedColumns.bootstrap5.css';
import '@uvarov.frontend/vanilla-calendar/build/vanilla-calendar.min.css';// Basic styles
import '@uvarov.frontend/vanilla-calendar/build/themes/light.min.css'; // Additional styles
import '@uvarov.frontend/vanilla-calendar/build/themes/dark.min.css'; // Additional styles
// import Scrollbar from 'smooth-scrollbar';
import OverscrollPlugin from 'smooth-scrollbar/plugins/overscroll/index';
import ModalPlugin from './plugins/smooth-scrollbar/ModalPlugin';
import HorizontalScrollPlugin from './plugins/smooth-scrollbar/HorizontalScrollPlugin';
import DisableScrollPlugin from './plugins/smooth-scrollbar/DisableScrollPlugin';
import fontawesome from './utils/fontawesome';
import { ConvertStringToHTML } from './utils/func.convert';
import { getRandomInt } from './utils/func.general';
import { findAncestor, insertAfter, removeNodes, scrollShadow } from './utils/func.html';
import { CountDownTimer /* , Timer, checkOnlineStatus */ } from './utils/func.process';
import { isElement, isString, isVarEmpty, mergeObjectRecursive } from './utils/func.validate';
import { /* inputDoneTyping, */ eventOn, eventOff, animateCSS } from './utils/func.event';
import iconClose from '../../asset/image/icons/ep_close-bold.svg';
import iconSearch from '../../asset/image/icons/fa_search.svg';
import iconMaximize from '../../asset/image/icons/gg_maximize-alt.svg';
import iconMinimize from '../../asset/image/icons/gg_minimize-alt.svg';
import iconRestricted from '../../asset/image/icons/ri_alarm-warning-fill.svg';
import iconCode from '../../asset/image/icons/mingcute_code-fill.svg';
// const momentParse = require('@eonasdan/tempus-dominus/dist/plugins/moment-parse');
// tempusDominus.extend(momentParse, 'DD-MM-yyyy');

/* == Main script == */
const initAREFormControl = () => {
	const tooltipExisting = Array.from(document.querySelectorAll('div.tooltip.bs-tooltip-auto.show')) || [];
	if (tooltipExisting.length >= 1) {
		tooltipExisting.forEach((perTooltip) => {
			removeNodes(perTooltip);
		});
	}
	const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
	const tooltipList = [...tooltipTriggerList].map((tooltipTriggerEl) => bootstrap.Tooltip.getOrCreateInstance(tooltipTriggerEl));

	// Form Control, hover behaviour
	const formControl = document.getElementsByClassName('form-control');
	for (let i = 0; i < formControl.length; i += 1) {
		const item = formControl[i];
		let parent = null;
		if (item.tagName.toLowerCase() === 'input') {
			parent = findAncestor(item, '.input-group');
			if (!isVarEmpty(parent) && parent.classList.contains('input-group')) {
				const inputGroupText = parent.querySelector('.input-group-text');
				if (document.body.contains(inputGroupText)) {
					parent = findAncestor(item, '.are-input-1');
					if (!isVarEmpty(parent)) {
						if (parent.classList.contains('are-input-1')) {
							item.addEventListener('focus', () => {
								if (document.body.contains(parent.querySelector('.input-group-text img'))) {
									parent.querySelector('.input-group-text img').classList.add('svg-primary');
								}
								parent.querySelector('.input-group-text').classList.add('focused');
							});
							item.addEventListener('focusout', () => {
								if (document.body.contains(parent.querySelector('.input-group-text img'))) {
									parent.querySelector('.input-group-text img').classList.remove('svg-primary');
								}
								parent.querySelector('.input-group-text').classList.remove('focused');
							});
						}
					}
					if (item.hasAttribute('disabled')) {
						inputGroupText.classList.add('bg-gray-200');
					}
				}
			}
		}
	}
	const formMTPInput = Array.from(document.querySelectorAll('.are-input-1')) || [];
	const fromMTPRequired = {
		attachment: [],
	};
	if (formMTPInput.length >= 1) {
		for (let i = 0; i < formMTPInput.length; i += 1) {
			const item = formMTPInput[i];
			// Components: Select-multiple
			const selectMultiple = Array.from(item.querySelectorAll('.select-multiple')) || [];
			if (selectMultiple.length >= 1) {
				selectMultiple.forEach((selectEl) => {
					let isSelectAll = false;
					let selectAllDisabled = false;
					const formValues = selectEl.querySelector('.form-values');
					const formSelect = selectEl.querySelector('.form-select');
					const searchInput = selectEl.querySelector('input.form-control');
					const optionGroup = selectEl.querySelector('.form-option');

					if (document.body.contains(searchInput) && document.body.contains(optionGroup)) {
						const UIOptionToggle = (e, toggle = 'show') => {
							if (isString(toggle)) {
								switch (toggle.toLowerCase()) {
								case 'show':
									if (!optionGroup.classList.contains('show')) {
										const lenOption = Array.from(optionGroup.querySelectorAll('[data-value]') || []).length;
										optionGroup.style.display = 'block';
										if (lenOption === 0) {
											if (!document.body.contains(optionGroup.querySelector('.option-item-empty'))) {
												const elementInfo = document.createElement('p');
												elementInfo.textContent = 'No option available to be selected, is empty!';
												elementInfo.classList.add('option-item-empty', 'fnt-style1', 'text-muted', 'px-3');
												elementInfo.setAttribute('data-weight', 'semibold');
												elementInfo.setAttribute('data-size', 'caption');
												optionGroup.appendChild(elementInfo);
											}
										} else {
											if (!isSelectAll) {
												if (document.body.contains(optionGroup.querySelector('.option-item-empty'))) {
													removeNodes(optionGroup.querySelector('.option-item-empty'));
												}
											}
										}
										setTimeout(() => {
											optionGroup.classList.add('show');
										}, 50);
									}
									break;
								case 'hide':
									if (optionGroup.classList.contains('show')) {
										optionGroup.classList.remove('show');
										setTimeout(() => {
											optionGroup.style.display = 'none';
										}, 300);
									}
									break;
								default:
									break;
								}
							}
							return false;
						};
						const autoHideClickOutside = (event) => {
							const isInside1 = searchInput.contains(event.target);
							const isInside2 = optionGroup.contains(event.target);
							if (!(isInside1 || isInside2)) {
								UIOptionToggle(null, 'hide');
							}
						};
						eventOff('click', searchInput, UIOptionToggle);
						eventOn('click', searchInput, UIOptionToggle.bind(null, event, 'show'));
						eventOff('click', document, autoHideClickOutside, false);
						eventOn('click', document, autoHideClickOutside, false);
						eventOn('keyup', searchInput, (event) => {
							const filterInput = event.target.value;
							let optionValues = Array.from(formValues.querySelectorAll('input[type="hidden"]') || []);
							if (event.key.toLowerCase() === 'backspace') {
								if (filterInput.length === 0) {
									const selectValues = Array.from(formValues.querySelectorAll('input[type="hidden"]') || []);
									const doItemClose = (el) => {
										let itemClose = el;
										let itemInput = null;
										let itemSelectedNew = null;
										itemClose = itemClose.parentNode;
										removeNodes(itemClose.querySelector('button.btn-item-close'));
										if (document.body.contains(itemClose.querySelector('input[type="hidden"]'))) {
											itemInput = itemClose.querySelector('input[type="hidden"]');
											removeNodes(itemClose.querySelector('input[type="hidden"]'));
										}
										itemSelectedNew = document.createElement('li');
										itemSelectedNew.classList.add('option-item');
										itemSelectedNew.setAttribute('data-value', itemInput.value);
										itemSelectedNew.innerHTML = itemClose.innerHTML;
										optionValues = Array.from(formValues.querySelectorAll('input[type="hidden"]') || []);
										if (itemInput.value === 'all') {
											isSelectAll = false;
											if (document.body.contains(optionGroup.querySelector('.option-item-empty'))) {
												removeNodes(optionGroup.querySelector('.option-item-empty'));
											}
										}
										if (optionValues.length >= 1) {
											selectAllDisabled = true;
										} else {
											selectAllDisabled = false;
										}
										Array.from(optionGroup.querySelectorAll('[data-value]') || []).forEach((perOption) => {
											if (isSelectAll) {
												perOption.style.display = 'none';
											} else {
												const optionData = perOption.getAttribute('data-value') || null;
												if (selectAllDisabled) {
													if (!isVarEmpty(optionData) && optionData === 'all') {
														perOption.style.display = 'none';
													} else {
														perOption.style.display = 'block';
													}
												} else {
													perOption.style.display = 'block';
												}
											}
										});
										optionGroup.appendChild(itemSelectedNew);
										removeNodes(itemClose);
									};
									if (selectValues.length >= 1) {
										searchInput.value = selectValues[selectValues.length - 1].parentNode.textContent;
										doItemClose(selectValues[selectValues.length - 1]);
									}
								}
							} else {
								const optionItems = Array.from(optionGroup.querySelectorAll('[data-value]') || []);
								let totalFounds = 0;
								let lastOption = null;

								if (optionValues.length >= 1) {
									selectAllDisabled = true;
								} else {
									selectAllDisabled = false;
								}

								optionItems.forEach((perOption) => {
									const optionContent = perOption.textContent || '';
									const optionData = perOption.getAttribute('data-value') || null;
									if (optionContent.indexOf(filterInput) !== -1) {
										if (isSelectAll) {
											perOption.style.display = 'none';
										} else {
											if (selectAllDisabled) {
												if (!isVarEmpty(optionData) && optionData === 'all') {
													perOption.style.display = 'none';
												} else {
													perOption.style.display = 'block';
													totalFounds += 1;
													lastOption = perOption;
												}
											} else {
												perOption.style.display = 'block';
												totalFounds += 1;
												lastOption = perOption;
											}
										}
									} else {
										perOption.style.display = 'none';
									}
								});
								if (totalFounds === 1 && isElement(lastOption)) {
									let itemValue = null;
									let selectNames = null;
									if (lastOption.hasAttribute('data-value')) {
										itemValue = lastOption.getAttribute('data-value') || null;
										if (itemValue === 'all') {
											isSelectAll = true;
											if (!document.body.contains(optionGroup.querySelector('.option-item-empty'))) {
												const elementInfo = document.createElement('p');
												elementInfo.textContent = 'No option available to be selected, is empty!';
												elementInfo.classList.add('option-item-empty', 'fnt-style1', 'text-muted', 'px-3');
												elementInfo.setAttribute('data-weight', 'semibold');
												elementInfo.setAttribute('data-size', 'caption');
												optionGroup.appendChild(elementInfo);
											}
										}
										removeNodes(lastOption);
										searchInput.value = '';
										selectNames = formSelect.getAttribute('data-name') || null;
										if (!isVarEmpty(selectNames)) {
											selectNames = `name="${selectNames}[]"`;
										} else {
											selectNames = '';
										}
										lastOption = ConvertStringToHTML(`<h5 class="badge bg-primary rounded px-3 py-2 me-2 mb-1 fnt-style1" data-weight="semibold" data-size="caption"><input type="hidden" ${selectNames} value="${itemValue}" class="d-none w-0 h-0"/>${lastOption.innerHTML}<button type="button" class="btn-item-close bg-transparent border-0"><img src="${iconClose}" class="svg-white" width="10px"/></button></h5>`);
										while (lastOption.firstChild) {
											formValues.appendChild(lastOption.firstChild);
										}
										lastOption = formValues.querySelector(
											`input[value="${itemValue}"]`,
										);
										optionValues = Array.from(formValues.querySelectorAll('input[type="hidden"]') || []);
										if (optionValues.length >= 1) {
											selectAllDisabled = true;
										} else {
											selectAllDisabled = false;
										}
										Array.from(optionGroup.querySelectorAll('[data-value]') || []).forEach((perOption) => {
											if (isSelectAll) {
												perOption.style.display = 'none';
											} else {
												const optionData = perOption.getAttribute('data-value') || null;
												if (selectAllDisabled) {
													if (!isVarEmpty(optionData) && optionData === 'all') {
														perOption.style.display = 'none';
													} else {
														perOption.style.display = 'block';
													}
												} else {
													perOption.style.display = 'block';
												}
											}
										});
										if (document.body.contains(lastOption)) {
											const btnItemClose = lastOption.parentNode.querySelector(
												'button.btn-item-close',
											);
											if (document.body.contains(btnItemClose)) {
												const doItemClose = (ev) => {
													let itemClose = ev.currentTarget;
													let itemInput = null;
													let lastOptionNew = null;
													itemClose = itemClose.parentNode;
													removeNodes(itemClose.querySelector('button.btn-item-close'));
													if (document.body.contains(itemClose.querySelector('input[type="hidden"]'))) {
														itemInput = itemClose.querySelector('input[type="hidden"]');
														removeNodes(itemClose.querySelector('input[type="hidden"]'));
													}
													lastOptionNew = document.createElement('li');
													lastOptionNew.classList.add('option-item');
													lastOptionNew.setAttribute('data-value', itemInput.value);
													if (itemInput.value === 'all') {
														isSelectAll = false;
														if (document.body.contains(optionGroup.querySelector('.option-item-empty'))) {
															removeNodes(optionGroup.querySelector('.option-item-empty'));
														}
													}
													optionValues = Array.from(formValues.querySelectorAll('input[type="hidden"]') || []);
													if (optionValues.length >= 1) {
														selectAllDisabled = true;
													} else {
														selectAllDisabled = false;
													}
													Array.from(optionGroup.querySelectorAll('[data-value]') || []).forEach((perOption) => {
														if (isSelectAll) {
															perOption.style.display = 'none';
														} else {
															const optionData = perOption.getAttribute('data-value') || null;
															if (selectAllDisabled) {
																if (!isVarEmpty(optionData) && optionData === 'all') {
																	perOption.style.display = 'none';
																} else {
																	perOption.style.display = 'block';
																}
															} else {
																perOption.style.display = 'block';
															}
														}
													});
													lastOptionNew.innerHTML = itemClose.innerHTML;
													optionGroup.appendChild(lastOptionNew);
													removeNodes(itemClose);
													eventOff('click', btnItemClose, doItemClose);
												};
												eventOn('click', btnItemClose, doItemClose);
											}
										}
									}
								}
							}
						});

						optionGroup.addEventListener('click', (e) => {
							let itemSelected = e.target;
							let itemValue = null;
							let selectNames = null;
							if (itemSelected.hasAttribute('data-value')) {
								itemValue = itemSelected.getAttribute('data-value') || null;
								if (itemValue === 'all') {
									isSelectAll = true;
									if (!document.body.contains(optionGroup.querySelector('.option-item-empty'))) {
										const elementInfo = document.createElement('p');
										elementInfo.textContent = 'No option available to be selected, is empty!';
										elementInfo.classList.add('option-item-empty', 'fnt-style1', 'text-muted', 'px-3');
										elementInfo.setAttribute('data-weight', 'semibold');
										elementInfo.setAttribute('data-size', 'caption');
										optionGroup.appendChild(elementInfo);
									}
								}
								removeNodes(itemSelected);
								searchInput.value = '';
								selectNames = formSelect.getAttribute('data-name') || null;
								if (!isVarEmpty(selectNames)) {
									selectNames = `name="${selectNames}[]"`;
								} else {
									selectNames = '';
								}
								itemSelected = ConvertStringToHTML(
									`<h5 class="badge bg-primary rounded px-3 py-2 me-2 mb-1 fnt-style1" data-weight="semibold" data-size="caption"><input type="hidden" ${selectNames} value="${itemValue}" class="d-none w-0 h-0"/>${itemSelected.innerHTML}<button type="button" class="btn-item-close bg-transparent border-0"><img src="${iconClose}" class="svg-white" width="10px"/></button></h5>`,
								);
								while (itemSelected.firstChild) {
									formValues.appendChild(itemSelected.firstChild);
								}
								itemSelected = formValues.querySelector(
									`input[value="${itemValue}"]`,
								);
								let optionValues = Array.from(formValues.querySelectorAll('input[type="hidden"]') || []);
								if (optionValues.length >= 1) {
									selectAllDisabled = true;
								} else {
									selectAllDisabled = false;
								}
								Array.from(optionGroup.querySelectorAll('[data-value]') || []).forEach((perOption) => {
									if (isSelectAll) {
										perOption.style.display = 'none';
									} else {
										const optionData = perOption.getAttribute('data-value') || null;
										if (selectAllDisabled) {
											if (!isVarEmpty(optionData) && optionData === 'all') {
												perOption.style.display = 'none';
											} else {
												perOption.style.display = 'block';
											}
										} else {
											perOption.style.display = 'block';
										}
									}
								});
								if (document.body.contains(itemSelected)) {
									const btnItemClose = itemSelected.parentNode.querySelector('button.btn-item-close');
									if (document.body.contains(btnItemClose)) {
										const doItemClose = (ev) => {
											let itemClose = ev.currentTarget;
											let itemInput = null;
											let itemSelectedNew = null;
											itemClose = itemClose.parentNode;
											removeNodes(itemClose.querySelector('button.btn-item-close'));
											if (document.body.contains(itemClose.querySelector('input[type="hidden"]'))) {
												itemInput = itemClose.querySelector('input[type="hidden"]');
												removeNodes(itemClose.querySelector('input[type="hidden"]'));
											}
											itemSelectedNew = document.createElement('li');
											itemSelectedNew.classList.add('option-item');
											itemSelectedNew.setAttribute('data-value', itemInput.value);
											itemSelectedNew.innerHTML = itemClose.innerHTML;
											if (itemInput.value === 'all') {
												isSelectAll = false;
												if (document.body.contains(optionGroup.querySelector('.option-item-empty'))) {
													removeNodes(optionGroup.querySelector('.option-item-empty'));
												}
											}
											optionValues = Array.from(formValues.querySelectorAll('input[type="hidden"]') || []);
											if (optionValues.length >= 1) {
												selectAllDisabled = true;
											} else {
												selectAllDisabled = false;
											}
											Array.from(optionGroup.querySelectorAll('[data-value]') || []).forEach((perOption) => {
												if (isSelectAll) {
													perOption.style.display = 'none';
												} else {
													const optionData = perOption.getAttribute('data-value') || null;
													if (selectAllDisabled) {
														if (!isVarEmpty(optionData) && optionData === 'all') {
															perOption.style.display = 'none';
														} else {
															perOption.style.display = 'block';
														}
													} else {
														perOption.style.display = 'block';
													}
												}
											});
											optionGroup.appendChild(itemSelectedNew);
											removeNodes(itemClose);
											eventOff('click', btnItemClose, doItemClose);
										};
										eventOn('click', btnItemClose, doItemClose);
									}
								}
								// formValues.
							}
						});
					}
				});
			}
			// Components: Attachment-dragdrop
			const attachmentDD = Array.from(item.querySelectorAll('.drop-file')) || [];
			if (attachmentDD.length >= 1) {
				attachmentDD.forEach((attachmentEl) => {
					const dropMaxFiles = 1;
					const dropElements = {
						dropTitle: attachmentEl.querySelector('.drop-title'),
						dropIsRequired: attachmentEl.querySelector('.drop-is-required'),
						dropFileInput: attachmentEl.querySelector('.drop-file-input'),
						dropAcceptExt: attachmentEl.querySelector('.drop-accept-ext'),
						dropMaxSize: attachmentEl.querySelector('.drop-max-size'),
						dropChooseFile: attachmentEl.querySelector('.drop-choose-file'),
					};
					const inputFile = dropElements.dropChooseFile.querySelector('input[type="file"]');
					const maxFileSize = process.env.APP.REGISTER_MAX_FILESIZE;
					let allowedExtension = [];
					const preventDefaults = (e) => {
						e.preventDefault();
						e.stopPropagation();
					};
					const highlight = (e) => {
						const el = e.currentTarget;
						el.classList.add('highlight');
					};
					const unhighlight = (e) => {
						const el = e.currentTarget;
						el.classList.remove('highlight');
					};
					const handleFiles = (files) => {
						let loopLength = 0;
						const dT = new DataTransfer();
						dropElements.dropFileInput.innerHTML = 'File input: -';
						if (document.body.contains(inputFile)) {
							inputFile.files = dT.files;
							inputFile.value = null;
						}
						([...files]).every((file) => {
							if (loopLength >= dropMaxFiles) {
								return false;
							}
							const inspectFile = file;
							const fileName = inspectFile.name.replace(/^.*[\\/]/, '');
							const fileExt = fileName.slice((Math.max(0, fileName.lastIndexOf('.')) || Infinity) + 1);
							const fileSize = inspectFile.size / 1000; // Convert Bytes to Kilobytes

							// Check file Extension
							if (allowedExtension.length >= 1) {
								if (!allowedExtension.includes(fileExt)) {
									alert(`Sorry invalid Attachment extension!\nOnly accept: ${allowedExtension.join(', ')}`);
									return true;
								}
							}
							// Check file Size
							if (fileSize >= maxFileSize) {
								alert(`Sorry the attached file size exceeds limit, please input a smaller size!\Max file-size: ${Number((maxFileSize / 1000).toFixed(1))} Megabytes`);
								return true;
							}

							dropElements.dropFileInput.innerHTML = `File input: <b>${inspectFile.name}</b>`;
							if (document.body.contains(inputFile)) {
								dT.items.add(file);
							}
							loopLength += 1;
							return true;
						});

						if (document.body.contains(inputFile)) {
							inputFile.files = dT.files;
							dropElements.dropChooseFile.querySelector('.btn').classList.remove('btn-dark', 'btn-primary');
							if (dT.files.length >= 1) {
								attachmentEl.classList.add('selected');
								dropElements.dropChooseFile.querySelector('p').textContent = 'Change file';
								dropElements.dropChooseFile.querySelector('.btn').classList.add('btn-dark');
							} else {
								attachmentEl.classList.remove('selected');
								dropElements.dropChooseFile.querySelector('p').textContent = 'Choose file';
								dropElements.dropChooseFile.querySelector('.btn').classList.add('btn-primary');
							}
						}
					};
					const handleDrop = (e) => {
						const dt = e.dataTransfer;
						const { files } = dt;
						handleFiles(files);
					};
					['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
						eventOff(eventName, attachmentEl, preventDefaults, false);
						eventOn(eventName, attachmentEl, preventDefaults, false);
					});
					['dragenter', 'dragover'].forEach((eventName) => {
						eventOff(eventName, attachmentEl, highlight, false);
						eventOn(eventName, attachmentEl, highlight, false);
					});
					['dragleave', 'drop'].forEach((eventName) => {
						eventOff(eventName, attachmentEl, unhighlight, false);
						eventOn(eventName, attachmentEl, unhighlight, false);
					});

					// Drag and Drop files
					eventOff('drop', attachmentEl, handleDrop, false);
					eventOn('drop', attachmentEl, handleDrop, false);

					// Manual select files
					if (document.body.contains(inputFile)) {
						let isRequired = 'Yes';
						if (inputFile.hasAttribute('data-required')) {
							isRequired = (String(inputFile.getAttribute('data-required')).toLowerCase().trim() === 'true') ? 'Yes' : 'No';
							if (String(isRequired).toLowerCase().trim() === 'yes' && !fromMTPRequired.attachment.includes(inputFile.getAttribute('name'))) {
								fromMTPRequired.attachment.push(inputFile.getAttribute('name'));
							}
						} else {
							if (fromMTPRequired.attachment.length >= 1) {
								if (fromMTPRequired.attachment.includes(inputFile.getAttribute('name'))) {
									isRequired = 'Yes';
								} else {
									isRequired = 'No';
								}
							} else {
								isRequired = 'Yes';
							}
						}
						if (String(isRequired).toLowerCase().trim() === 'yes' && !fromMTPRequired.attachment.includes(inputFile.getAttribute('name'))) {
							fromMTPRequired.attachment.push(inputFile.getAttribute('name'));
						}
						dropElements.dropIsRequired.innerHTML = `Required: <b>${isRequired}</b>`;

						if (inputFile.hasAttribute('accept')) {
							allowedExtension = String(inputFile.getAttribute('accept')).split(',');
							allowedExtension = allowedExtension.map((str) => str.replace(/[^a-zA-Z]/g, ''));
							dropElements.dropAcceptExt.innerHTML = `Accept extension: <b>${allowedExtension.map((str) => `.${str}`).join(', ')}</b>`;
						}
						dropElements.dropMaxSize.innerHTML = `Max size: <b>${Number((maxFileSize / 1000).toFixed(1))} Megabytes</b>`;

						const doHandleFiles = (e) => {
							handleFiles(e.currentTarget.files);
						};
						eventOff('change', inputFile, doHandleFiles, false);
						eventOn('change', inputFile, doHandleFiles, false);
					}
				});
			}
			// Components: Switch-toggle
			const switchToggle = Array.from(item.querySelectorAll('.switch-toggle')) || [];
			if (switchToggle.length >= 1) {
				switchToggle.forEach((switchEl) => {
					let selectedIndex = null;
					let inputName = null;
					const switcherToggler = switchEl.querySelector('.switcher-toggle');
					const listOption = Array.from(switchEl.querySelectorAll('.switcher-input')) || [];
					const doSwitchAction = (e) => {
						if (selectedIndex !== null) {
							listOption.forEach((perOption) => {
								perOption.checked = false;
								perOption.removeAttribute('checked');
							});
							selectedIndex = listOption.indexOf(e.currentTarget);
							const switchBoundRect = switchEl.getBoundingClientRect();
							let selectedOption = listOption[selectedIndex];
							let selectedBoundRect = null;
							let selectedPosition = { top: 0, right: 0, bottom: 0, left: 0 };
							if (document.body.contains(switcherToggler) && document.body.contains(selectedOption)) {
								selectedOption.checked = true;
								selectedOption.setAttribute('checked', 'true');
								selectedOption = switchEl.querySelector(`label[for="${selectedOption.getAttribute('id')}"]`);
								selectedBoundRect = selectedOption.getBoundingClientRect();
								selectedPosition = {
									top: selectedBoundRect.top - switchBoundRect.top,
									right: selectedBoundRect.right - switchBoundRect.right,
									bottom: selectedBoundRect.bottom - switchBoundRect.bottom,
									left: selectedBoundRect.left - switchBoundRect.left,
								};
								if (document.body.contains(selectedOption)) {
									setTimeout(() => {
										switcherToggler.setAttribute('style', `width: ${selectedOption.offsetWidth}px; left: ${selectedPosition.left}px;`);
									}, 100);
								}
							}
						}
					};
					if (listOption.length >= 1) {
						listOption.forEach((perOption, idx) => {
							const optionType = String(perOption.getAttribute('type')).toLowerCase() || null;
							const optionName = perOption.getAttribute('name') || null;
							if (optionType !== 'radio' || !perOption.hasAttribute('type')) {
								perOption.setAttribute('type', 'radio');
							}
							if (inputName === null && optionName !== null) {
								inputName = optionName;
							}
							if (selectedIndex === null && perOption.hasAttribute('checked')) {
								selectedIndex = idx;
							}

							eventOff('click', perOption, doSwitchAction);
							eventOn('click', perOption, doSwitchAction);
						});
						if (inputName !== null) {
							// Fallback to Fix, tag Name
							listOption.forEach((perOption) => {
								const optionName = perOption.getAttribute('name') || null;
								if (inputName !== null && (optionName === null || !perOption.hasAttribute('name') || optionName !== inputName)) {
									perOption.setAttribute('name', inputName);
								}
							});
						}
					}
					if (selectedIndex !== null) {
						let selectedOption = listOption[selectedIndex];
						let selectedBoundRect = null;
						let selectedPosition = null;
						if (document.body.contains(switcherToggler) && document.body.contains(selectedOption)) {
							selectedOption = switchEl.querySelector(`label[for="${selectedOption.getAttribute('id')}"]`);
							if (document.body.contains(selectedOption)) {
								setTimeout(() => {
									const switchBoundRect = switchEl.getBoundingClientRect();
									selectedBoundRect = selectedOption.getBoundingClientRect();
									selectedPosition = {
										top: selectedBoundRect.top - switchBoundRect.top,
										right: selectedBoundRect.right - switchBoundRect.right,
										bottom: selectedBoundRect.bottom - switchBoundRect.bottom,
										left: selectedBoundRect.left - switchBoundRect.left,
									};
									switcherToggler.setAttribute('style', `width: ${selectedOption.offsetWidth}px; left: ${selectedPosition.left}px;`);
								}, 100);
							}
						}
					}
				});
			}
		}
	}
};

// DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
	// Init fontawesome
	fontawesome();

	// Init custom form-control behaviour
	initAREFormControl();

	// Smooth scrollbar
	Scrollbar.use(ModalPlugin, HorizontalScrollPlugin, DisableScrollPlugin, OverscrollPlugin);
	Scrollbar.initAll({
		alwaysShowTracks: true,
		damping: 0.15,
		continuousScrolling: false,
		plugins: { overscroll: { effect: 'bounce' }, modal: { open: false }, horizontalScroll: { enabled: false } },
	});

	if (document.body.contains(document.querySelector('.content-body'))) {
		const contentScroll = Scrollbar.get(document.querySelector('.content-body'));
		contentScroll.updatePluginOptions('disableScroll', { direction: 'x' });
		contentScroll.track.xAxis.element.remove();
	}

	// Shadow Overflow
	const shadowOverflow = Array.from(document.getElementsByClassName('shadow-scroll')) || [];
	shadowOverflow.forEach((el) => {
		scrollShadow.init(el);
	});

	// Enable Bootstrap 5 Tooltip features
	const myDefaultAllowList = bootstrap.Tooltip.Default.allowList;
	myDefaultAllowList.h6 = ['data-weight', 'style'];
	myDefaultAllowList.p = ['data-weight', 'style'];
	myDefaultAllowList.img = ['src', 'class', 'style'];
	const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
	const tooltipList = [...tooltipTriggerList].map((tooltipTriggerEl) => bootstrap.Tooltip.getOrCreateInstance(tooltipTriggerEl));

	// Add modal 'show-immediately' class
	const allModals = Array.from(document.getElementsByClassName('modal'));
	const newModals = [];
	const optModalsDefault = { backdrop: true, focus: true, keyboard: true };
	if (document.body.contains(allModals[0])) {
		allModals.forEach((item) => {
			let optModalsCondition = {};
			if (item.classList.contains('is-static') || (item.hasAttribute('data-bs-backdrop') && String(item.getAttribute('data-bs-backdrop')).toLowerCase() === 'static')) {
				optModalsCondition = {
					backdrop: 'static',
					keyboard: false,
				};
			}
			newModals.push(new bootstrap.Modal(item, mergeObjectRecursive(optModalsDefault, optModalsCondition)));
			// reorderModal(item);
			if (item.classList.contains('show-immediately')) {
				const instanceModal = bootstrap.Modal.getInstance(item);
				instanceModal.show();
			}
		});
	}

	// Enable Bootstrap 5 Collapse features
	const collapseElementList = document.querySelectorAll('.collapse');
	// const collapseList = [...collapseElementList].map((collapseEl) => new bootstrap.Collapse(collapseEl, { toggle: false }));
	[...collapseElementList].map((collapseEl) => new bootstrap.Collapse(collapseEl, { toggle: false }));

	// Datatable
	$('.datatable').DataTable({
		// <"top-control row no-gutters g-0 justify-content-between mb-3 w-100"<"col-auto d-flex align-items-center" l><"col-12 d-flex justify-content-end"f>>
		dom: '<"top-control row g-0 justify-content-between"<"col-12 mb-2 text-dark p-0"f>><"table-wrapper"t><"bottom-control d-flex flex-column flex-lg-row justify-content-center justify-content-lg-between align-items-center mt-2"<"dt-entries" l><"dt-total"i><"dt-pagination"p>>',
		language: {
			search: '',
		},
		// initComplete: (setting, json) => {
		initComplete: (setting) => {
			// <div class="form-group are-input-1">
			// 	<div class="input-group mb-3 has-validation">
			// 		<span class="input-group-text"><img src="./asset/image/icons/fluent_city-24-filled.svg" alt="Icons Company Name" width="24px" height="24px" /></span>
			// 		<div class="form-floating">
			// 			<input type="text" name="customer-name" class="form-control" id="inputCompanyName" placeholder="Enter company Name" />
			// 			<label for="inputCompanyName">Enter company Name</label>
			// 		</div>
			// 	</div>
			// </div>
			const idInput = getRandomInt(0, 9999);
			const inputWrapper = document.createElement('div');
			inputWrapper.classList.add('form-group', 'are-input-1');

			const inputGroup = document.createElement('div');
			inputGroup.classList.add('input-group');
			inputWrapper.appendChild(inputGroup);

			const inputGroupText = document.createElement('span');
			inputGroupText.classList.add('input-group-text');
			inputGroup.appendChild(inputGroupText);

			const inputIcon = document.createElement('img');
			inputIcon.src = iconSearch;
			inputIcon.alt = 'Search Icons';
			inputIcon.width = 16;
			inputIcon.height = 16;
			inputGroupText.appendChild(inputIcon);

			const inputFloat = document.createElement('div');
			inputFloat.classList.add('form-floating');
			inputGroup.appendChild(inputFloat);

			const inputLabel = document.createElement('label');
			inputLabel.setAttribute('for', `searchInput_${idInput}`);
			inputLabel.textContent = 'Search';

			const inputSearch = setting.nTableWrapper.querySelector('.dataTables_filter').querySelector('input[type="search"]');
			inputSearch.style.transition = 'all 300ms ease-in-out';
			inputSearch.classList.add('w-100', 'ms-0', 'ml-0', 'form-control');
			inputSearch.setAttribute('id', `searchInput_${idInput}`);
			inputSearch.setAttribute('placeholder', 'Search');
			// inputSearch.addEventListener('focus', () => {
			// 	inputSearch.style.borderColor = 'rgba(0, 0, 0, 1)';
			// });
			// inputSearch.addEventListener('focusout', () => {
			// 	inputSearch.style.borderColor = 'rgba(0, 0, 0, 0)';
			// });

			inputFloat.appendChild(inputSearch);
			inputFloat.appendChild(inputLabel);

			setting.nTableWrapper.querySelector('.dataTables_filter').appendChild(inputWrapper);
			removeNodes(setting.nTableWrapper.querySelector('.dataTables_filter').querySelector('label'));
			document.dispatchEvent(new Event('AREDOMInserted'));
		},
	});

	// Navbar Behaviour
	// TODO: Notification client
	const navbarTop = document.querySelector('.top-navbar');
	if (document.body.contains(navbarTop)) {
		/*
		const navNotification = navbarTop.querySelector('.navbar-notification');
		if (document.body.contains(navNotification)) {
			let timerFetchNotifications = null;
			const fetchNotifications = () => {
				const requestAPI = new FormData();
				requestAPI.append('ajax', true);
				requestAPI.append('action', 'fetch-notifications');
				axios.post(
					`${hostURL}/app/includes/accounts.inc.php`,
					requestAPI,
					{
						headers: { 'Content-Type': 'multipart/form-data' },
					},
				).then(({ data: res }) => {
					if (res.success) {
						sessionStore.set(`${process.env.APP.PREFIX}notifications`, 60);
						timerFetchNotifications.reset(60);
						timerFetchNotifications.start();
					}
				}).catch((err) => console.error(err));
			};
			let dataNotifications = sessionStore.get(`${process.env.APP.PREFIX}notifications`) || null;
			if (isVarEmpty(dataNotifications)) {
				dataNotifications = 60;
			}
			timerFetchNotifications = new CountDownTimer(dataNotifications);
			timerFetchNotifications.onTick((minutes, seconds, raw) => {
				dataNotifications = raw;
				sessionStore.set(`${process.env.APP.PREFIX}notifications`, dataNotifications);
				if (raw === 0) {
					fetchNotifications();
					timerFetchNotifications.kill();
				}
			});
			timerFetchNotifications.start();

			const wrapperHTML = `
				<section class="navbar-notification-wrapper animate__faster d-none" style="opacity: 0;">
					<div class="navbar-nw-head">
						<div class="row align-items-center justify-content-between gx-0 mb-3">
							<div class="col-auto">
								<h6 class="fnt-style1 text-dark" data-weight="semibold">Notifications</h6>
							</div>
							<div class="col-auto">
								<button type="button" class="btn btn-mark-all btn-link">
									Mark all as read
								</button>
							</div>
						</div>
						<ul class="nav nav-tabs">
							<li class="nav-item">
								<button type="btn" class="btn nav-link active" data-filter="all">All</button>
							</li>
							<li class="nav-item">
								<button type="btn" class="btn nav-link" data-filter="important">Important</button>
							</li>
							<li class="nav-item">
								<button type="btn" class="btn nav-link" data-filter="subcribe">Subscribe</button>
							</li>
							<li class="nav-item">
								<button type="btn" class="btn nav-link" data-filter="archive">Archive</button>
							</li>
							<li class="nav-item">
								<button type="btn" class="btn nav-link" data-filter="news">News</button>
							</li>
						</ul>
					</div>
					<div class="navbar-nw-body"></div>
				</section>
			`;
			const btnNotif = navNotification.querySelector('.btn-notification');
			if (document.body.contains(btnNotif)) {
				const toggleNotificationMenu = () => {
					let notificationWrapper = navNotification.querySelector('.navbar-notification-wrapper');
					if (document.body.contains(notificationWrapper)) {
						animateCSS(notificationWrapper, 'fadeOutDownCustom').then(() => {
							removeNodes(notificationWrapper);
						});
					} else {
						const html2Node = ConvertStringToHTML(wrapperHTML);
						while (html2Node.firstChild) {
							const firstChildElement = html2Node.firstChild;
							insertAfter(firstChildElement, btnNotif);
						}
						notificationWrapper = navNotification.querySelector('.navbar-notification-wrapper');
						if (document.body.contains(notificationWrapper)) {
							notificationWrapper.classList.remove('d-none');
							animateCSS(notificationWrapper, 'fadeInUpCustom').then(() => {
								notificationWrapper.removeAttribute('style');
							});
						}
					}
				};
				eventOn('click', btnNotif, toggleNotificationMenu);
			}
		}
		*/
		// /*
		const elementNotifications = document.getElementById('userNotification');
		if (document.body.contains(elementNotifications)) {
			// console.log(elementNotifications);
			const userNotification = new Notification(elementNotifications, {
				remote: {
					host: hostURL,
					api: {
						push: 'app/includes/features/notification.feat.php',
						pull: 'app/includes/features/notification.feat.php',
					},
				},
				widget: {
					id: 'userNotification',
				},
			});
			userNotification.draw();
		}
		// */
	}
	const navbarSide = document.querySelector('.side-navbar');
	if (document.body.contains(navbarSide)) {
		const defaultAllowList = bootstrap.Tooltip.Default.allowList;
		defaultAllowList.p = ['class', 'data-weight', 'data-size'];
		defaultAllowList.img = ['width', 'height', 'src', 'class'];

		const menuRestricted = Array.from(navbarSide.querySelectorAll('li.is-restricted')) || [];
		if (menuRestricted.length >= 1) {
			menuRestricted.forEach((perMenu) => {
				const tooltipInstance = bootstrap.Tooltip.getOrCreateInstance(perMenu, {
					html: true,
					placement: 'right',
					title: `<p class="fnt-style1 center-content" data-weight="semibold" data-size="caption"><img src="${iconRestricted}" class="svg-white mr-1 me-1" width="20px" /> Anda tidak mempunyai akses ke fitur ini.</p>`,
					customClass: 'danger-tooltip',
				});
				perMenu.setAttribute('style', 'cursor:not-allowed !important;');
				const linkElement = perMenu.querySelector('a');
				eventOn('click', linkElement, (e) => {
					e.preventDefault();
				});
			});
		}

		const menuUnderconstruction = Array.from(navbarSide.querySelectorAll('li.under-construction')) || [];
		if (menuUnderconstruction.length >= 1) {
			menuUnderconstruction.forEach((perMenu) => {
				const tooltipInstance = bootstrap.Tooltip.getOrCreateInstance(perMenu, {
					html: true,
					placement: 'right',
					title: `<p class="fnt-style1 center-content" data-weight="semibold" data-size="caption"><img src="${iconCode}" class="svg-white mr-1 me-1" width="20px" /> Fitur dalam tahap pengembangan!</p>`,
					customClass: 'orange-tooltip',
				});
				perMenu.setAttribute('style', 'cursor:not-allowed !important;');
				const linkElement = perMenu.querySelector('a');
				eventOn('click', linkElement, (e) => {
					e.preventDefault();
				});
			});
		}
	}

	// Check in-activity/idle and refresh when user has activity
	// const idleSecond = 30; // 30 seconds
	// let idleTime = 0; // 5
	// const timerIncrement = () => { idleTime += 1; };
	// const idleInterval = setInterval(timerIncrement, (idleSecond * 1000));

	// // Zero the idle timer on users activity
	// const eventsReset = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
	// eventsReset.forEach((name) => {
	// 	document.addEventListener(name, () => {
	// 		if (idleTime >= 1) {
	// 			const requestAPI = new FormData();
	// 			requestAPI.append('ajax', true);
	// 			requestAPI.append('action', 'reset-activity');
	// 			axios.post(
	// 				`${hostURL}/app/includes/check-activity.inc.php`,
	// 				requestAPI,
	// 				{
	// 					headers: { 'Content-Type': 'multipart/form-data' },
	// 				},
	// 			).then(({ data: res }) => {
	// 				if (res.success) {
	// 					// console.log(res);
	// 				}
	// 			}).catch((err) => console.error(err));
	// 			idleTime = 0;
	// 		}
	// 	}, true);
	// });
});

document.addEventListener('AREDOMInserted', () => {
	// Init custom form-control behaviour
	initAREFormControl();
}, false);
