var Backbone = OC.Backbone

var Bookmark = Backbone.Model.extend({
  defaults: {
    id: '',
    url: '',
    title: '',
    description: '',
    tags: Tags
  }
})

var Bookmarks = Backbone.Collection.extend({
  model: Bookmark
, url: 'bookmark'
})

var Tag = Backbone.Model.extend({
  id: '',
  title: ''
})

var Tags = Backbone.Collection.extend({
  model: Tag
, url: 'tag'
})
var BookmarkCardView = Marionette.View.extend({
  template: _.template('<h1><%- title %></h1><h2><%- new URL(url).host %></h2>'),
  tagName: "div",
  className: "bookmark-card",
  events: {
    "click": "open",
  },
  initialize: function() {
    this.listenTo(this.model, "change", this.render);
  },
})

var BookmarksView = Marionette.CollectionView.extend({
  tagName: "div"
, className: 'bookmarks'
, childView: BookmarkCardView
, emptyView: EmptyBookmarksView
})

var EmptyBookmarksView = Marionette.CollectionView.extend({
  template: _.template('<h2>No bookmarks, here.</h2><p>There are no bookmarks available for this query. Try adding some using the above form.</p>')
, tagName: "div"
, className: 'bookmarks-empty'
})


var _sync = Backbone.sync
Backbone.sync = function(method, model, options) {
  _sync(method, model, _.extend({}, options, {
    success: function(json) {
      console.log(json)
      if (!(model instanceof Tags)) options.success(json.data)
      else options.success(json)
    }
  }))
}

var bookmarks = new Bookmarks
var view = new BookmarksView({collection: bookmarks})
bookmarks.fetch({success: function() {
  view.render()
}})

$(function() {
  $('#app-content').append(view.el)
})
