var path = require('path')
module.exports = {
	entry: {
		main: './js/main.js',
		bookmarklet: './js/bookmarklet.js'
	},
	output: {
		filename: '[name].bundle.js',
		path: path.resolve(__dirname, 'js/dist')
	},
	module: {
		rules: [
			{
				test: /\.html$/,
				use: [ {
					loader: 'html-loader',
					options: {
						minimize: true,
						exportAsEs6Default: true
					}
				}],
			}
		]
	}
};
