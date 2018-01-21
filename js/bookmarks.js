var Radio = Backbone.Radio

var Bookmark = Backbone.Model.extend({
  defaults: {
    /*url: '',
    title: '',
    description: '',*/
    tags: Tags
  }
, urlRoot: 'bookmark'
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
    index: function() {
      setTimeout(function(){
        Backbone.history.navigate('all', {trigger: true})
      }, 1)
    }
  , all: function() {
      this.app.bookmarks.fetch()
      Radio.channel('nav').trigger('navigate', 'all')
    }
  , favorites: function() {
      Radio.channel('nav').trigger('navigate', 'favorites')
    }
  , shared: function() {
      this.app.bookmarks.fetch()
      Radio.channel('nav').trigger('navigate', 'shared')
    }
  , tags: function(tagString) {
      var tags = tagString? tagString.split(',').map(decodeURIComponent) : [] 
      this.app.bookmarks.fetch({
        data: {item: {tags: tags}}
      })
      Radio.channel('nav').trigger('navigate', 'tags', tags)
    }
  , tag: function(tag) {
      this.app.bookmarks.fetch({
        data: {item: {tags: [decodeURIComponent(tag)]}}
      })
      Radio.channel('nav').trigger('navigate', 'tag', tag)
    }
  , search: function(query) {
      this.app.bookmarks.fetch({
        data: {search: decodeURIComponent(query).split(' ')}
      })
      Radio.channel('nav').trigger('navigate', 'search', query)
    }
  }
, appRoutes: {
    '': 'index'
  , 'all': 'all'
  , 'favorites': 'favorites'
  , 'shared': 'shared'
  , 'tags(/*tags)': 'tags'
  , 'tag/:tag': 'tag'
  , 'search/:query': 'search'
  }
, initialize: function(options) {
    this.controller.app = options.app
  }
})

var AppView = Marionette.View.extend({
  template: _.template('<div id="app-navigation"><div id="add-bookmark-slot"></div><div id="navigation-slot"></div><h3>Favorite tags</h3><div id="favorite-tags-slot"></div><div id="settings-slot"></div></div><div id="app-content"><div id="content-slot"></div></div>')
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
  , 'settings': {
      el: '#settings-slot'
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
    this.showChildView('settings', new SettingsView())
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
    if (route === 'search/:query') this.$el.val(decodeURIComponent(query))
  }
, submit: function(query) {
    if (query !== '') {
      query = encodeURIComponent(query)
      Backbone.history.navigate('search/'+query, {trigger: true})
    }else {
      Backbone.history.navigate('all', {trigger: true})
    }
  }
})

