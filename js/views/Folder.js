import _ from 'underscore';
import Backbone from 'backbone';
import interact from 'interactjs';
import templateString from '../templates/Folder.html';
import FoldersView from './Folders';

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
		'click .menu-delete': 'actionDelete',
		'click .menu-edit': 'actionEdit',
		'click .menu-move': 'actionMove',
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
		interact(this.el).dropzone({
			ondrop: this.onDrop.bind(this),
			ondropactivate: this.onDropActivate.bind(this),
			ondropdeactivate: this.onDropDeactivate.bind(this)
		});
	},
	onRender: function() {
		if (this.model.get('children')) {
			this.$el.addClass('collapsible');
		} else {
			this.$el.removeClass('collapsible');
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
	actionMove: function() {},
	actionDelete: function() {
		this.model.destroy();
	},
	actionEdit: function() {
		this.editing = true;
		this.render();
	},
	onKeyup: function(e) {
		if (e.which === 13) {
			this.actionSubmit();
		}
	},
	actionSubmit: function() {
		this.model.set('title', this.$('input.title').val());
		this.model.save();
		this.actionCancel();
	},
	actionCancel: function() {
		this.editing = false;
		this.render();
	},
	onDropActivate: function(e) {
		if (this.$el.hasClass('active')) return;
		this.$el.addClass('droptarget');
	},
	onMouseOver: function() {
		if (this.$el.hasClass('droptarget')) this.showChildren();
	},
	onMouseOut: function() {
		if (this.$el.hasClass('droptarget')) this.toggleChildren();
	},
	onDropDeactivate: function(e) {
		this.$el.removeClass('droptarget');
	},
	onDrop: function(e) {
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
		e.draggable.model.once('sync', function() {
			setTimeout(function() {
				that.quiver();
			}, 500);
		});
	},
	quiver: function() {
		var that = this;
		that.$el.addClass('quiver-vertically');
		setTimeout(function() {
			that.$el.removeClass('quiver-vertically');
		}, 600);
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
	}
});
