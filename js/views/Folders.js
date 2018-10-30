import Backbone from 'backbone';
import interact from 'interactjs';
import Folder from '../models/Folder';
import Folders from '../models/Folders';
import FolderView from './Folder';
import AddFolderView from './AddFolder';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.CollectionView.extend({
	childView: FolderView,
	tagName: 'ul',
	className: 'folders',
	initialize: function(options) {
		this.app = options.app;
		this.parentFolder = options.parentFolder;
		this.selectedFolder = options.selectedFolder;
		if (!this.parentFolder) {
			this.parentFolder = new Folder({
				id: '-1',
				title: t('bookmarks', 'Uncategorized')
			});
			this.isRootFolder = true;
		}
	},
	childViewOptions: function() {
		return {
			selectedFolder: this.selectedFolder,
			app: this.app
		};
	},
	onRender: function() {
		var length = this.collection.length;
		if (this.isRootFolder) {
			this.addChildView(
				new RootFolderView({ app: this.app, model: this.parentFolder }),
				0
			);
			length++;
		}

		this.addChildView(
			new AddFolderView({
				parentFolder: this.parentFolder,
				collection: this.collection
			}),
			length + 1
		);
	}
});

var RootFolderView = FolderView.extend({
	initInteractable: function() {
		this.interactable = interact(this.el).dropzone({
			ondrop: this.onDrop.bind(this),
			ondropactivate: this.onDropActivate.bind(this),
			ondropdeactivate: this.onDropDeactivate.bind(this)
		});
		this.interactable.model = this.model;
	},
	onRender: function() {
		this.$el.removeClass('active');
		if (this.selectedFolder == this.model.get('id')) {
			this.$el.addClass('active');
		}
		this.$('.app-navigation-entry-utils').hide();
		this.$('.collapse').hide();
	},
	onMouseOver: function() {},
	onMouseOut: function() {}
});
