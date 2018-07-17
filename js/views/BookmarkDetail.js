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
	},
	onRender: function() {
		this.showChildView(
			'tags',
			new TagsSelectionView({
				collection: this.app.tags,
				selected: this.tags,
				app: this.app
			})
		);

		if (this.savingState === 'saving') {
			this.savingState = 'saved';
		}
		if (this.savingState === 'saved') {
			this.getUI('status').addClass('saved');
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
				break;
			case 'description':
				if ($el.hasClass('empty')) {
					$el.text('');
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
		this.app.tags.add(this.tags.models);
		this.model.set({
			tags: this.tags.pluck('name')
		});
		this.model.save({ wait: true });
		this.savingState = 'saving';
		this.getUI('status')
			.removeClass('saved')
			.addClass('saving');
	},
	submit: function($el) {
		if (this.savingState === 'saving') {
			return;
		}
		this.savingState = 'saving';
		this.model.set({
			[$el.data('attribute')]: $el.text()
		});
		this.model.save({ wait: true });
		this.getUI('status')
			.removeClass('saved')
			.addClass('saving');
	},
	onDestroy: function() {
		this.close();
	}
});
