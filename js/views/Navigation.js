import _ from 'underscore';
import Backbone from 'backbone';
import TagsManagementView from './TagsManagement';
import FoldersView from './Folders';
import Folder from '../models/Folder';
import interact from 'interactjs';
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
		this.interactable = interact(this.el).dropzone({
			ondrop: this.onDrop.bind(this),
			ondropactivate: this.onDropActivate.bind(this),
			ondropdeactivate: this.onDropDeactivate.bind(this)
		});
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
		if (e.target.parentNode.dataset.id === 'folder') {
			$li
				.siblings()
				.removeClass('open')
				.removeClass('active');
			$li.addClass('active');
			$li.addClass('open');
			Backbone.history.navigate('folder/-1', { trigger: true });
			return;
		}
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
	},
	onDropActivate: function(e) {
		if (this.$el.hasClass('active')) return;
		this.$el.addClass('droptarget');
	},
	onDropDeactivate: function(e) {
		this.$el.removeClass('droptarget');
	},
	onDrop: function(e) {
		if (e.draggable.model instanceof Folder) {
			this.onDropFolder(e);
		} else {
			this.onDropBookmark(e);
		}
	},
	onDropBookmark: function(e) {
		var that = this;
		if (this.app.selectedBookmarks.length) {
			this.app.selectedBookmarks.models.slice().forEach(function(bm, i) {
				bm.trigger('unselect');
				that.moveBookmark(bm);
				// quiver only once
				if (i === that.app.selectedBookmarks.length - 1) {
					bm.once('sync', function() {
						setTimeout(function() {
							that.quiver();
						}, 500);
					});
				}
			});
			this.app.selectedBookmarks.reset();
			return;
		}
		this.moveBookmark(e.draggable.model);
	},
	onDropFolder: function(e) {
		var that = this;
		this.moveFolder(e.draggable.model);
		e.draggable.model.once('sync', function() {
			setTimeout(function() {
				that.app.folders.fetch({ reset: true });
			}, 500);
		});
	},
	moveFolder: function(folder) {
		folder.set('parent_folder', -1);
		folder.save();
	},
	moveBookmark: function(bm) {
		var that = this;
		var folders = bm.get('folders'),
			isInsideFolder =
				'undefined' !==
				typeof this.app.bookmarks.loadingState.get('query').folder;
		if (isInsideFolder) {
			if (this.app.bookmarks.loadingState.get('query').folder === '-1') {
				return;
			}
			folders = _.without(
				folders,
				this.app.bookmarks.loadingState.get('query').folder
			);
		}
		folders.push(-1);
		bm.set('folders', folders);
		if (isInsideFolder) {
			bm.once('sync', function() {
				that.app.bookmarks.remove(bm);
			});
		}
		bm.save();
	}
});
