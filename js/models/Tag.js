import Backbone from 'backbone';

export default Backbone.Model.extend({
	idAttribute: 'name',
	urlRoot: 'tag'
});
