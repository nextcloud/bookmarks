var path = require('path')
module.exports = {
  entry: './js/index.js',
  output: {
    filename: 'bundle.js',
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
