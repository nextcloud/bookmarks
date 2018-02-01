import Backbone from 'backbone'

const Marionette = Backone.Marionette
const Radio = Backbone.Radio

export default Marionette.View.extend({
  className: 'bulk-actions'
, template: _.template('<div class="selection-tools"><button class="select-all"><span class="icon-checkmark"></span> Select all visible</button><div class="close"><span class="icon-close"></span></div></div><button class="delete"><span class="icon-delete"></span><span>Delete</span></button><div class="tags"><input type="text" /></div></div>')
, events: {
    'click .delete': 'delete'
  , 'click .select-all': 'selectAll'
  , 'click .selection-tools .close': 'abort'
  }
, initialize: function(opts) {
    this.all = opts.all
    this.selected = opts.selected
    this.listenTo(this.selected, 'remove', this.onReduceSelection)
    this.listenTo(this.selected, 'add', this.onExtendSelection)
  }
, onRender: function() {
    // hack to ignore events caused by tagit setup -- we should really get something else...
	this.rendering = true
    this.$('.tags input')
    .val(_.intersection.apply(_, this.selected.pluck('tags')).join(','))
    .tagit({
      allowSpaces: true,
      availableTags: app.tags.pluck('name'),
      placeholderText: t('bookmarks', 'Enter tags'),
      onTagRemoved: this.onTagRemoved.bind(this),
      onTagAdded: this.onTagAdded.bind(this),
      onTagFinishRemoved: function() {},
      onTagClicked: function(){}
    })
    this.rendering = false
  }
, onReduceSelection: function() {
    if (this.selected.length == 0) {
	    this.$el.removeClass('active')
    }
    this.render()
  }
, onExtendSelection: function() {
    if (this.selected.length == 1) {
      this.$el.addClass('active')
    }
    this.render()
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
, onTagAdded: function(e, el) {
    if (this.rendering) return
    var tagName = $('.tagit-label', el).text()
    this.selected.forEach(function(model) {
      var tags = model.get('tags')
      model.set('tags', _.union(tags, [tagName]))
      model.save()
    }) 
  }
, onTagRemoved: function(e, el) {
    if (this.rendering) return
    var tagName = $('.tagit-label', el).text()
    this.selected.forEach(function(model) {
      var tags = model.get('tags')
      model.set('tags', _.without(tags, tagName))
      model.save()
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
