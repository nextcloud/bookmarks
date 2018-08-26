import _ from 'underscore';
import Backbone from 'backbone';
import Tag from '../models/Tag';
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
		tags: {
			el: '.tags'
		}
	},
	ui: {
		preview: '.preview',
		link: 'h2 > a',
		close: '> .close',
		edit: '.edit',
		status: '.status'
	},
	events: {
		'click @ui.link': 'clickLink',
		'click @ui.close': 'close',
		'click h1': 'edit',
		'click h2 .edit': 'edit',
		'click .description': 'edit',
		'click .submit': 'submit',
		'click .cancel': 'cancel'
	},
	initialize: function(opts) {
		this.doSlideIn = opts.slideIn;
		this.app = opts.app;
		this.listenTo(this.model, 'change', this.render);
		this.listenTo(this.model, 'destroy', this.onDestroy);
		this.listenTo(this.app.tags, 'sync', this.render);

		var that = this;
		this.tags = new Tags(
			this.model.get('tags').map(function(id) {
				return that.app.tags.get(id);
			})
		);
		this.listenTo(this.tags, 'add remove', this.submitTags);
		this.listenTo(
			Radio.channel('documentClicked'),
			'click',
			this.onDocumentClicked
		);
	},
	onRender: function() {
		this.getUI('preview').css(
			'background-image',
			'url(bookmark/' + this.model.get('id') + '/image)'
		);
		this.getUI('preview').css('background-color', this.model.getColor());

		this.showChildView(
			'tags',
			new TagsSelectionView({
				collection: this.app.tags,
				selected: this.tags,
				app: this.app
			})
		);

		if (this.savingState === 'saving') {
			this.getUI('status')
				.removeClass('saved')
				.addClass('saving');
		}
		if (this.savingState === 'saved') {
			this.getUI('status')
				.addClass('saved')
				.removeClass('saving');
		}

		if (this.doSlideIn) {
			this.slideIn();
			this.doSlideIn = false;
		}
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

		var $el = this.$(e.target).closest('[data-attribute]');

		if ($el.prop('contenteditable') === true) {
			return;
		}

		switch ($el.data('attribute')) {
			case 'url':
				$el.text(this.model.get('url'));
			// fallthrough
			case 'title':
				$el.on('keydown', function(e) {
					// enter
					if (e.which === 13) {
						that.submit($el);
					}
				});
			case 'description':
				if ($el.hasClass('empty')) {
					$el.text('');
					$el.removeClass('empty');
				}
				break;
		}
		$el.prop('contenteditable', true);
		$el.one('blur', function() {
			that.submit($el);
		});
		$el.focus();
	},
	submitTags: function() {
		var that = this;
		this.savingState = 'saving';
		this.app.tags.add(this.tags.models);
		this.model.set({
			tags: this.tags.pluck('name')
		});
		this.model.once('sync', function() {
			that.savingState = 'saved';
		});
		this.model.save();
	},
	submit: function($el) {
		var that = this;
		if (this.savingState === 'saving') {
			return;
		}
		this.savingState = 'saving';
		this.model.set({
			[$el.data('attribute')]: $el.text()
		});
		this.model.once('sync', function() {
			that.savingState = 'saved';
		});
		this.model.save();
	},
	onDestroy: function() {
		this.close();
	},
	onDocumentClicked: function(evt) {
		if (
			evt &&
			(this.el === evt.target ||
				$.contains(this.el, evt.target) ||
				!$.contains(document.body, evt.target))
		) {
			return;
		}
		this.close();
	},
	slideIn: function(cb) {
		this.$el.addClass('slide-in');
		if (cb) setTimeout(cb, 200);
	},
	slideOut: function(cb) {
		this.$el.addClass('slide-out');
		if (cb) setTimeout(cb, 200);
	}
});
