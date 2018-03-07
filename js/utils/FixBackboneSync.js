import Backbone from 'backbone';

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
