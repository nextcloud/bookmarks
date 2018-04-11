import _ from 'underscore';
import Backbone from 'backbone';
import Tags from '../models/Tags';
import TagsNavigationView from './TagsNavigation';
import templateString from '../templates/BookmarkCard.html';
import colorPalettes from 'nice-color-palettes';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

const COLORS = colorPalettes.reduce((p1, p2) => p1.concat(p2), [])
const simpleHash = (str) => {
	var hash = 0;
	for (var i = 0; i<str.length; i++){
		hash = str.charCodeAt(i) + (hash << 6) + (hash << 16) - hash;
	}
	return hash
}

export default Marionette.View.extend({
	template: _.template(templateString),
	className: 'bookmark-card',
	ui: {
		'link': 'h2 > a',
		'checkbox': '.selectbox',
		'actionsMenu': '.popovermenu',
		'actionsToggle': '.toggle-actions'
	},
	regions: {
		'tags': '.tags'
	},
	events: {
		'click': 'clickLink',
		'click @ui.checkbox': 'select',
		'click @ui.actionsToggle': 'toggleActions',
		'click .menu-filter-add': 'select',
		'click .menu-filter-remove': 'select',
		'click .menu-delete': 'delete',
		'click .menu-details': 'open',
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
		if (this.model.get('image')) {
			this.$el.css('background-image', 'url(bookmark/'+this.model.get('id')+'/image)');
		} else {
			this.$el.css('background-color', COLORS[simpleHash(this.model.get('url')) & 63] + '66')
		}
		var tags = new Tags(this.model.get('tags').map(function(id) {
			return that.app.tags.findWhere({name: id});
		}));
		this.showChildView('tags', new TagsNavigationView({collection: tags}));
		this.$('.checkbox').prop('checked', this.$el.hasClass('active'));
	},
	clickLink: function(e) {
		if (e &&  e.target === this.getUI('actionsToggle')[0]) {
			return;
		}
		if (this.$el.closest('.selection-active').length) {
			this.select(e);
			e.preventDefault();
			return
		}
		this.model.clickLink();
	},
	open: function() {
		Radio.channel('details').trigger('show', this.model);
	},
	toggleActions: function() {
		this.getUI('actionsMenu').toggleClass('open').toggleClass('closed')
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
	},
	delete: function() {
		this.model.destroy();
	}
});
