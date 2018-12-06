import _ from 'underscore';
import Backbone from 'backbone';
import Tag from '../models/Tag';
import Tags from '../models/Tags';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.View.extend({
	tagName: 'select',
	template: _.template(''),
	className: 'tags-selection',
	events: {
		'select2:select': 'onAddByUser',
		'select2:unselect': 'onRemoveByUser'
	},
	initialize: function(options) {
		this.app = options.app;
		this.selected = options.selected || new Tags();
		this.selected.comparator = 'name';

		this.listenTo(this.selected, 'add', this.onChangeByAlgo);
		this.listenTo(this.selected, 'remove', this.onChangeByAlgo);
		this.listenTo(this.selected, 'reset', this.onChangeByAlgo);
	},
	onAttach: function() {
		if (this.$el.hasClass('select2-hidden-accessible')) {
			this.$el.select2('destroy');
		}
		this.$el
			.select2({
				placeholder: t('bookmarks', 'Set tags'),
				width: '100%',
				tags: true,
				multiple: true,
				tokenSeparators: [','],
				data: this.app.tags.pluck('name').map(function(name) {
					return { id: name, text: name };
				})
			})
			.val(this.selected.pluck('name'))
			.trigger('change');
	},
	onDetach: function() {
		this.$el.select2('destroy');
	},
	onAddByUser: function(e) {
		var that = this;
		var tag =
			this.app.tags.get(e.params.data.text) ||
			new Tag({ name: e.params.data.text, count: 1 });
		this.selected.add(tag);
		setTimeout(function() {
			that.$el.select2('open');
		}, 150);
	},
	onRemoveByUser: function(e) {
		this.selected.remove(e.params.data.text);
	},
	onChangeByAlgo: function(e) {
		this.$el.val(this.selected.pluck('name')).trigger('change');
	}
});
