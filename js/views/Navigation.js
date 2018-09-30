import _ from 'underscore';
import Backbone from 'backbone';
import TagsManagementView from './TagsManagement';
import FoldersView from './Folders';
import templateString from '../templates/Navigation.html';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.View.extend({
	className: 'navigation',
	tagName: 'ul',
	template: _.template(templateString),
	events: {
		'click .all': 'onClick',
		'click .untagged': 'onClick',
		'click .favorites': 'onClick',
		'click .shared': 'onClick',
		'click .folders': 'onClick',
		'click .tags': 'onClick'
	},
	regions: {
		folders: {
			el: '#folders-slot',
			replaceElement: true
		},
		tags: {
			el: '#favorite-tags-slot',
			replaceElement: true
		}
	},
	initialize: function(opt) {
		this.app = opt.app;
		this.listenTo(Radio.channel('nav'), 'navigate', this.onNavigate, this);
	},
	onRender: function() {
		this.showChildView(
			'folders',
			new FoldersView({ collection: this.app.folders, app: this.app })
		);
		this.showChildView(
			'tags',
			new TagsManagementView({ collection: this.app.tags })
		);
	},
	onClick: function(e) {
		e.preventDefault();
		var $li = this.$(e.target).closest('li');
		if ($li.hasClass('collapsible')) {
			$li
				.siblings()
				.removeClass('open')
				.removeClass('active');
			$li.addClass('active');
			$li.toggleClass('open');
			return;
		}
		Backbone.history.navigate(e.target.parentNode.dataset.id, {
			trigger: true
		});
	},
	onNavigate: function(category) {
		$('.active', this.$el).removeClass('active');
		var $li = this.$('[data-id=' + category + ']');
		if (category && $li.length) {
			$li.addClass('active');
			if ($li.hasClass('collapsible')) {
				$li.siblings().removeClass('open');
				$li.addClass('open');
			}
		}
	}
});
