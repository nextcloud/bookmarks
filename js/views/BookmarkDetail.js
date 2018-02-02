import _ from 'underscore'
import Backbone from 'backbone'
import TagsNavigationView from './TagsNavigation'
import templateStringDefault from '../templates/BookmarkDetail_default.html'
import templateStringEditing from '../templates/BookmarkDetail_default.html'

const Marionette = Backbone.Marionette
const Radio = Backbone.Radio

export default Marionette.View.extend({
  getTemplate: function() {
    if (this.editing) {
      return this.templateEditing
    }
    return this.templateDefault
  },
  templateDefault: _.template(templateStringDefault),
  templateEditing: _.template(templateStringEditing),
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
    this.app = opts.app
    this.listenTo(this.model, "change", this.render);
    this.listenTo(app.tags, 'sync', this.render)
  },
  onRender: function() {
    var that = this
    if (this.editing) {
      this.$('.tags input')
      .val(this.model.get('tags').join(','))
      .tagit({
        allowSpaces: true,
        availableTags: this.app.tags.pluck('name'),
        placeholderText: t('bookmarks', 'Enter tags'),
        onTagRemoved: function() {},
        onTagFinishRemoved: function() {},
        onTagClicked: function(){}
      })
    }else{
      var tags = new Tags(this.model.get('tags').map(function(id) {
        return that.app.tags.findWhere({name: id})
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
    var that = this
    this.model.set({
      'title': this.$('.input-title').val()
    , 'url': this.$('.input-url').val()
    , 'tags': this.$('.tags input').tagit("assignedTags")
    , 'description': this.$('.input-desc').val()
    })
    this.model.save({wait: true})
    this.model.once('sync', function() {
      that.cancel()
    })
  }
, cancel: function() {
    this.setEditing(false)
  }
, onDestroy: function() {
    this.close() 
  }
})
