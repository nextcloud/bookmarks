import Backbone from 'backbone';
import Bookmark from './Bookmark';

export default Backbone.Collection.extend({
	model: Bookmark,
	url: 'bookmark',
});
