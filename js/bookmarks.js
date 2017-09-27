var Radio = Backbone.Radio

var Bookmark = Backbone.Model.extend({
  defaults: {
    /*url: '',
    title: '',
    description: '',*/
    tags: Tags
  }
, url: 'bookmark'
})

var Bookmarks = Backbone.Collection.extend({
  model: Bookmark
, url: 'bookmark'
})

var Tag = Backbone.Model.extend({
  defaults: {
    name: ''
  }
, url: 'tag'
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
  , showBookmark: function() {
    }
  , search: function(query) {
      this.app.bookmarks.fetch({
        data: {search: query.split(' ')}
      })
    }
  }
, appRoutes: {
    'all': 'showAllBookmarks'
  , 'favorites': 'showFavoriteBookmarks'
  , 'shared': 'showSharedBookmarks'
  , 'tags': 'showTags'
  , 'tag/:tag': 'showTag'
  , 'bookmark/:bookmark': 'showBookmark'
  , 'search/:query': 'search'
  }
, initialize: function(options) {
    this.controller.app = options.app
  }
, onRoute: function(name, path, args) {
    Radio.channel('nav').trigger('navigate', path, args)
  }
})

var AppView = Marionette.View.extend({
  template: _.template('<div id="app-navigation"><div id="add-bookmark-slot"></div><div id="navigation-slot"></div><h3>Favorite tags</h3><div id="favorite-tags-slot"></div></div><div id="app-content"><div id="content-slot"></div></div>')
, regions: {
    'addBookmarks':  {
      el: '#add-bookmark-slot'
    , replaceElement: true
    }
  , 'navigation': {
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
    this.searchController = new SearchController
  }
, onRender: function() {
    this.showChildView('addBookmarks', new AddBookmarkView());
    this.showChildView('navigation', new NavigationView);
    this.showChildView('content', new ContentView({bookmarks: this.bookmarks})); 
    this.showChildView('tags', new TagsNavigationView({collection: this.tags}))
  }
})


var SearchController = Marionette.View.extend({
  el: '#searchbox'
, initialize: function() {
    var that = this
    // register a dummy search plugin
    OC.Plugins.register('OCA.Search', { attach: function(search) {
        search.setFilter('bookmarks', function(query) {
          that.submit(query)
        })
      }
    });
    this.listenTo(Radio.channel('nav'), 'navigate', this.onNavigate, this)
  }
, events: {
    'keydown': 'onKeydown'
  }
, onRender: function() {
    this.$el.show()
  }
, onNavigate: function(route, query) {
    if (route === 'search/:query') this.$el.val(query)
  }
, submit: function(query) {
    if (query !== '') {
      Backbone.history.navigate('search/'+query)
      app.router.controller.search(query)
    }else {
      Backbone.history.navigate('all')
      app.router.controller.showAllBookmarks()
    }
  }
})

var AddBookmarkView = Marionette.View.extend({
  template: _.template('<input type="text" value="" placeholder="Address"/><button title="Add" class="icon-add"></button>')
, className: 'add-bookmark'
, events: {
    'click button': 'submit'
  , 'keydown input': 'onKeydown'
  }
, ui: {
    'input': 'input'
  , 'button': 'button'
  }
, onKeydown: function(e) {
    if (e.which != 13) return
    // Enter
    this.submit()
  }
, submit: function() {
    if (this.pending) return
    var $input = this.getUI('input')
    var url = $input.val()
    var bm = new Bookmark({url: url})
    this.setPending(true)
    var that = this
    bm.save(null,{
      success: function() {
      Backbone.history.navigate('all', {trigger: true})
      app.bookmarks.fetch()
      $input.val('')
      that.setPending(false)
    }
    , error: function() {
        that.setPending(false)
        that.getUI('button').removeClass('icon-add')
        that.getUI('button').addClass('icon-error-color')
      }
    })
  }
, setPending: function(pending) {
    if (pending) {
      this.getUI('button').removeClass('icon-add')
      this.getUI('button').removeClass('icon-error-color')
      this.getUI('button').addClass('icon-loading-small')
      this.getUI('button').prop('disabled', true)
    }else {
      this.getUI('button').removeClass('icon-error-color')
      this.getUI('button').addClass('icon-add')
      this.getUI('button').removeClass('icon-loading-small')
      this.getUI('button').prop('disabled', false) 
    }
    this.pending = pending
  }
})
var nav_ids = {
  all: true
, favorites: true
, shared: true
, tags: true
}
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
    if (category && nav_ids[category]) $('.'+category, this.$el).addClass('active')
  }
})

