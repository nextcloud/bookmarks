import _ from 'underscore';
import Backbone from 'backbone';
import TagsManagementView from './TagsManagement';
import FoldersView from './Folders';
import AddBookmarkView from './AddBookmark';
import SettingsView from './Settings';
import Folder from '../models/Folder';
import interact from 'interactjs';
import templateString from '../templates/Navigation.html';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.View.extend({
	className: 'navigation',
	id: 'app-navigation',
	tagName: 'div',
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
		addBookmarks: {
			el: '#add-bookmark-slot',
			replaceElement: true
		},
		folders: {
			el: '#folders-slot',
			replaceElement: true
		},
		tags: {
			el: '#favorite-tags-slot',
			replaceElement: true
		},
		settings: {
			el: '#settings-slot',
			replaceElement: true
		}
	},
	initialize: function(opt) {
		this.app = opt.app;
		this.listenTo(Radio.channel('nav'), 'navigate', this.onNavigate, this);
	},
	onRender: function() {
		this.showChildView('addBookmarks', new AddBookmarkView());
		this.showChildView(
			'folders',
			new FoldersView({
				collection: this.app.folders,
				app: this.app,
				selectedFolder: this.selectedFolder
			})
		);
		this.showChildView(
			'tags',
			new TagsManagementView({ collection: this.app.tags })
		);
		this.showChildView(
			'settings',
			new SettingsView({ app: this.app, model: this.app.settings })
		);
	},
	onClick: function(e) {
		e.preventDefault();
		var $li = this.$(e.target).closest('li');
		if ($li.hasClass('collapsible')) {
			this.$('li')
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
	onNavigate: function(category, id) {
		$('.active', this.$el).removeClass('active');
		var $li = this.$('[data-id=' + category + ']');
		if (category && $li.length) {
			$li.addClass('active');
			if ($li.hasClass('collapsible')) {
				this.$('li').removeClass('open');
				$li.addClass('open');
			}
		}
		if (category === 'folder') {
			this.selectedFolder = id;
			this.render();
		}
	}
});
