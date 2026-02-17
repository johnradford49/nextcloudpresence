module.exports = {
	extends: [
		'@nextcloud',
	],
	parserOptions: {
		ecmaVersion: 2022,
		sourceType: 'module',
		parser: '@typescript-eslint/parser',
	},
	rules: {
		'jsdoc/require-jsdoc': 'off',
		'vue/first-attribute-linebreak': 'off',
	},
}
