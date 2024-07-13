module.exports = {
	"extends": "stylelint-config-standard",
	"customSyntax": "postcss-scss",
	"plugins": [
        "stylelint-scss"
    ],
	"rules": {
		"at-rule-no-unknown": null,
		"at-rule-empty-line-before": "never",
		"scss/at-rule-no-unknown": true,
		"block-no-empty": true,
		"color-no-invalid-hex": true,
		"color-hex-case": [
			"upper",
			{
			  "message": "Uppercase letters are easier to write"
			}
		],
		"indentation": "tab",
		"comment-no-empty": true,
		"declaration-block-no-duplicate-properties": [
			true,
			{
				ignore: ["consecutive-duplicates-with-different-values"]
			}
		],
		"declaration-block-no-shorthand-property-overrides": true,
		"font-family-no-duplicate-names": true,
		"font-family-no-missing-generic-family-keyword": true,
		"function-calc-no-unspaced-operator": true,
		"function-linear-gradient-no-nonstandard-direction": true,
		"keyframe-declaration-no-important": true,
		"media-feature-name-no-unknown": true,
		"no-descending-specificity": true,
		"no-duplicate-at-import-rules": true,
		"no-duplicate-selectors": true,
		"no-empty-source": true,
		"no-extra-semicolons": true,
		"no-invalid-double-slash-comments": true,
		"property-no-unknown": true,
		"selector-pseudo-class-no-unknown": true,
		"selector-pseudo-element-no-unknown": true,
		"selector-type-no-unknown": true,
		"string-no-newline": true,
		"unit-no-unknown": true,
		"selector-list-comma-newline-after": "never-multi-line",
		"declaration-block-single-line-max-declarations": 4
	},
	"ignoreFiles": [
		"src/vendor/",
		"src/vendor/**/*.css",
		"src/vendor/**/*.sass",
		"src/vendor/**/*.scss",
		"src/vendor/**/*.{css,scss,sass}",
		"node_modules/",
		"node_modules/**/*.js",
		"node_modules/**/*.css",
		"node_modules/**/*.sass",
		"node_modules/**/*.scss",
		"node_modules/**/*.{css,scss,sass}"
	]
};