var TagsNavigationView = Marionette.CollectionView.extend({
  tagName: 'ul'
, childView: function() {return TagsNavigationTagView}
})

var TagsNavigationTagView = Marionette.View.extend({
  className: 'tag-nav-item'
, tagName: 'li'
, template: _.template('<a href="#"><%- name %></a>')
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
  template: _.template('<div id="bulk-actions-slot"></div><div id="view-bookmarks-slot"></div><div id="bookmark-detail-slot"></div>')
, regions: {
    'bulkActions': {
      el: '#bulk-action-slot'
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
    var selected = new Bookmarks
    this.bookmarks.on('select', function(model) {
      selected.add(model)
      if (selected.models.length == 1) this.showChildView('bulkActions', new BulkActionsView({collection: selected}))
    })
    this.bookmarks.on('unselect', function(model) {
      selected.remove(model)
      if (selected.models.length == 0) this.detachChildView('bulkActions')
    })
    this.listenTo(Radio.channel('nav'), 'navigate', this.onNavigate, this) // Turn this into a request!
  }
, onRender: function() {
    this.showChildView('viewBookmarks', new BookmarksView({collection: this.bookmarks}));
  }
, onNavigate: function(path, args) {
    if ('bookmark/:bookmark' === path) {
      var bm = app.bookmarks.get(args[0])
      var view = new BookmarkDetailView({model: bm})
      this.showChildView('bookmarkDetail', view)
      var that = this
      view.on('close', function() {
        that.detachChildView('bookmarkDetail')
      })
    }
  }
})



var BulkActionsView = Marionette.View.extend({
  className: 'bulk-actions'
, template: _.template('<button class="delete icon-delete"></button>')
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
  template: _.template('<input type="checkbox"/><h1><img src="<%- "//:"+new URL(url).host+"/favicon.ico" %>"/><%- title %></h1><h2><a href="<%- url %>"><%- new URL(url).host %></a></h2>'),
  className: "bookmark-card",
  ui: {
    "checkbox": 'input[type="checkbox"]'
  },
  events: {
    "click": "open",
    "click @ui.checkbox": "select"
  },
  initialize: function() {
    this.listenTo(this.model, "change", this.render);
  }
, open: function() {
    Backbone.history.navigate('bookmark/'+this.model.get('id'), {trigger: true})
  }
, select: function(e) {
    e.stopPropagation()
    if (this.$el.hasClass('active')) {
      this.triggerMethod('unselecct', this.model)
    }else{
      this.triggerMethod('select', this.model)
    }
    this.$el.toggleClass('active')
  }
})


var BookmarkDetailView = Marionette.View.extend({
  template: _.template('<div class="actions"><button class="edit icon-rename"></button><button class="delete icon-delete"></button></div><h1><%- title %></h1><h2><a href="<%- url %>"><%- new URL(url).host %></a></h2><div class="close icon-close"></div>'),
  className: "bookmark-detail",
  ui: {
    'close': '.close'
  , 'edit': '.edit'
  , 'delete': '.delete'
  },
  events: {
    'click @ui.close': 'close'
  , 'click @ui.edit': 'edit'
  , 'click @ui.delete': 'delete'
  },
  initialize: function() {
    this.listenTo(this.model, "change", this.render);
  },
  close: function() {
    Backbone.history.history.back();
    this.triggerMethod('close')
  }
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
