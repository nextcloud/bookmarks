import Backbone from 'backbone';
import $ from 'jquery';

export default Backbone.Model.extend({
	urlRoot: 'settings',
	initialize: function() {
		this.fetch({
			url: 'settings/sort'
		});
		this.fetch({
			url: 'settings/view'
		});
	},
	setSorting: function(sorting) {
		var that = this;
		$.ajax({
			method: 'POST',
			url: 'settings/sort',
			headers: {
				requesttoken: oc_requesttoken
			},
			data: {
				sorting: sorting
			},
			success: function() {
				that.set({ sorting: sorting });
			}
		});
	},
	setViewMode: function(viewMode) {
		var that = this;
		$.ajax({
			method: 'POST',
			url: 'settings/view',
			headers: {
				requesttoken: oc_requesttoken
			},
			data: {
				viewMode: viewMode
			},
			success: function() {
				that.set({ viewMode: viewMode });
			}
		});
	}
});
