import _ from 'underscore';
import Backbone from 'backbone';
import interact from 'interactjs';
import templateString from '../templates/Folder.html';
import FoldersView from './Folders';
import Folder from '../models/Folder';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.View.extend({
	className: 'folders-item',
	tagName: 'li',
	template: _.template(templateString),
	regions: {
		children: {
			el: '.children',
			replaceElement: true
		}
	},
	ui: {
		actionsMenu: '.app-navigation-entry-menu',
		actionsToggle: '.app-navigation-entry-utils-menu-button'
	},
	events: {
		'click > a': 'select',
		'click > .collapse': 'toggleChildren',
		'click @ui.actionsToggle': 'toggleActions',
		'click > .app-navigation-entry-menu .menu-delete': 'actionDelete',
		'click > .app-navigation-entry-menu .menu-edit': 'actionEdit',
		'click > .app-navigation-entry-menu .menu-addsub': 'actionAddSubFolder',
		'click .action.submit': 'actionSubmit',
		'click .action.cancel': 'actionCancel',
		'keyup input.title': 'onKeyup',
		mouseover: 'onMouseOver',
		mouseout: 'onMouseOut'
	},
	initialize: function(options) {
		this.app = options.app;
		this.selectedFolder = options.selectedFolder;
		this.listenTo(Radio.channel('nav'), 'navigate', this.onNavigate);
		this.listenTo(Radio.channel('documentClicked'), 'click', this.closeActions);
		this.listenTo(this.model, 'dropFolder', this.onDropFolder);
		this.listenTo(this.model, 'dropped', this.onDropped);
		this.initInteractable();
	},
	initInteractable: function() {
		this.interactable = interact(this.el)
			.dropzone({
				overlap: 'pointer',
				ignoreFrom: '.folders, input',
				ondrop: this.onDrop.bind(this),
				ondropactivate: this.onDropActivate.bind(this),
				ondropdeactivate: this.onDropDeactivate.bind(this)
			})
			.draggable({
				onstart: this.onDragStart.bind(this),
				onend: this.onDragEnd.bind(this),
				onmove: this.onDragMove.bind(this)
			});
		this.interactable.model = this.model;
	},
	onRender: function() {
		if (this.model.get('children') && this.model.get('children').length) {
			this.$el.addClass('collapsible');
		} else {
			this.$el.removeClass('collapsible');
			this.$('> .collapse').hide();
		}
		this.showChildView(
			'children',
			new FoldersView({
				collection: this.model.get('children'),
				parentFolder: this.model,
				selectedFolder: this.selectedFolder,
				app: this.app
			})
		);

		this.$el.removeClass('active');
		if (this.selectedFolder == this.model.get('id')) {
			this.$el.addClass('active');
		}
		if (this.model.contains(this.selectedFolder)) {
			this.showChildren();
		}

		this.$el.removeClass('editing');
		if (this.editing) {
			this.$el.addClass('editing');
			this.$('input').focus();
		}
	},
	select: function(e) {
		e.preventDefault();
		e.stopPropagation();
		if (this.editing) return;
		this.triggerRoute();
	},
	toggleChildren: function(e) {
		if (e) {
			e.stopPropagation();
			e.preventDefault();
		}
		if (this.editing) return;
		this.$el.toggleClass('open');
	},
	showChildren: function(e) {
		if (e) {
			e.stopPropagation();
			e.preventDefault();
		}
		this.$el.addClass('open');
	},
	onNavigate: function(category, folderId) {
		if (category !== 'folder') {
			return;
		}
		this.selectedFolder = folderId;
		this.render();
	},
	triggerRoute: function() {
		Backbone.history.navigate('folder/' + this.model.get('id'), {
			trigger: true
		});
	},
	toggleActions: function(e) {
		if (e) {
			e.stopPropagation();
			e.preventDefault();
		}
		this.getUI('actionsMenu').toggleClass('open');
	},
	closeActions: function(e) {
		if (this.editing || $.contains(this.getUI('actionsToggle')[0], e.target))
			return;
		this.getUI('actionsMenu').removeClass('open');
	},
	actionDelete: function(e) {
		if (e) {
			e.stopPropagation();
			e.preventDefault();
		}
		this.model.destroy();
	},
	actionEdit: function(e) {
		if (e) {
			e.stopPropagation();
			e.preventDefault();
		}
		this.editing = true;
		this.render();
	},
	onKeyup: function(e) {
		if (e.which === 13) {
			this.actionSubmit();
		}
	},
	actionSubmit: function(e) {
		if (e) {
			e.stopPropagation();
			e.preventDefault();
		}
		this.model.set('title', this.$('input.title').val());
		this.model.save();
		this.actionCancel();
	},
	actionCancel: function(e) {
		if (e) {
			e.stopPropagation();
			e.preventDefault();
		}
		this.editing = false;
		this.render();
	},
	actionAddSubFolder: function(e) {
		if (e) {
			e.stopPropagation();
			e.preventDefault();
		}
		this.model.trigger('addSubFolder'); // communicate to AddFolderView
		this.$el.addClass('collapsible');
		this.toggleActions();
		this.showChildren();
	},
	onDropActivate: function(e) {
		if (e.draggable.model instanceof Folder) {
			this.folderBeingDragged = e.draggable.model;
			this.$el.addClass('droptarget-folder');
			return;
		}
		if (this.$el.hasClass('active')) return;
		this.$el.addClass('droptarget-bookmark');
	},
	onMouseOver: function(e) {
		if (
			this.$el.hasClass('droptarget-bookmark') &&
			this.model.get('children').length
		) {
			this.showChildren();
		}
		if (this.folderBeingDragged) {
			this.showChildren();
		}
	},
	onMouseOut: function(e) {
		if (
			this.$el.hasClass('droptarget-bookmark') &&
			this.model.get('children').length
		) {
			this.toggleChildren();
		}

		// Only hide children if this is a folder AND the folder being dragged is not within this one
		if (
			this.folderBeingDragged &&
			!this.model.contains(this.folderBeingDragged.get('id'))
		) {
			this.toggleChildren();
		}
	},
	onDropDeactivate: function(e) {
		// TODO: Wait for 'sync' til we remove this
		this.folderBeingDragged = false;
		this.$el.removeClass('droptarget-bookmark');
		this.$el.removeClass('droptarget-folder');
	},
	onDrop: function(e) {
		if (!(e.draggable.model instanceof Folder)) {
			this.onDropBookmark(e);
		} else {
			this.onDropFolder(e);
		}
	},
	onDropBookmark: function(e) {
		var that = this;
		e.draggable.model.trigger('dropped');
		if (this.app.selectedBookmarks.length) {
			this.app.selectedBookmarks.models.slice().forEach(function(bm, i) {
				bm.trigger('unselect');
				that.moveBookmark(bm);
				// quiver only once
				if (i === that.app.selectedBookmarks.length - 1) {
					bm.once('sync', function() {
						that.quiver();
					});
				}
			});
			this.app.selectedBookmarks.reset();
			return;
		}
		this.moveBookmark(e.draggable.model);
		e.draggable.model.once('sync', function() {
			that.quiver();
		});
	},
	onDropFolder: function(e) {
		var that = this;
		e.draggable.model.trigger('dropped');
		e.draggable.model.once('sync', function() {
			that.quiver(function() {
				that.app.folders.fetch({ reset: true });
			});
		});
		this.moveFolder(e.draggable.model);
	},
	quiver: function(cb) {
		var that = this;
		that.$('> a').addClass('quiver-vertically');
		setTimeout(function() {
			that.$('> a').removeClass('quiver-vertically');
			cb && cb();
		}, 600);
	},
	moveFolder: function(folder) {
		folder.set('parent_folder', this.model.get('id'));
		folder.save();
	},
	moveBookmark: function(bm) {
		var that = this;
		var folders = bm.get('folders'),
			isInsideFolder =
				'undefined' !==
				typeof this.app.bookmarks.loadingState.get('query').folder;
		if (isInsideFolder) {
			if (
				this.app.bookmarks.loadingState.get('query').folder ===
				this.model.get('id')
			) {
				return;
			}
			folders = _.without(
				folders,
				this.app.bookmarks.loadingState.get('query').folder
			);
		}
		folders.push(this.model.get('id'));
		bm.set('folders', folders);
		if (isInsideFolder) {
			bm.once('sync', function() {
				that.app.bookmarks.remove(bm);
			});
		}
		bm.save();
	},
	onDragStart: function(e) {
		this.$el.addClass('dragging');
	},
	onDragMove: function(e) {
		this.$el.offset({ top: e.pageY + 5, left: e.pageX });
	},
	onDragEnd: function(e) {
		this.$el.removeClass('dragging');
		this.$el.css({ position: 'relative', top: 0, left: 0 });
	},
	onDropped: function() {
		var that = this;
		this.$el.hide();
	},
	onDestroy: function() {
		this.interactable.unset();
	}
});
