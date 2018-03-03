import _ from 'underscore';
import Backbone from 'backbone';
import Marionette from 'backbone.marionette';
import select2 from 'select2';
import Tag from './models/Tag';
import Tags from './models/Tags';
import App from './Application';

var _sync = Backbone.sync;
Backbone.sync = function(method, model, options) {
	var overrideOptions = {
		headers: {
			'requesttoken': oc_requesttoken
		}
	};
	if (method === 'update' && model instanceof Tag) {
		overrideOptions.url = model.urlRoot+'/'+model.previous('name'); 
	}
	_sync(method, model, _.extend({}, options, overrideOptions));
};

// init

var app = new App();
$(function() {
	app.start();
});
