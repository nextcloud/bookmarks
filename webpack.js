const { merge } = require('webpack-merge')
const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

const config = {
	entry: {
		admin: path.join(__dirname, 'src', 'admin.js'),
		'service-worker': path.join(__dirname, 'src', 'service-worker.js'),
	},
}

module.exports = merge(config, webpackConfig)
