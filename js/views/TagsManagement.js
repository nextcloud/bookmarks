import Backbone from 'backbone';
import Tags from '../models/Tags';
import TagView from './TagsManagementTag';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.CollectionView.extend({
	childView: TagView,
	tagName: 'ul',
	className: 'tags-management',
	initialize: function(options) {
		this.selected = new Tags;
		this.selected.comparator = 'name';
    
		this.listenTo(this.collection, 'select', this.onSelect);
		this.listenTo(this.collection, 'unselect', this.onUnselect);
		this.listenTo(this.collection, 'add', this.onAdd);
		this.listenTo(Radio.channel('nav'), 'navigate', this.onNavigate);
	},
	onNavigate: function(category, tags) {
		// reset selection (needs slice, since we pull the models out from under the loop otherwise)
		this.selected.slice().forEach(function(t) {
			t.trigger('unselect', t, true);
		});
		if (category !== 'tags') {
			this.lastRouteTags = []; // for the below hack
			return;
		}

		var that = this;
		// select all tags passed by router
		tags.forEach(function(tagName) {
			var tag = that.collection.findWhere({name: tagName});
			if (!tag) return;

			tag.trigger('select', tag, true);
		});

		// hack!
		// this is for when the route is triggered before the tags are loaded
		this.lastRouteTags = tags;
	},
	onAdd: function(model) {
		if (~this.lastRouteTags.indexOf(model.get('name'))) {
			// wait for the tag view to render, so it can receive the event
			setTimeout(function() {
				model.trigger('select', model, true);
			}, 1);
			return;
		}
	},
	onSelect: function(model, silentRoute) {
		this.selected.add(model);
		if (!silentRoute) this.triggerRoute();
	},
	onUnselect: function(model, silentRoute) {
		this.selected.remove(model);
		if (!silentRoute) this.triggerRoute();
	},
	triggerRoute: function() {
		Backbone.history.navigate('tags/'+this.selected.pluck('name').map(encodeURIComponent).join(','), {trigger: true});
	}
});
