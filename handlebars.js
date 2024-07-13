/* eslint-disable func-names */
/* eslint-disable import/no-extraneous-dependencies */
/* eslint-disable no-param-reassign */
/* eslint-disable one-var */
/* eslint-disable one-var-declaration-per-line */
/* eslint linebreak-style: ["error", "windows"] */
/* eslint global-require: "error" */
/* eslint no-param-reassign: "error" */
/* eslint-disable no-restricted-syntax */
/* eslint-disable max-len */

const Handlebars = require('handlebars/runtime');

// Helpers
Handlebars.registerHelper('ifCond', function (v1, v2, options) {
	if (v1 === v2) { return options.fn(this); }
	return options.inverse(this);
});
Handlebars.registerHelper('contains', function (needle, haystack, options) {
	needle = Handlebars.escapeExpression(needle);
	haystack = Handlebars.escapeExpression(haystack);
	return (haystack.indexOf(needle) > -1) ? options.fn(this) : options.inverse(this);
});

module.exports = Handlebars;
