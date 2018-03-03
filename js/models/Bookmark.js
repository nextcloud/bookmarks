import Backbone from 'backbone';
import $ from 'jquery'

export default Backbone.Model.extend({
	urlRoot: 'bookmark',
	clickLink: function() {
		const url = encodeURIComponent(this.get('url'))
		$.ajax({
			method: 'POST',
			url: 'bookmark/click?url='+url,
			headers: {
				'requesttoken': oc_requesttoken
			}
		})
	},
});
