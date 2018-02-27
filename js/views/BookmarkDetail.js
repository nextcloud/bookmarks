import _ from 'underscore';
import Backbone from 'backbone';
import Tags from '../models/Tags';
import TagsNavigationView from './TagsNavigation';
import TagsSelectionView from './TagsSelection';
import templateString from '../templates/BookmarkDetail.html';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.View.extend({
	template: _.template(templateString),
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
		'click @ui.delete': 'delete',
		'click h1': 'edit',
		'click h2 .edit': 'edit',
		'click .description': 'edit',
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
		this.listenTo(this.tags, 'add remove', this.submitTags);
		this.showChildView('tags', new TagsSelectionView({collection: this.app.tags, selected: this.tags, app: this.app }));
	},
	clickLink: function() {
		this.model.clickLink();
	},
	close: function() {
		Radio.channel('details').trigger('close');
	},
	edit: function(e) {
	    var that = this;
		e.preventDefault();
		
		var $el = $(e.target).closest('[data-attribute]');
		
		switch($el.data('attribute')) {
		case 'url':
			$el.text(this.model.get('url'));
			// fallthrough
		case 'title':
			$el.keydown(function(e) {
				// enter
				if (e.which === 13) {
					that.submit($el);
				}
			});
			break;
		case 'description':
			break;
		}
		$el.prop('contenteditable', true);
		$el.blur(function() {
			that.submit($el);
		});
		$el.focus();
	},
	submitTags: function() {
		this.model.set({
			'tags': this.tags.pluck('name'),
		});
		this.model.save({wait: true});
	},
	submit: function($el) {
		this.model.set({
			[$el.data('attribute')]: $el.text()
		});
		this.model.save({wait: true});
	},
	onDestroy: function() {
		this.close(); 
	}
});
