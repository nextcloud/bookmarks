import Backbone from 'backbone';
import Marionette from 'backbone.marionette';
import select2 from 'select2';
import App from './apps/Main';
import fixBackboneSync from './utils/FixBackboneSync';

// init

var app = new App();
$(function() {
	app.start();
});
