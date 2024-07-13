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

import { ScrollbarPlugin } from 'smooth-scrollbar';

export default class HorizontalScrollPlugin extends ScrollbarPlugin {
	static pluginName = 'horizontalScroll';

	static defaultOptions = {
		enabled: false,
	};

	transformDelta(delta, fromEvent) {
		if (!/wheel/.test(fromEvent.type)) {
			return delta;
		}

		// @see: https://github.com/idiotWu/smooth-scrollbar/issues/181
		const { x, y } = delta;

		return this.options.enabled ? {
			y: 0,
			x: Math.abs(x) > Math.abs(y) ? x : y,
			// x: Math.sign(x || y) * Math.sqrt(x*x + y*y),
		} : delta;
	}
}
