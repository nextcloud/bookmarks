import Backbone from 'backbone'
import BookmarkCardView from './BookmarkCard'
import EmptyBookmarksView from './EmptyBookmarks'

const Marionette = Backone.Marionette
const Radio = Backbone.Radio

export default Marionette.CollectionView.extend({
  className: 'bookmarks'
, regions: {

  }
, childView: function() {return BookmarkCardView}
, emptyView: function() {return EmptyBookmarksView}
})
