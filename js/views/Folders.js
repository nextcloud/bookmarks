import Backbone from 'backbone';
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
		this.parentFolder = options.parentFolder;
		this.selectedFolder = options.selectedFolder;
	},
	childViewOptions: function() {
		return {
			selectedFolder: this.selectedFolder
		};
	},
	onRender: function() {
		this.addChildView(
			new AddFolderView({
				parentFolder: this.parentFolder,
				collection: this.collection
			}),
			this.collection.length
		);
	}
});
