/* eslint-disable no-underscore-dangle */
/* eslint-disable guard-for-in */
/* eslint-disable no-lonely-if */
/* eslint-disable no-param-reassign */
/* eslint-disable max-len */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* eslint no-undef: "error" */
/* global axios, bootstrap, Chart, ChartDataLabels, Scrollbar, Values, lottie */

/* == Module import == */
import 'Styles/dashboard.scss';
import VanillaCalendar from '@uvarov.frontend/vanilla-calendar';
import '@uvarov.frontend/vanilla-calendar/build/vanilla-calendar.min.css';
import { isElement, isObject, isVarEmpty } from './utils/func.validate';
import { removeAllChildNodes, removeNodes } from './utils/func.html';
import { ConvertStringToHTML } from './utils/func.convert';
import { eventOn } from './utils/func.event';
import iconFilter from '../../asset/image/icons/mdi_filter.svg';
import iconClose from '../../asset/image/icons/ep_close-bold.svg';
import iconAdd from '../../asset/image/icons/mingcute_add-fill.svg';
import iconEdit from '../../asset/image/icons/clarity_pencil-solid.svg';
import iconDelete from '../../asset/image/icons/ph_trash-fill.svg';
import iconModal from '../../asset/image/icons/fluent_window-new-16-filled.svg';
import iconDesc from '../../asset/image/icons/fluent_text-description-16-filled.svg';
import iconTask from '../../asset/image/icons/ri_task-fill.svg';
import { isEmpty } from 'validator';

