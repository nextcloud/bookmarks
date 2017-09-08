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
  defaults: {
    id: ''
  , name: ''
  }
})

var Tags = Backbone.Collection.extend({
  model: Tag
, url: 'tag'
})


var AppView = Marionette.View.extend({
  template: _.template('<div id="app-navigation"></div><div id="app-content"></div>')
, regions: {
    'navigation': '#app-navigation'
  , 'content': '#app-content'
  }
, initialize: function() {
    this.bookmarks = new Bookmarks
    this.bookmarks.fetch()
    this.tags = new Tags
    this.tags.fetch()
  }
, onRender: function() {
    this.showChildView('navigation', new NavigationView({tags: this.tags}));
    this.showChildView('content', new ContentView({bookmarks: this.bookmarks})); 
  }
})

var NavigationView = Marionette.View.extend({
  className: 'navigation'
, template: _.template('<ul><li data-id="all" class="all"><a href="#">All bookmarks</a></li><li data-id="facorites" class="favorites"><a href="#">Favorites</a></li><li data-id="shared" class="shared"><a href="#">Shared</a></li><li data-id="tags" class="tags"><a href="#">Tags</a></li></ul>Favorite tags<div id="favorite-tags-slot"></div>')
, events: {
    'click .all': 'onClick'
  , 'lick .favorites': 'onClick'
  , 'click .shared': 'onClick'
  }
, regions: {
    'tags': '#favorite-tags-slot'
  }
, initialize: function(options) {
    this.tags = options.tags
  }
, onClick: function(e) {
    e.preventDefault()
    this.triggerMethod('navigation:open', e.target.parentNode.dataset.id)
  }
, onNavigationOpen: function(category) {
    $('.active', this.$el).removeClass('active')
    $('.'+category, this.$el).addClass('active')
  }
, onRender: function() {
    this.showChildView('tags', new TagsNavigationView({collection: this.tags}))
  }
})

var TagsNavigationView = Marionette.CollectionView.extend({
  tagName: 'ul'
, childView: function() {return TagsNavigationTagView}
})

var TagsNavigationTagView = Marionette.View.extend({
  className: 'tag-nav-item'
, tagName: 'li'
, template: _.template('<li><a href="#"><%- name %></a></li>')
})

var ContentView = Marionette.View.extend({
  template: _.template('<div id="add-bookmark-slot"></div><div id="view-bookmarks-slot"></div>')
, regions: {
    'addBookmarks':  {
      el: '#add-bookmark-slot'
    , replaceElement: true
    }
  , 'viewBookmarks': {
      el: '#view-bookmarks-slot'
    , replaceElement: true
    }
  }
, initialize: function(options) {
    this.bookmarks = options.bookmarks
  }
, onRender: function() {
    this.showChildView('addBookmarks', new AddBookmarkView());
    this.showChildView('viewBookmarks', new BookmarksView({collection: this.bookmarks}));
  }
})





var AddBookmarkView = Marionette.View.extend({
  template: _.template('<input type="text" value="" placeholder="Address"/><button title="Add" class="icon-add"></button>')
, className: 'add-bookmark'
})



var BookmarksView = Marionette.CollectionView.extend({
  className: 'bookmarks'
, childView: function() {return BookmarkCardView}
, emptyView: function() {return EmptyBookmarksView}
})

var EmptyBookmarksView = Marionette.View.extend({
  template: _.template('<h2>No bookmarks, here.</h2><p>There are no bookmarks available for this query. Try adding some using the above form.</p>')
, className: 'bookmarks-empty'
})

var BookmarkCardView = Marionette.View.extend({
  template: _.template('<h1><%- title %></h1><h2><%- new URL(url).host %></h2>'),
  className: "bookmark-card",
  events: {
    "click": "open",
  },
  initialize: function() {
    this.listenTo(this.model, "change", this.render);
  },
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

// init

var view = new AppView()
$(function() {
  $('#content').append(view.render().el)
})
