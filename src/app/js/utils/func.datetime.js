/* eslint-disable import/prefer-default-export */
/* eslint-disable no-param-reassign */
/* eslint-disable no-loop-func */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* eslint no-undef: "error" */
/* global moment */

export const runCodeEvery = (func, seconds) => setInterval(func, seconds * 1000);

/**
 * Fungsi untuk membuat date-range menggunakan
 * library Moment.js
 * @author Asphira Andreas <arechta911@gmail.com>
 * @param {date|moment} start The start date
 * @param {date|moment} end The end date
 * @param {string} type The range type. eg: 'days', 'hours' etc
 * @param {string} format The range type. eg: 'YYYY-MM-DD'
 * @return {Array} Return date-range Array
 */
export const getMomentRange = (startDate, endDate, type = 'days', format = 'YYYY-MM-DD') => {
	const fromDate = moment(startDate);
	const toDate = moment(endDate);
	const diff = toDate.diff(fromDate, type);
	const range = [];
	for (let i = 0; i <= diff; i += 1) {
		range.push(moment(startDate).add(i, type).format(format));
	}
	return range;
};