/* == Main script == */
// DOMContentLoaded
document.addEventListener('DOMContentLoaded', async () => {
	const eventTriggers = {
		listCreate: async () => {
			const nameValue = prompt("Please enter a new List title", "");
			if (!isVarEmpty(nameValue)) {
				if (nameValue.trim().length >= 5) {
					// Request API
					const requestAPI = new FormData();
					requestAPI.append('ajax', true);
					requestAPI.append('action', 'list-create');
					requestAPI.append('data', JSON.stringify({ listTitle: nameValue.trim() }));
					const res = await axios.post('app/includes/todo.inc.php', requestAPI, { headers: { 'Content-Type': 'multipart/form-data' } }).then(({ data: res }) => (res)).catch((err) => console.error(err));
					if (isObject(res) && Object.prototype.hasOwnProperty.call(res, 'success')) {
						if (res.success) {
							window.location.reload();
						}
					}
				} else {
					if (confirm('Minimum 5 characters, try again?')) {
						eventTriggers.listCreate();
					}
				}
			} else {
				if (confirm('List name must be filled, try again?')) {
					eventTriggers.listCreate();
				}
			}
		},
		cardCreate: async (idList, title) => {
			// Create Data
			const requestAPI = new FormData();
			requestAPI.append('ajax', true);
			requestAPI.append('action', 'list-add-card');
			requestAPI.append('data', JSON.stringify({ listID: idList, cardTitle: title }));
			const output = await axios.post('app/includes/todo.inc.php', requestAPI, { headers: { 'Content-Type': 'multipart/form-data' } }).then(({ data: res }) => (res)).catch((err) => console.error(err));
			return output;
		},
		cardOpen: async (id, evt) => {
			// Request API
			const requestAPI = new FormData();
			requestAPI.append('ajax', true);
			requestAPI.append('action', 'card-details');
			requestAPI.append('data', JSON.stringify({ cardID: id }));
			const res = await axios.post('app/includes/todo.inc.php', requestAPI, { headers: { 'Content-Type': 'multipart/form-data' } }).then(({ data: res }) => (res)).catch((err) => console.error(err));
			if (isObject(res) && Object.prototype.hasOwnProperty.call(res, 'success')) {
				if (res.success) {
					const { data } = res;
					const elementString = `
						<div class="modal modal-cards fade" id="modal-${data.id}" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
							<div class="modal-dialog modal-lg">
								<div class="modal-content">
									<div class="modal-header">
										<div class="header-wrapper">
											<div class="d-flex align-items-center mb-1">
												<img src="${iconModal}" alt="Icons Modal" class="modal-icon" />
												<h1 class="modal-title">
													${data.name}
												</h1>
											</div>
											<p class="modal-subtitle">in list of <b>${data.parent.name ?? '-'}</b></p>
										</div>
										<button type="button" class="btn-modal-close" data-bs-dismiss="modal" aria-label="Close">
											<img src="${iconClose}" alt="Icons Close"/>
										</button>
									</div>
									<div class="modal-body">
										<section class="card-detail-data row g-3">
											<div class="col-auto">
												<div class="card-detail-item is-due-date">
													<label class="card-detail-label">Due dates</label>
													<input type="text" class="form-control form-control-sm input-due-date" value="${!isVarEmpty(data.dueDate) ? data.dueDate : ''}" placeholder="Enter date here...">
												</div>
											</div>
											<div class="col-auto">
												<div class="card-detail-item is-priority w-120px">
													<label class="card-detail-label">Priority</label>
													<div class="dropdown w-100">
														<button class="btn w-100 d-flex align-items-center justify-content-between btn-sm btn-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Low</button>
														<ul class="dropdown-menu">
															<li><button type="button" class="dropdown-item" data-value="low">Low</button></li>
															<li><button type="button" class="dropdown-item" data-value="medium">Medium</button></li>
															<li><button type="button" class="dropdown-item" data-value="high">High</button></li>
														</ul>
													</div>
												</div>
											</div>
											<div class="col-auto">
												<div class="card-detail-item is-status w-120px">
													<label class="card-detail-label">Status</label>
													<div class="dropdown w-100">
														<button class="btn w-100 d-flex align-items-center justify-content-between btn-sm btn-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">In-complete</button>
														<ul class="dropdown-menu">
															<li><button type="button" class="dropdown-item" data-value="complete">Complete</button></li>
															<li><button type="button" class="dropdown-item" data-value="in_complete">In-complete</button></li>
														</ul>
													</div>
												</div>
											</div>
											<div class="col-auto">
												<div class="card-detail-item is-progress">
													<label class="card-detail-label">Progress completed</label>
													<div class="d-flex flex-column">
														<div class="progress" style="height: 6px;">
															<div class="progress-bar bg-success" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
														</div>
														<p class="fnt-style1 text-white mt-2" data-size="caption">3 of 100 (80%)</p>
													</div>
												</div>
											</div>
										</section>
										<section class="card-desc mb-3">
											<div class="card-desc-label">
												<img src="${iconDesc}" alt="Icons Description" class="card-desc-icon" />
												<h1 class="card-desc-title">Description</h1>
											</div>
											<div class="card-desc-form">
												<textarea class="card-desc-input d-none" placeholder="Add more description..."></textarea>
												<div class="card-desc-blank">Add more description...</div>
												<button type="button" class="btn btn-desc-save d-none">Save</button>
											</div>
										</section>
										<section class="card-task">
											<div class="card-task-label">
												<img src="${iconTask}" alt="Icons Task" class="card-task-icon" />
												<h1 class="card-task-title">Tasks</h1>
											</div>
											<div class="card-task-form">
												
											</div>
											<button type="button" class="btn btn-add-task">Add an task</button>
										</section>
									</div>
									<div class="modal-footer">
									</div>
								</div>
							</div>
						</div>
					`;
					const html2Node = ConvertStringToHTML(elementString);
					while (html2Node.firstChild) { document.body.appendChild(html2Node.firstChild); }

					// Check element List-item exist
					const elementModal = document.getElementById(`modal-${data.id}`);
					if (isElement(elementModal)) {
						const modalInstance = bootstrap.Modal.getOrCreateInstance(elementModal, {
							backdrop: 'static',
							keyboard: false,
							focus: true,
						});
						if (isObject(modalInstance)) {
							eventOn('shown.bs.modal', elementModal, () => {
								// Fill out Due-dates
								const elementDueDates = elementModal.querySelector('.input-due-date');
								if (isElement(elementDueDates)) {
									const calendarDueDates = new VanillaCalendar(elementDueDates, {
										input: true,
										actions: {
											changeToInput(e, self, dates) {
												console.log(e);
												if (!self.HTMLInputElement) return;
												if (dates) {
													self.HTMLInputElement.value = dates;
													self.hide();
												} else {
													self.HTMLInputElement.value = '';
												}
											},
										},
										settings: {
											visibility: {
												positionToInput: 'center',
											},
										},
									});
									calendarDueDates.init();
								}

								// Fill out Priority
								const elementPriority = elementModal.querySelector('.card-detail-item.is-priority');
								if (isElement(elementPriority)) {
									let priorityName = 'Low';
									if (!isVarEmpty(data.priority)) {
										switch (data.priority.trim()) {
										case 'low': priorityName = 'Low'; break;
										case 'medium': priorityName = 'Medium'; break;
										case 'high': priorityName = 'High'; break;
										default: break;
										}
										const buttonToggler = elementPriority.querySelector('.dropdown-toggle');
										if (isElement(buttonToggler)) {
											buttonToggler.innerHTML = priorityName;
										}
									}
								}

								// Fill out Status
								const elementStatus = elementModal.querySelector('.card-detail-item.is-status');
								if (isElement(elementStatus)) {
									let statusName = 'In-complete';
									if (!isVarEmpty(data.status)) {
										switch (data.status.trim()) {
										case 'in_complete': statusName = 'In-complete'; break;
										case 'complete': statusName = 'Completed'; break;
										default: break;
										}
										const buttonToggler = elementStatus.querySelector('.dropdown-toggle');
										if (isElement(buttonToggler)) {
											buttonToggler.innerHTML = statusName;
										}
									}
								}

								// Fill out Tasks
								const elementTaskForm = elementModal.querySelector('.card-task-form');
								if (isElement(elementTaskForm)) {
									removeAllChildNodes(elementTaskForm);
									if (data.taskItems.length >= 1) {
										data.taskItems.forEach((perTask) => {
											const elementString2 = `
												<div class="task-item" data-id="${perTask.id}">
													<div class="row g-2 align-items-center">
														<div class="col-auto">
															<div class="form-check">
																<input class="form-check-input" type="checkbox" value="${perTask.isDone}" ${perTask.isDone ? 'checked':''}/>
															</div>
														</div>
														<div class="col">
															<div class="task-wrapper row g-0 gx-2 align-items-center">
																<div class="col">
																	<p class="task-title">${perTask.name}</p>
																</div>
																<div class="col-auto">
																	<div class="task-action">
																		<button type="button" class="btn btn-success btn-task-action" data-action="update">
																			<img src="${iconEdit}" alt="Icons Edit" />
																		</button>
																		<button type="button" class="btn btn-danger btn-task-action" data-action="delete">
																			<img src="${iconDelete}" alt="Icons Delete" />
																		</button>
																	</div>
																</div>
															</div>
														</div>
													</div>
												</div>
											`;
											const html2Node2 = ConvertStringToHTML(elementString2);
											while (html2Node2.firstChild) { elementTaskForm.appendChild(html2Node2.firstChild); }

											// Check element List-item exist
											const elementTask = elementTaskForm.querySelector(`.task-item[data-id="${perTask.id}"`);
											if (isElement(elementTask)) {
												/// Button action Checked
												const btnTaskCheck = elementTask.querySelector('.form-check-input');
												if (isElement(btnTaskCheck)) {
													eventOn('change', btnTaskCheck, eventTriggers.taskUpdateCheck.bind(null, data.id, perTask.id));
												}

												// Button action Update/Delete
												const btnTaskActions = Array.from(elementTask.querySelectorAll('.btn-task-action'));
												if (btnTaskActions.length >= 1) {
													btnTaskActions.forEach((perBtn) => {
														if (isElement(perBtn)) {
															eventOn('click', perBtn, async () => {
																const dataAction = perBtn.getAttribute('data-action') ?? '';
																if (dataAction === 'update') {
																	const nameValue = prompt("Please enter a new value for change Task-name", "");
																	if (!isVarEmpty(nameValue)) {
																		if (nameValue.trim().length >= 5) {
																			const updateResponse = await eventTriggers.taskUpdate(data.id, perTask.id, nameValue);
																			if (isObject(updateResponse) && Object.prototype.hasOwnProperty.call(updateResponse, 'success')) {
																				if (updateResponse.success) {
																					window.location.reload();
																				}
																			}
																		} else {
																			if (confirm('Minimum 5 characters, try again?')) {
																				perBtn.click();
																			}
																		}
																	} else {
																		if (confirm('Task name must be filled, try again?')) {
																			perBtn.click();
																		}
																	}
																}
																if (dataAction === 'delete') {
																	if (confirm('Are you sure want to delete this task?')) {
																		const deleteResponse = await eventTriggers.taskDelete(data.id, perTask.id);
																		if (isObject(deleteResponse) && Object.prototype.hasOwnProperty.call(deleteResponse, 'success')) {
																			if (deleteResponse.success) {
																				window.location.reload();
																			}
																		}
																	}
																}
															});
														}
													});
												}

											}
										});
									}
								}
								const elementTaskCreate = elementModal.querySelector('.btn-add-task');
								if (isElement(elementTaskCreate)) {
									eventOn('click', elementTaskCreate, eventTriggers.taskCreate.bind(null, data.id));
								}
							});
							modalInstance.show();
						}
					}
				}
			}
		},
		taskCreate: async (idCard) => {
			const nameValue = prompt("Please enter a new Task", "");
			if (!isVarEmpty(nameValue)) {
				if (nameValue.trim().length >= 5) {
					// Request API
					const requestAPI = new FormData();
					requestAPI.append('ajax', true);
					requestAPI.append('action', 'task-create');
					requestAPI.append('data', JSON.stringify({ taskTitle: nameValue.trim(), cardID: idCard }));
					const res = await axios.post('app/includes/todo.inc.php', requestAPI, { headers: { 'Content-Type': 'multipart/form-data' } }).then(({ data: res }) => (res)).catch((err) => console.error(err));
					if (isObject(res) && Object.prototype.hasOwnProperty.call(res, 'success')) {
						if (res.success) {
							window.location.reload();
						}
					}
				} else {
					if (confirm('Minimum 5 characters, try again?')) {
						eventTriggers.listCreate();
					}
				}
			} else {
				if (confirm('Task name must be filled, try again?')) {
					eventTriggers.listCreate();
				}
			}
		},
		taskUpdate: async (idCard, idTask, title, evt) => {
			// Update Data
			const requestAPI = new FormData();
			requestAPI.append('ajax', true);
			requestAPI.append('action', 'task-update');
			requestAPI.append('data', JSON.stringify({ cardID: idCard, taskID: idTask, taskTitle: title }));
			const output = await axios.post('app/includes/todo.inc.php', requestAPI, { headers: { 'Content-Type': 'multipart/form-data' } }).then(({ data: res }) => (res)).catch((err) => console.error(err));
			return output;
		},
		taskUpdateCheck: async (idCard, idTask, evt) => {
			// Update Data
			const requestAPI = new FormData();
			requestAPI.append('ajax', true);
			requestAPI.append('action', 'task-update-check');
			requestAPI.append('data', JSON.stringify({ cardID: idCard, taskID: idTask, taskFlag: evt.target.checked }));
			const output = await axios.post('app/includes/todo.inc.php', requestAPI, { headers: { 'Content-Type': 'multipart/form-data' } }).then(({ data: res }) => (res)).catch((err) => console.error(err));
			return output;
		},
		taskDelete: async (idCard, idTask, title, evt) => {
			// Delete Data
			const requestAPI = new FormData();
			requestAPI.append('ajax', true);
			requestAPI.append('action', 'task-delete');
			requestAPI.append('data', JSON.stringify({ cardID: idCard, taskID: idTask, taskTitle: title }));
			const output = await axios.post('app/includes/todo.inc.php', requestAPI, { headers: { 'Content-Type': 'multipart/form-data' } }).then(({ data: res }) => (res)).catch((err) => console.error(err));
			return output;
		},
	};

	// Create new list trigger
	const elementContent = document.getElementById('content');
	let listWrapper = null;
	let listTriggerNode = null;
	if (isElement(elementContent)) {
		listWrapper = elementContent.querySelector('.list-wrapper');
		if (isElement(listWrapper)) {
			listTriggerNode = listWrapper.querySelector('.list-item.is-trigger');
			if (isElement(listTriggerNode)) {
				eventOn('click', listTriggerNode, eventTriggers.listCreate);
			}
		}
	}

	// View Data
	const requestData = new FormData();
	requestData.append('ajax', true);
	requestData.append('action', 'list-cards');
	axios.post('app/includes/todo.inc.php', requestData, { headers: { 'Content-Type': 'multipart/form-data' } }).then(({ data: res }) => {
		if (res.success && res.data.length >= 1) {
			if (isElement(elementContent)) {
				if (isElement(listWrapper)) {
					const listItemsDOM = Array.from(listWrapper.querySelectorAll('.list-item:not(.is-trigger)'));
					if (listItemsDOM.length >= 1) {
						listItemsDOM.forEach((perList) => removeNodes(perList));
					}
					// removeAllChildNodes(listWrapper);
					res.data.forEach((perList) => {
						const elementString = `
							<div class="list-item" data-id="${perList.id_list}">
								<div class="list-head col-auto">
									<div class="list-head-wrapper">
										<div class="list-head-title">
											<h4 class="list-title">${perList.list_name}</h4>
											<input type="text" value="${perList.list_name}" class="input-list-title d-none" />
										</div>
										<button class="btn btn-action-filter">
											<img src="${iconFilter}" alt="Icons Filter" />
										</button>
									</div>
									<div class="list-head-filter">
										<section class="row g-2">
											<div class="col-12">
												<div class="filter-search">
													<label class="filter-label">Search</label>
													<input type="email" class="form-control form-control-sm" placeholder="Search task here...">
												</div>
											</div>
											<div class="col-6">
												<div class="filter-status">
													<label class="filter-label">Status</label>
													<div class="dropdown w-100">
														<button class="btn w-100 d-flex align-items-center justify-content-between btn-sm btn-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Any</button>
														<ul class="dropdown-menu">
															<li><button type="button" class="dropdown-item" data-value="any">Any</button></li>
															<li><button type="button" class="dropdown-item" data-value="complete">Complete</button></li>
															<li><button type="button" class="dropdown-item" data-value="in_complete">In-complete</button></li>
														</ul>
													</div>
												</div>
											</div>
											<div class="col-6">
												<div class="filter-priority">
													<label class="filter-label">Priority</label>
													<div class="dropdown w-100">
														<button class="btn w-100 d-flex align-items-center justify-content-between btn-sm btn-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Any</button>
														<ul class="dropdown-menu">
															<li><button type="button" class="dropdown-item" data-value="any">Any</button></li>
															<li><button type="button" class="dropdown-item" data-value="low">Low</button></li>
															<li><button type="button" class="dropdown-item" data-value="medium">Medium</button></li>
															<li><button type="button" class="dropdown-item" data-value="high">High</button></li>
														</ul>
													</div>
												</div>
											</div>
											<div class="col"></div>
										</section>
									</div>
								</div>
								<div class="list-body col">
									<section class="card-wrapper"></section>
								</div>
								<div class="list-foot col-auto">
									<div class="card-input-wrapper d-none">
										<textarea type="text" value="Test" class="input-card-title" placeholder="Enter a title for this card…"></textarea>
										<div class="row w-100 gx-1">
											<div class="col-auto">
												<button class="btn btn-success btn-card-add">Add card</button>
											</div>
											<div class="col-auto">
												<button class="btn btn-card-cancel">
													<img src="${iconClose}" alt="Icons Close" />
												</button>
											</div>
										</div>
									</div>
									<div class="card-trigger-wrapper">
										<button class="btn btn-action-add">
											<img src="${iconAdd}" alt="Icons Add"/>
											<p class="fnt-style1">Add a card</p>
										</button>
									</div>
								</div>
							</div>
						`;
						const html2Node = ConvertStringToHTML(elementString);
						while (html2Node.firstChild) { listWrapper.insertBefore(html2Node.firstChild, listTriggerNode); }

						// Check element List-item exist
						const elementListItem = listWrapper.querySelector(`.list-item[data-id="${perList.id_list}"]`);
						if (isElement(elementListItem)) {
							const cardWrapper = elementListItem.querySelector('.card-wrapper');
							if (isElement(cardWrapper)) {
								perList.cards.forEach((perCard) => {
									let stringDueDates = '';
									if (!isVarEmpty(perCard.due_date)) {
										stringDueDates = `
											<div class="col-auto">
												<div class="card-footer-item is-due-date">
													${perCard.due_date}
												</div>
											</div>
										`;
									}
									let stringPriority = '';
									if (!isVarEmpty(perCard.priority)) {
										let priorityClass = 'bg-dark';
										let priorityName = 'Low';
										switch (perCard.priority.trim()) {
										case 'low': {
											priorityClass = 'bg-dark';
											priorityName = 'Low';
											break;
										}
										case 'medium': {
											priorityClass = 'bg-orange text-dark';
											priorityName = 'Medium';
											break;
										}
										case 'high': {
											priorityClass = 'bg-danger';
											priorityName = 'High';
											break;
										}
										default: break;
										}
										stringPriority = `
											<div class="col-auto">
												<div class="card-footer-item is-priority ${priorityClass}">
													${priorityName}
												</div>
											</div>
										`;
									}
									let stringStatus = '';
									if (!isVarEmpty(perCard.status)) {
										let statusClass = 'bg-dark';
										let statusName = 'In-complete';
										switch (perCard.priority.trim()) {
										case 'in_complete': {
											statusClass = 'bg-dark';
											statusName = 'In-complete';
											break;
										}
										case 'complete': {
											statusClass = 'bg-success';
											statusName = 'Complete';
											break;
										}
										default: break;
										}
										stringStatus = `
											<div class="col-auto">
												<div class="card-footer-item is-status ${statusClass}">
													${statusName}
												</div>
											</div>
										`;
									}
									const elementString2 = `
										<div class="card-item" data-id="${perCard.id}">
											<button class="btn btn-card-edit">
												<img src="${iconEdit}" alt="Icons Edit" />
											</button>
											<div class="card-item-value">
												${perCard.name}
											</div>
											<div class="card-item-footer row g-2">
												${stringDueDates}
												${stringPriority}
												${stringStatus}
											</div>
										</div>
									`;
									const html2Node2 = ConvertStringToHTML(elementString2);
									while (html2Node2.firstChild) { cardWrapper.appendChild(html2Node2.firstChild); }

									// Check element Card-item exist
									const elementCardItem = cardWrapper.querySelector(`.card-item[data-id="${perCard.id}"]`);
									if (isElement(elementCardItem)) {
										eventOn('click', elementCardItem, eventTriggers.cardOpen.bind(null, perCard.id));
									}
								});
							}

							// Create event action
							const elementListHead = elementListItem.querySelector('.list-head');
							if (isElement(elementListHead)) {
								const elementHeadWrapper = elementListHead.querySelector('.list-head-wrapper');
								const elementHeadFilter = elementListHead.querySelector('.list-head-filter');
								if (isElement(elementHeadWrapper)) {
									const elementBtnFilter = elementHeadWrapper.querySelector('.btn-action-filter');
									if (isElement(elementBtnFilter) && isElement(elementHeadFilter)) {
										eventOn('click', elementBtnFilter, () => {
											elementHeadFilter.classList.toggle('d-none');
										});
									}
								}
							}
							const elementListFoot = elementListItem.querySelector('.list-foot');
							if (isElement(elementListFoot)) {
								const elementInputWrapper = elementListFoot.querySelector('.card-input-wrapper');
								const elementBtnTrigger = elementListFoot.querySelector('.card-trigger-wrapper button');
								if (isElement(elementInputWrapper) && isElement(elementBtnTrigger)) {
									eventOn('click', elementBtnTrigger, () => {
										elementInputWrapper.classList.remove('d-none');
										elementBtnTrigger.classList.add('d-none');
									});
									if (isElement(elementInputWrapper)) {
										const elementInputName = elementInputWrapper.querySelector('textarea');
										const elementBtnAdd = elementInputWrapper.querySelector('.btn-card-add');
										if (isElement(elementInputName) && isElement(elementBtnAdd)) {
											eventOn('click', elementBtnAdd, async () => {
												const inputValue = elementInputName.value;
												if (!isVarEmpty(inputValue) && inputValue.length >= 5) {
													const res2 = await eventTriggers.cardCreate(perList.id_list, inputValue);
													if (res2.success) {
														window.location.reload();
													}
												} else {
													alert('Tidak boleh kosong atau kurang dari 5 karakter!');
												}
											});
										}

										// Show to previous element
										const elementBtnCancel = elementInputWrapper.querySelector('.btn-card-cancel');
										if (isElement(elementBtnCancel)) {
											eventOn('click', elementBtnCancel, () => {
												elementInputWrapper.classList.add('d-none');
												elementBtnTrigger.classList.remove('d-none');
											});
										}
									}
								}
							}
						}
					});
				}
			}
		}
	}).catch((err) => console.error(err));
});
