const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

module.exports = webpackConfig

webpackConfig.entry.admin = path.join(__dirname, 'src', 'admin.js')
webpackConfig.entry['service-worker'] = path.join(__dirname, 'src', 'service-worker.js')
webpackConfig.entry.flow = path.join(__dirname, 'src', 'flow.js')
webpackConfig.entry.dashboard = path.join(__dirname, 'src', 'dashboard.js')
webpackConfig.entry.talk = path.join(__dirname, 'src', 'talk.js')
webpackConfig.entry.references = path.join(__dirname, 'src', 'references.js')
