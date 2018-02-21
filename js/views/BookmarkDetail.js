import _ from 'underscore';
import Backbone from 'backbone';
import Tags from '../models/Tags';
import TagsNavigationView from './TagsNavigation';
import TagsSelectionView from './TagsSelection';
import templateStringDefault from '../templates/BookmarkDetail_default.html';
import templateStringEditing from '../templates/BookmarkDetail_editing.html';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.View.extend({
	getTemplate: function() {
		if (this.editing) {
			return this.templateEditing;
		}
		return this.templateDefault;
	},
	templateDefault: _.template(templateStringDefault),
	templateEditing: _.template(templateStringEditing),
	className: 'bookmark-detail',
	regions: {
		'tags': {
			el: '.tags'
		}
	},
	ui: {
		'link': 'h2 > a',
		'close': '> .close',
		'edit': '.edit',
		'delete': '.delete'
	},
	events: {
		'click @ui.link': 'clickLink',
		'click @ui.close': 'close',
		'click @ui.edit': 'edit',
		'click @ui.delete': 'delete',
		'click .submit': 'submit',
		'click .cancel': 'cancel'
	},
	initialize: function(opts) {
		this.app = opts.app;
		this.listenTo(this.model, 'change', this.render);
		this.listenTo(this.app.tags, 'sync', this.render);
	},
	onRender: function() {
		var that = this;
		this.tags = new Tags(this.model.get('tags').map(function(id) {
			return that.app.tags.findWhere({name: id});
		}));
		if (this.editing) {
			this.showChildView('tags', new TagsSelectionView({collection: this.app.tags, selected: this.tags, app: this.app }));
		}else{
			this.showChildView('tags', new TagsNavigationView({collection: this.tags}));
		}
	},
	clickLink: function() {
		this.model.clickLink();
	},
	close: function() {
		Radio.channel('details').trigger('close');
	},
	setEditing: function(isEditing) {
		if (isEditing) {
			this.editing = true;
			this.$el.addClass('editing');
		}else{
			this.editing = false;
			this.$el.removeClass('editing');
		}
		this.render();
	},
	edit: function() {
		this.setEditing(true);
	},
	submit: function() {
		var that = this;
		this.model.set({
			'title': this.$('.input-title').val(),
			'url': this.$('.input-url').val(),
			'tags': this.tags.pluck('name'),
			'description': this.$('.input-desc').val()
		});
		this.model.save({wait: true});
		this.model.once('sync', function() {
			that.cancel();
		});
	},
	cancel: function() {
		this.setEditing(false);
	},
	onDestroy: function() {
		this.close(); 
	}
});
