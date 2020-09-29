const { merge } = require('webpack-merge')
const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

const config = {
	entry: {
		admin: path.join(__dirname, 'src', 'admin.js'),
		'service-worker': path.join(__dirname, 'src', 'service-worker.js'),
		flow: path.join(__dirname, 'src', 'flow.js'),
	},
	module: {
		rules: [
			{
				test: /\.vue$/,
				loader: 'vue-loader',
			},
		],
	},
}

const mergedConfigs = merge(config, webpackConfig)

// Remove duplicate rules by the `test` key
mergedConfigs.module.rules = mergedConfigs.module.rules.filter((v, i, a) => a.findIndex(t => (t.test.toString() === v.test.toString())) === i)

module.exports = mergedConfigs
