import _ from 'underscore'
import Backbone from 'backbone'

const Marionette = Backbone.Marionette
const Radio = Backbone.Radio

export default Marionette.View.extend({
  tagName: 'select'
, template: _.template('')
, className: 'tags-selection'
, events: {
    'select2:select': 'onAdd'
  , 'select2:unselect': 'onRemove'
  }
, initialize: function(options) {
    this.app = options.app 
    this.selected = options.selected || new Tags
    this.selected.comparator = 'name'
    
    this.listenTo(this.selected, 'add', this.onChangeByAlgo)
    this.listenTo(this.selected, 'remove', this.onChangeByAlgo)
    this.listenTo(this.selected, 'reset', this.onChangeByAlgo)
    this.listenTo(this.collection, 'add', this.onAttach)
  }
, onAttach: function() {
    if (this.$el.hasClass("select2-hidden-accessible")) {
      this.$el.select2('destroy')
    }
    this.$el.select2({
      placeholder: t('bookmarks', 'Set tags')
    , allowClear: true
    , width: '100%'
    , tags: true
    , multiple: true
    , tokenSeparators: [',', ' ']
    , data: this.app.tags.pluck('name').map(function(name) {
        return {id: name, text: name}
      })
    })
    .val(this.selected.pluck('name'))
    .trigger('change')
  }
, onDetach: function() {
    this.$el.select2('destroy')
  }
, onAddByUser: function(e) {
    if (this.triggeredByAlgo) {
      return this.triggeredByAlgo = false
    }
    this.selected.add(this.app.tags.get(e.params.data.id))
  }
, onRemoveByUser: function(e) {
    if (this.triggeredByAlgo) {
      return this.triggeredByAlgo = false
    }
    this.selected.remove(this.app.tags.get(e.params.data.id))
  }
, onChangeByAlgo: function(e) {
    this.$el
    .val(this.selected.pluck('name'))
    .trigger('change')
    this.triggeredByAlgo = true
  }
})
