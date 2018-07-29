import Backbone from 'backbone';
import colorPalettes from 'nice-color-palettes';
import $ from 'jquery';

// 100 palettes * 5 colors = 500 colors
const COLORS = colorPalettes.reduce((p1, p2) => p1.concat(p2), []);
const simpleHash = str => {
	var hash = 0;
	for (var i = 0; i < str.length; i++) {
		hash = str.charCodeAt(i) + (hash << 6) + (hash << 16) - hash;
	}
	return Math.abs(hash);
};

export default Backbone.Model.extend({
	urlRoot: 'bookmark',
	parse: function(json) {
		if (json.item) {
			return json.item;
		}
		return json;
	},
	clickLink: function() {
		const url = encodeURIComponent(this.get('url'));
		$.ajax({
			method: 'POST',
			url: 'bookmark/click?url=' + url,
			headers: {
				requesttoken: oc_requesttoken
			}
		});
	},
	getColor: function() {
		return COLORS[simpleHash(new URL(this.get('url')).host) % COLORS.length];
	}
});
