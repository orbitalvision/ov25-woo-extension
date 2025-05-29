module.exports = {
	extends: [
		'plugin:@woocommerce/eslint-plugin/recommended',
		'plugin:@typescript-eslint/recommended'
	],
	parser: '@typescript-eslint/parser',
	plugins: ['@typescript-eslint'],
	rules: {
		'react/react-in-jsx-scope': 'off',
	},
	overrides: [
		{
			files: ['**/*.ts', '**/*.tsx'],
			rules: {
				'no-undef': 'off',
			},
		},
	],
};
