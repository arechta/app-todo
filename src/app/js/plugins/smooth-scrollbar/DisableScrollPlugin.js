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

export default class DisableScrollPlugin extends ScrollbarPlugin {
	static pluginName = 'disableScroll';

	static defaultOptions = {
		direction: null,
	};

	// constructor() {
	// 	super();
	// 	this.track.xAxis.element.remove();
	// }

	transformDelta(delta) {
		if (this.options.direction) {
			delta[this.options.direction] = 0;
		}

		return { ...delta };
	}
}