var AddBookmarkView = Marionette.View.extend({
  template: _.template('<li class="link"><a href="#"><span>Add Bookmark</span></a></li><li class="form"><input type="text" value="" placeholder="Address..."/><button title="Add" class="icon-add"></button></li>')
, className: 'add-bookmark'
, tagName: 'ul'
, events: {
    'click @ui.link': 'activate'
  , 'click @ui.button': 'submit'
  , 'keydown @ui.input': 'onKeydown'
  , 'blur @ui.input': 'deactivate'
  }
, ui: {
    'link': '.link a'
  , 'linkEntry': '.link'
  , 'formEntry': '.form'
  , 'input': 'input'
  , 'button': 'button'
  }
, activate: function() {
    this.getUI('linkEntry').hide()
    this.getUI('formEntry').show()
    this.getUI('input').focus()
  }
, deactivate: function() {
    this.getUI('linkEntry').show()
    this.getUI('formEntry').hide()
    this.getUI('input').val('')
  }
, onKeydown: function(e) {
    if (e.which != 13) return
    // Enter
    this.submit()
  }
, submit: function(e) {
    var $input = this.getUI('input')
    if (this.pending || $input.val() === '') return
    var url = $input.val()
    var bm = new Bookmark({url: url})
    this.setPending(true)
    var that = this
    bm.save(null,{
      success: function() {
				Backbone.history.navigate('all', {trigger: true})
				app.bookmarks.fetch()
				that.setPending(false)
        that.deactivate()
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
, template: _.template('<li data-id="all" class="all"><a href="#"><span class="icon-home"></span>All bookmarks</a></li><li data-id="favorites" class="favorites"><a href="#"><span class="icon-favorite"></span>Favorites</a></li><li data-id="shared" class="shared"><a href="#"><span class="icon-share"></span>Shared</a></li><li data-id="tags" class="tags"><a href="#"><span class="icon-tag"></span>Tags</a></li>')
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
    e.stopPropagation() // for when tags are displayed in BookmarkCardView, we don't want that view to get the click, too
    Backbone.history.navigate('tag/' + encodeURIComponent(this.model.get('name')), {trigger: true});
  }
, onNavigate: function(category, tag) {
    this.$el.removeClass('active')
    if (category === 'tag' && decodeURIComponent(tag) === this.model.get('name')) {
      this.$el.addClass('active')
    }
  }
})

var SettingsView = Marionette.View.extend({
  className: 'settings'
, id: 'app-settings'
, template: _.template('<div id="app-settings-header"><button class="settings-button">Settings</a></div><div id="app-settings-content"><form class="import-form" action="bookmark/import" method="post" target="upload_iframe" enctype="multipart/form-data" encoding="multipart/form-data"><input type="file" class="import" name="bm_import" size="5" /><input type="hidden" name="requesttoken" value="'+oc_requesttoken+'" /><button class="import-facade"><span class="icon-upload"></span> Import</button></form><iframe class="upload" name="upload_iframe" id="upload_iframe"></iframe><button class="export"><span class="icon-download"></span> Export</button><div class="import-status"></div></div>')
, ui: {
    'content': '#app-settings-content'
  , 'import': '.import'
  , 'form': '.import-form'
  , 'iframe': '.upload'
  , 'status': '.import-status'
  }
, events: {
    'click .settings-button': 'open'
  , 'click .import-facade': 'importTrigger'
  , 'change @ui.import': 'importSubmit'
  , 'load @ui.iframe': 'importResult'
  , 'click .export': 'exportTrigger'
  }
, open: function(e) {
    e.preventDefault()
    this.getUI('content').slideToggle()
  }
, importTrigger: function(e) {
    e.preventDefault()
    this.getUI('import').click()
  }
, importSubmit: function(e) {
    e.preventDefault()
    this.getUI('iframe').load(this.importResult.bind(this));
    this.getUI('form').submit();
    this.getUI('status').text(t('bookmark', 'Uploading...'));
  }
, importResult: function () {
    var data;
    try {
      data = $.parseJSON(this.getUI('iframe').contents().text());
    } catch (e) {
      this.getUI('status').text(t('bookmark', 'Import error'));
      return;
    }
    if (data.status == 'error') {
      var list = $("<ul></ul>").addClass('setting_error_list');
      console.log(data);
      $.each(data.data, function (index, item) {
        list.append($("<li></li>").text(item));
      });
      this.getUI('status').html(list);
      return
    }
    this.getUI('status').text(t('bookmark', 'Import completed successfully.'));
    Backbone.history.navigate('all', {trigger: true})
  }
, exportTrigger: function() {
    window.location = 'bookmark/export?requesttoken='+oc_requesttoken
  }
})

var ContentView = Marionette.View.extend({
  template: _.template('<div id="mobile-nav-slot"></div><div id="tags-management-slot"></div><div id="bulk-actions-slot"></div><div id="view-bookmarks-slot"></div><div id="bookmark-detail-slot"></div>')
, regions: {
    'mobileNav': {
      el: '#mobile-nav-slot'
    , replaceElement: true
    }
  , 'tagsManagement': {
      el: '#tags-management-slot'
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
, onNavigate: function(route, tags) {
    if (route.indexOf('tags') !== 0) {
      this.detachChildView('tagsManagement')
      return
    }
    if (this.getRegion('tagsManagement').hasView()) return
    this.showChildView('tagsManagement', new TagsManagementView({tags: app.tags, selected: tags})) 
  }
, onSelect: function(model) {
    this.selected.add(model)
  }
, onUnselect: function(model) {
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

var MobileNavView = Marionette.View.extend({
  className: 'mobile-nav'
, template: _.template('<a href="#" class="icon-menu toggle-menu"></a>')
, events: {
    'click .toggle-menu': 'toggleMenu'
  }
, toggleMenu: function(e) {
    e.preventDefault()
    $('body').toggleClass('mobile-nav-open')
  }
})

var BulkActionsView = Marionette.View.extend({
  className: 'bulk-actions'
, template: _.template('<button class="delete"><span class="icon-delete"></span></button><div class="selection-tools"><button class="select-all"><span class="icon-checkmark"></span> Select all visible</button><div class="close"><span class="icon-close"></span></div></div>')
, events: {
    'click .delete': 'delete'
  , 'click .select-all': 'selectAll'
  , 'click .close': 'abort'
  }
, initialize: function(opts) {
    this.all = opts.all
    this.selected = opts.selected
    this.listenTo(this.selected, 'remove', this.onReduceSelection)
    this.listenTo(this.selected, 'add', this.onExtendSelection)
  }
, onReduceSelection: function() {
    if (this.selected.length == 0) this.$el.slideUp()
  }
, onExtendSelection: function() {
    if (this.selected.length == 1) this.$el.slideDown()
  }
, delete: function() {
    var that = this
    this.selected.forEach(function(model) {
      model.trigger('unselect', model)
      model.destroy({
        error: function() {
          Backbone.history.navigate('all', {trigger: true})
        }
      })
    })
  }
, selectAll: function() {
    this.all.forEach(function(model) {
      model.trigger('select', model) 
    })
  }
, abort: function() {
    this.selected.models.slice().forEach(function(model) {
      model.trigger('unselect', model)
    })
  }
})


var BookmarksView = Marionette.CollectionView.extend({
  className: 'bookmarks'
, regions: {

  }
, childView: function() {return BookmarkCardView}
, emptyView: function() {return EmptyBookmarksView}
})

var EmptyBookmarksView = Marionette.View.extend({
  template: _.template('<h2>No bookmarks, here.</h2><p>There are no bookmarks available for this query. Try adding some using the above form.</p>')
, className: 'bookmarks-empty'
})

var BookmarkCardView = Marionette.View.extend({
  template: _.template('<input type="checkbox"/><h1><img src="<%- "//:"+new URL(url).host+"/favicon.ico" %>"/><%- title %></h1><h2><a href="<%- url %>"><span class="icon-external"></span><%- new URL(url).host %></a></h2><div class="actions"><div class="icon-more toggle"></div><div class="popovermenu"><ul><li><button class="action-edit"><span class="icon-edit"></span><span>Edit</span></button></li><li><button class="action-delete"><span class="icon-delete"></span><span>Delete</span></button></li></ul></div></div><div class="tags"></div>'),
  className: "bookmark-card",
  ui: {
    'checkbox': 'input[type="checkbox"]'
  , 'actionsToggle': '.actions .toggle'
  , 'actionsMenu': '.actions .popovermenu'
  },
  regions: {
    'tags': '.tags'
  },
  events: {
    "click": "open"
  , "click @ui.checkbox": "select"
  , 'click @ui.actionsToggle': 'toggleActions'
  , 'blur @ui.actionsToggle': 'closeActions'
  , 'click .action-edit': 'actionEdit'
  , 'click .action-delete': 'actionDelete'
  },
  initialize: function() {
    this.listenTo(this.model, "change", this.render);
    this.listenTo(this.model, "select", this.onSelect);
    this.listenTo(this.model, "unselect", this.onUnselect);
    
    this.onDocumentClick = this.closeActions.bind(this)
    $(window.document).click(this.onDocumentClick)
  }
, onRender: function() {
    var tags = new Tags(this.model.get('tags').map(function(id) {
      return new Tag({name: id})
    }))
    this.showChildView('tags', new TagsNavigationView({collection: tags}))
  }
, open: function(e) {
    if (e && e.target !== this.el && e.target !== this.$('h1')[0]) return
    Radio.channel('details').trigger('show', this.model)
  }
, select: function(e) {
    e.stopPropagation()
    if (this.$el.hasClass('active')) {
      this.model.trigger('unselect', this.model)
    }else{
      this.model.trigger('select', this.model)
    }
  }
, onSelect: function() {
    this.$el.addClass('active')
  }
, onUnselect: function() {
    this.$el.removeClass('active')
    this.render()
  }
, onDestroy: function() {
    $(window.document).unbind('click', this.onDocumentClick)
  }
, toggleActions: function(e) {
    this.getUI('actionsMenu').toggleClass('open')
    this.$el.toggleClass('actions-open')
  }
, closeActions: function(e) {
    if (e.target === this.getUI('actionsToggle')[0]) return
    this.getUI('actionsMenu').removeClass('open')
    this.$el.removeClass('actions-open')
  }
, actionDelete: function() {
    this.model.destroy()
  }
, actionEdit: function() {
    Radio.channel('details').trigger('edit', this.model)
  }
})


var BookmarkDetailView = Marionette.View.extend({
  getTemplate: function() {
    if (this.editing) {
      return this.templateEditing
    }
    return this.templateDefault
  },
  templateDefault: _.template('<div class="close icon-close"></div><div class="actions"><button class="edit icon-rename"></button><button class="delete icon-delete"></button></div><h1><%- title %></h1><h2><a href="<%- url %>"><span class="icon-external"></span><%- new URL(url).host %></a></h2><div class="tags"></div><div class="description"><%- description %></div>'),
  templateEditing: _.template('<div class="close icon-close"></div><h1><input class="input-title" type="text" value="<%- title %>" /></h1><h2><input type="type" class="input-url icon-external" value="<%- url %>" /></h2><div class="tags"><input type="text" /></div><div class="description"><textarea class="input-desc"><%- description %></textarea></div><div class="actions editing"><button class="submit primary"><span class="icon-checkmark"></span> <span>Save</span></button><button class="cancel">Cancel</button></div>'),
  className: "bookmark-detail",
  regions: {
    'tags': {
      el: '.tags'
    , replaceElement: true
    }
  },
  ui: {
    'close': '> .close'
  , 'edit': '.edit'
  , 'delete': '.delete'
  },
  events: {
    'click @ui.close': 'close'
  , 'click @ui.edit': 'edit'
  , 'click @ui.delete': 'delete'
  , 'click .submit': 'submit'
  , 'click .cancel': 'cancel'
  },
  initialize: function(opts) {
    this.listenTo(this.model, "change", this.render);
  },
  onRender: function() {
    if (this.editing) {
      this.$('.tags input')
      .val(this.model.get('tags').join(','))
      .tagit({
        allowSpaces: true,
        availableTags: app.tags.pluck('name'),
        placeholderText: t('bookmarks', 'Enter tags'),
        onTagRemoved: function() {},
        onTagFinishRemoved: function() {},
        onTagClicked: function(){}
      })
    }else{
      var tags = new Tags(this.model.get('tags').map(function(id) {
        return new Tag({name: id})
      }))
      this.showChildView('tags', new TagsNavigationView({collection: tags}))
    }
  },
  close: function() {
    Radio.channel('details').trigger('close')
  },
  setEditing: function(isEditing) {
    if (isEditing) {
      this.editing = true
      this.$el.addClass('editing')
    }else{
      this.editing = false
      this.$el.removeClass('editing')
    }
    this.render()
  },
  edit: function() {
    this.setEditing(true)
  }
, submit: function() {
    this.model.set('title', this.$('.input-title').val())
    this.model.set('url', this.$('.input-url').val())
    this.model.set('tags', this.$('.tags input').tagit("assignedTags"))
    this.model.set('description', this.$('.input-desc').val())
    this.model.save()
    this.cancel()
  }
, cancel: function() {
    this.setEditing(false)
  }
, onDestroy: function() {
    this.close() 
  }
})



var TagsManagementView = Marionette.View.extend({
  template: _.template('<div id="selected-slot"></div><div id="unselected-slot"></div>')
, className: 'tags-management'
, regions: {
    'selected': {
      el: '#selected-slot'
    , replaceElement: true
    }
  , 'unselected': {
      el: '#unselected-slot'
    , replaceElement: true
    }
  }
, initialize: function(options) {
    var that = this
    this.tags = options.tags
    
    this.unselected = new Tags
    this.unselected.comparator = 'name'
    this.unselected.add(this.tags.models)
    
    this.selected = new Tags
    this.selected.comparator = 'name'
    options.selected.forEach(function(tagName) {
      var tag = that.unselected.findWhere({name: tagName})
      if (!tag) return

      that.unselected.remove(tag)
      that.selected.add(tag)
    })
    
    this.tags.on('add', function(model) {
      if (~options.selected.indexOf(model.get('name'))) {
        that.selected.add(model)
        return
      }
      that.unselected.add(model)
    })
    console.log(options.selected, this.tags, this.selected, this.unselected)

    this.listenTo(this.tags, 'select', this.onSelect)
    this.listenTo(this.tags, 'unselect', this.onUnselect)

  }
, onRender: function() {
    this.showChildView('selected', new TagsManagementCollectionView({collection: this.selected, selected: true}))
    this.showChildView('unselected', new TagsManagementCollectionView({collection: this.unselected}))
  }
, onSelect: function(model) {
    this.selected.add(model)
    this.unselected.remove(model)
    this.triggerRoute()
  }
, onUnselect: function(model) {
    this.selected.remove(model)
    this.unselected.add(model)
    this.triggerRoute()
  }
, triggerRoute: function() {
    Backbone.history.navigate('tags/'+this.selected.pluck('name').map(encodeURIComponent).join(','), {trigger: true})
  }
})

var TagsManagementCollectionView = Marionette.CollectionView.extend({
  tagName: 'ul'
, initialize: function(opts) {
    this.selected = opts.selected
  }
, childView: function() {return TagsManagementTagView}
, childViewOptions: function(model, i) {
    return {
      selected: this.selected
    }
  }
})

var TagsManagementTagView = Marionette.View.extend({
  className: 'tag-man-item'
, tagName: 'li'
, template: _.template('<input type="checkbox" class="checkbox" name="selected" /><label for="selected" title="Select this"></label><a href="#"><%- name %></a><div class="actions"><ul><li class="action"><button class="edit icon-edit"></button></li><li class="action"><button class="delete icon-delete"></button></li></ul></div>')
, events: {
    'click': 'open'
  }
, initialize: function(opts) {
    this.selected = opts.selected
  }
, onRender: function() {
    this.$('.checkbox').prop('checked', this.selected)
    if (this.selected) {
      this.$el.addClass('selected')
    }
  }
, open: function(e) {
    e.preventDefault()
    e.stopPropagation()
    if (this.selected) this.model.trigger('unselect', this.model)
    else this.model.trigger('select', this.model)
  }
})


var _sync = Backbone.sync
Backbone.sync = function(method, model, options) {
  _sync(method, model, _.extend({}, options, {
    success: function(json) {
      console.log(json)
      if (!(model instanceof Tags)) options.success(json.item || json.data)
      else options.success(json.map(function(name){return {name: name}}))
    }
  }))
}

// init

var app = new App()
$(function() {
  app.start()
})
