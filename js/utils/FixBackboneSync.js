import _ from 'underscore';
import Backbone from 'backbone';
import Tag from '../models/Tag';

var _sync = Backbone.sync;
Backbone.sync = function(method, model, options) {
	var overrideOptions = {
		headers: {
			requesttoken: oc_requesttoken
		}
	};
	if (method === 'update' && model instanceof Tag) {
		overrideOptions.url = model.urlRoot + '/' + model.previous('name');
	}
	return _sync(method, model, _.extend({}, options, overrideOptions));
};
