import Backbone from 'backbone';
import $ from 'jquery'

export default Backbone.Model.extend({
	urlRoot: 'settings',
	initialize: function() {
		var that = this;
		$.ajax({
			method: 'GET',
			url: 'settings/sort',
			async: 'false',
			headers: {
				'requesttoken': oc_requesttoken
			},
			dataType: 'json',
			success: function(response) {
				var value = response.sorting;
				that.set({sorting : value});
			}
		});
	},
	setSorting: function(sorting) {
		var that = this;
		$.ajax({
			method: 'POST',
			url: 'settings/sort',
			headers: {
				'requesttoken': oc_requesttoken
			},
			data: {
				sorting: sorting
			},
			success: function() {
				that.set({sorting : sorting});
			},
		});
	},
});
