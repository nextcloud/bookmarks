import _ from 'underscore';
import Backbone from 'backbone';
import Tags from '../models/Tags';
import TagsNavigationView from './TagsNavigation';
import templateString from '../templates/BookmarkCard.html';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.View.extend({
	template: _.template(templateString),
	className: 'bookmark-card',
	ui: {
		'link': 'h2 > a',
		'checkbox': '.selectbox',
	},
	regions: {
		'tags': '.tags'
	},
	events: {
		'click': 'open',
		'click @ui.link': 'clickLink',
		'click @ui.checkbox': 'select',
	},
	initialize: function(opts) {
		this.app = opts.app;
		this.listenTo(this.model, 'change', this.render);
		this.listenTo(this.model, 'select', this.onSelect);
		this.listenTo(this.model, 'unselect', this.onUnselect);
		this.listenTo(this.app.tags, 'sync', this.render);
		this.listenTo(Radio.channel('documentClicked'), 'click', this.closeActions); 
	},
	onRender: function() {
	  var that = this;
		this.$el.css('background-image', 'url(bookmark/'+this.model.get('id')+'/image)');
		var tags = new Tags(this.model.get('tags').map(function(id) {
			return that.app.tags.findWhere({name: id});
		}));
		this.showChildView('tags', new TagsNavigationView({collection: tags}));
		this.$('.checkbox').prop('checked', this.$el.hasClass('active'));
	},
	clickLink: function() {
		this.model.clickLink();
	},
	open: function(e) {
		if (e && e.target !== this.el && e.target !== this.$('h1')[0]) return;
		Radio.channel('details').trigger('show', this.model);
	},
	select: function(e) {
		e.stopPropagation();
		if (this.$el.hasClass('active')) {
			this.model.trigger('unselect', this.model);
		}else{
			this.model.trigger('select', this.model);
		}
	},
	onSelect: function() {
		this.$el.addClass('active');
		this.render();
	},
	onUnselect: function() {
		this.$el.removeClass('active');
		this.render();
	}
});
