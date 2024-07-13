/* eslint-disable no-lonely-if */
/* eslint-disable max-len */
/* eslint-disable no-loop-func */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* eslint no-undef: "error" */

import { library, dom } from '@fortawesome/fontawesome-svg-core';
import { faClock as fasClock } from '@fortawesome/free-solid-svg-icons/faClock';
import { faCalendar as fasCalendar } from '@fortawesome/free-solid-svg-icons/faCalendar';
import { faArrowUp as fasArrowUp } from '@fortawesome/free-solid-svg-icons/faArrowUp';
import { faArrowDown as fasArrowDown } from '@fortawesome/free-solid-svg-icons/faArrowDown';
import { faChevronLeft as fasChevronLeft } from '@fortawesome/free-solid-svg-icons/faChevronLeft';
import { faChevronRight as fasChevronRight } from '@fortawesome/free-solid-svg-icons/faChevronRight';
import { faCalendarCheck as fasCalendarCheck } from '@fortawesome/free-solid-svg-icons/faCalendarCheck';
import { faTrash as fasTrash } from '@fortawesome/free-solid-svg-icons/faTrash';
import { faTimes as fasTimes } from '@fortawesome/free-solid-svg-icons/faTimes';

const fontawesome = () => {
	library.add(
		fasClock,
		fasCalendar,
		fasArrowUp,
		fasArrowDown,
		fasChevronLeft,
		fasChevronRight,
		fasCalendarCheck,
		fasTrash,
		fasTimes,
	);

	dom.i2svg();
	dom.watch();
};

export default fontawesome;
