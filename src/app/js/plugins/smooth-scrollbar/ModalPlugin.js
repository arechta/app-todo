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

export default class ModalPlugin extends ScrollbarPlugin {
	static pluginName = 'modal';

	static defaultOptions = {
		open: false,
	};

	transformDelta(delta) {
		return this.options.open ? { x: 0, y: 0 } : delta;
	}
}
