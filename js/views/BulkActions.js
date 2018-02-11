import _ from 'underscore';
import Backbone from 'backbone';
import Tags from '../models/Tags';
import TagsSelectionView from './TagsSelection';
import templateString from '../templates/BulkActions.html';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.View.extend({
	className: 'bulk-actions',
	template: _.template(templateString),
	regions: {
		'tags': {
			el: '.tags'
		}
	},
	events: {
		'click .delete': 'delete',
		'click .select-all': 'selectAll',
		'click .selection-tools .close': 'abort'
	},
	initialize: function(opts) {
		this.app = opts.app;
		this.all = this.app.bookmarks;
		this.selected = opts.selected;
		this.tags = new Tags;
		this.listenTo(this.tags, 'remove', this.onTagRemoved);
		this.listenTo(this.tags, 'add', this.onTagAdded);
		this.listenTo(this.selected, 'remove', this.onReduceSelection);
		this.listenTo(this.selected, 'add', this.onExtendSelection);
	},
	onRender: function() {
		this.showChildView('tags', new TagsSelectionView({collection: this.app.tags, selected: this.tags, app: this.app }));
	},
	updateTags: function() {
		var that = this;
		this.triggeredByAlgo = true;
		this.tags.reset(
			_.intersection.apply(_, this.selected.pluck('tags'))
				.map(function(name) {
					return that.app.tags.get(name);
				})
		);
		this.triggeredByAlgo = false;
	},
	onReduceSelection: function() {
		if (this.selected.length == 0) {
			this.$el.removeClass('active');
		}
		this.updateTags();
	},
	onExtendSelection: function() {
		if (this.selected.length == 1) {
			this.$el.addClass('active');
		}
		this.updateTags();
	},
	delete: function() {
		this.selected.slice().forEach(function(model) {
			model.trigger('unselect', model);
			model.destroy({
				error: function() {
					Backbone.history.navigate('all', {trigger: true});
				}
			});
		});
	},
	onTagAdded: function(tag) {
		if (this.triggeredByAlgo) return;
		this.selected.forEach(function(model) {
			var tags = model.get('tags');
			model.set('tags', _.union(tags, [tag.get('name')]));
			model.save();
		}); 
	},
	onTagRemoved: function(tag) {
		if (this.triggeredByAlgo) return;
		this.selected.forEach(function(model) {
			var tags = model.get('tags');
			model.set('tags', _.without(tags, tag.get('name')));
			model.save();
		}); 
	},
	selectAll: function() {
		this.all.forEach(function(model) {
			model.trigger('select', model); 
		});
	},
	abort: function() {
		this.selected.models.slice().forEach(function(model) {
			model.trigger('unselect', model);
		});
	}
});
