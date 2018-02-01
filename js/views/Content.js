import Backbone from 'backbone'
import Bookmarks from '../models/Bookmarks'
import MobileNavView from './views/MobileNav'
import BulkActionsView from './views/BulkActions'
import BookmarksView from './views/Bookmarks'
import BookmarkDetailView from './views/BookmarkDetail'

const Marionette = Backone.Marionette
const Radio = Backbone.Radio

export default Marionette.View.extend({
  template: _.template('<div id="mobile-nav-slot"></div><div id="bulk-actions-slot"></div><div id="view-bookmarks-slot"></div><div id="bookmark-detail-slot"></div>')
, regions: {
    'mobileNav': {
      el: '#mobile-nav-slot'
    , replaceElement: true
    }
  , 'bulkActions': {
      el: '#bulk-actions-slot'
    , replaceElement: true
    }
  , 'viewBookmarks': {
      el: '#view-bookmarks-slot'
    , replaceElement: true
    }
  , 'bookmarkDetail': {
      el: '#bookmark-detail-slot'
    , replaceElement: true
    }
  }
, initialize: function(options) {
    this.bookmarks = options.bookmarks
    this.selected = new Bookmarks
    this.listenTo(this.bookmarks, 'select', this.onSelect)
    this.listenTo(this.bookmarks, 'unselect', this.onUnselect)
    this.listenTo(Radio.channel('nav'), 'navigate', this.onNavigate)
    this.listenTo(Radio.channel('details'), 'show', this.onShowDetails)
    this.listenTo(Radio.channel('details'), 'edit', this.onEditDetails)
    this.listenTo(Radio.channel('details'), 'close', this.onCloseDetails)
  }
, onRender: function() {
    this.showChildView('mobileNav', new MobileNavView())
    this.showChildView('bulkActions', new BulkActionsView({all: this.bookmarks, selected: this.selected}))
    this.showChildView('viewBookmarks', new BookmarksView({collection: this.bookmarks}));
  }
, onSelect: function(model) {
    if (this.selected.length == 0) {
      this.$el.addClass('selection-active')
	}
	this.selected.add(model)
  }
, onUnselect: function(model) {
    if (this.selected.length == 1) {
      this.$el.removeClass('selection-active')
	}
    this.selected.remove(model)
  }
, onShowDetails: function(model) {
    var that = this
    var view = new BookmarkDetailView({model: model})
    this.showChildView('bookmarkDetail', view)
  }
, onEditDetails: function(model) {
    this.onShowDetails(model)
    this.getRegion('bookmarkDetail').currentView.setEditing(true)
  }
, onCloseDetails: function() {
    this.detachChildView('bookmarkDetail')
  }
})
