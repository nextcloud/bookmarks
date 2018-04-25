import Backbone from 'backbone';
import BookmarkCardView from './BookmarkCard';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.CollectionView.extend({
	className: 'bookmarks',
	initialize: function(opts) {
		this.app = opts.app;
	},
	childViewOptions: function() {
		return {app: this.app};
	},
	childView: function() {return BookmarkCardView;}
});
