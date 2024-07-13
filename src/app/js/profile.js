/* eslint-disable import/first */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* global axios, bootstrap */
/* eslint no-undef: "error" */

// eslint-disable-next-line camelcase, no-undef
__webpack_public_path__ = window.resourceBasePath;

/* == Module import == */
import 'Styles/profile.scss';
import iconUser from '../../asset/image/icons/heroicons_user-group-20-solid.svg';

/* == Main script == */
// DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
	// Tooltip
	const myDefaultAllowList = bootstrap.Tooltip.Default.allowList;
	myDefaultAllowList.h6 = ['data-weight', 'style'];
	myDefaultAllowList.p = ['data-weight', 'style'];
	myDefaultAllowList.img = ['src', 'class', 'style'];
});
