import Backbone from 'backbone'
import SearchController from './views/SearchController'
import AddBookmarkView from './views/AddBookmark'
import NavigationView from './views/Navigation'
import TagsManagementView from './views/TagsManagement'
import ContentView from './views/Content'
import SettingsView from './views/Settings'

const Marionette = Backone.Marionette
const Radio = Backbone.Radio

export default Marionette.View.extend({
  template: _.template('<div id="app-navigation"><div id="add-bookmark-slot"></div><div id="navigation-slot"></div><h3>Tags</h3><div id="favorite-tags-slot"></div><div id="settings-slot"></div></div><div id="app-content"><div id="content-slot"></div></div>')
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
    
    $(window.document).click(function(e) {
      Radio.channel('documentClicked').trigger('click', e)
    })
  }
, onRender: function() {
    this.showChildView('addBookmarks', new AddBookmarkView());
    this.showChildView('navigation', new NavigationView);
    this.showChildView('content', new ContentView({bookmarks: this.bookmarks})); 
    this.showChildView('tags', new TagsManagementView({collection: this.tags}))
    this.showChildView('settings', new SettingsView())
  }
})
