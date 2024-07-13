/* eslint-disable import/prefer-default-export */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* eslint no-undef: "error" */

export const getInitials = (fullName) => {
	const allNames = fullName.trim().split(' ');
	const initials = allNames.reduce((acc, curr, index) => {
		if (index === 0 || index === allNames.length - 1) {
			acc = `${acc}${curr.charAt(0).toUpperCase()}`;
		}
		return acc;
	}, '');
	return initials;
};

// to always return type string event when s may be falsy other than empty-string
export const capitalize = (s) => (s && s[0].toUpperCase() + s.slice(1)) || '';
