var Radio = Backbone.Radio

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


var App = Marionette.Application.extend({
  region: '#content'
, onBeforeStart: function() {
    this.bookmarks = new Bookmarks
    this.tags = new Tags
    this.tags.fetch()

    this.router = new Router({app: this})
  }
, onStart: function() {
    this.showView(new AppView({bookmarks: this.bookmarks, tags: this.tags}));
    Backbone.history.start();
  }
});

var Router = Marionette.AppRouter.extend({
  controller: {
    showAllBookmarks: function() {
      this.app.bookmarks.fetch()
    }
  , showFavoriteBookmarks: function() {
    }
  , showSharedBookmarks: function() {
      this.app.bookmarks.fetch()
    }
  , showTags: function() {
    }
  , showTag: function(tag) {
      this.app.bookmarks.fetch({
        data: {tags: [tag]}
      })
    }
  }
, appRoutes: {
    'all': 'showAllBookmarks'
  , 'favorites': 'showFavoriteBookmarks'
  , 'shared': 'showSharedBookmarks'
  , 'tags': 'showTags'
  , 'tag/:tag': 'showTag'
  }
, initialize: function(options) {
    this.controller.app = options.app
  }
, onRoute: function(name, path, args) {
    Radio.channel('nav').trigger('navigate', path, args)
  }
})

var AppView = Marionette.View.extend({
  template: _.template('<div id="app-navigation"><div id="navigation-slot"></div><h3>Favorite tags</h3><div id="favorite-tags-slot"></div></div><div id="app-content"><div id="content-slot"></div></div>')
, regions: {
    'navigation': {
      el: '#navigation-slot'
    , replaceElement: true
    }
  , 'content': {
      el: '#content-slot'
    , replaceElement: true
    }
  , 'tags': {
      el: '#favorite-tags-slot'
    , replaceElement: true
    }
  }
, initialize: function(options) {
    this.bookmarks = options.bookmarks
    this.tags = options.tags
  }
, onRender: function() {
    this.showChildView('navigation', new NavigationView);
    this.showChildView('content', new ContentView({bookmarks: this.bookmarks})); 
    this.showChildView('tags', new TagsNavigationView({collection: this.tags}))
  }
})


var NavigationView = Marionette.View.extend({
  className: 'navigation'
, tagName: 'ul'
, template: _.template('<li data-id="all" class="all"><a href="#">All bookmarks</a></li><li data-id="favorites" class="favorites"><a href="#">Favorites</a></li><li data-id="shared" class="shared"><a href="#">Shared</a></li><li data-id="tags" class="tags"><a href="#">Tags</a></li>')
, events: {
    'click .all': 'onClick'
  , 'click .favorites': 'onClick'
  , 'click .shared': 'onClick'
  , 'click .tags': 'onClick'
  }
, initialize: function() {
    this.listenTo(Radio.channel('nav'), 'navigate', this.onNavigate, this)
  }
, onClick: function(e) {
    e.preventDefault()
    Backbone.history.navigate(e.target.parentNode.dataset.id, {trigger: true})
  }
, onNavigate: function(category) {
    $('.active', this.$el).removeClass('active')
    if (category && category.indexOf('tag/') !== 0) $('.'+category, this.$el).addClass('active')
  }
})

var TagsNavigationView = Marionette.CollectionView.extend({
  tagName: 'ul'
, childView: function() {return TagsNavigationTagView}
})

var TagsNavigationTagView = Marionette.View.extend({
  className: 'tag-nav-item'
, tagName: 'li'
, template: _.template('<a href="#"><i><%- name %></a></i>')
, events: {
    'click': 'open'
  }
, initialize: function() {
    this.listenTo(Radio.channel('nav'), 'navigate', this.onNavigate, this)
  }
, open: function(e) {
    e.preventDefault()
    Backbone.history.navigate('tag/' + this.model.get('name'), {trigger: true});
  }
, onNavigate: function(category, args) {
    this.$el.removeClass('active')
    if (category && category.indexOf('tag/') === 0 && args[0] === this.model.get('name')) {
      this.$el.addClass('active')
    }
  }
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
      else options.success(json.map(function(name){return {name: name}}))
    }
  }))
}

// init

var app = new App()
$(function() {
  app.start()
})
