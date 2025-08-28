const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const WooCommerceDependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');
const path = require('path');

module.exports = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry,
		'frontend': './src/frontend/index.ts',
		'swatches': './src/swatches/index.tsx',
	},
	module: {
		...defaultConfig.module,
		rules: [
			...defaultConfig.module.rules,
		]
	},
	resolve: {
		...defaultConfig.resolve,
		extensions: ['.ts', '.tsx', ...defaultConfig.resolve.extensions],
		fallback: {
			...defaultConfig.resolve.fallback,
			'react/jsx-runtime': path.resolve(__dirname, 'node_modules/react/jsx-runtime.js'),
			'react-dom/client': path.resolve(__dirname, 'node_modules/react-dom/client.js'),
		}
	},
	plugins: [
		...defaultConfig.plugins.filter(
			(plugin) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new WooCommerceDependencyExtractionWebpackPlugin(),
	],
};
