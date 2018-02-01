import Backbone from 'backbone'
import TagsNavigationView from './TagsNavigation'

const Marionette = Backone.Marionette
const Radio = Backbone.Radio

export default Marionette.View.extend({
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
    this.listenTo(app.tags, 'sync', this.render)
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
        return app.tags.findWhere({name: id})
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
