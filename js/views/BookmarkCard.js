import _ from 'underscore'
import Backbone from 'backbone'
import TagsNavigationView from './TagsNavigation'
import templateString from '../templates/BookmarkCard.html'

const Marionette = Backbone.Marionette
const Radio = Backbone.Radio

export default Marionette.View.extend({
  template: _.template(templateString),
  className: "bookmark-card",
  ui: {
    'checkbox': '.selectbox'
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
  , 'click .action-select': 'select'
  , 'click .action-unselect': 'select'
  },
  initialize: function(opts) {
    this.app = opts.app
    this.listenTo(this.model, "change", this.render);
    this.listenTo(this.model, "select", this.onSelect);
    this.listenTo(this.model, "unselect", this.onUnselect);
    this.listenTo(app.tags, 'sync', this.render)
    this.listenTo(Radio.channel('documentClicked'), 'click', this.closeActions) 
  }
, onRender: function() {
	  var that = this
    this.$el.css('background-image', 'url(bookmark/'+this.model.get('id')+'/image)')
    var tags = new Tags(this.model.get('tags').map(function(id) {
      return that.app.tags.findWhere({name: id})
    }))
    this.showChildView('tags', new TagsNavigationView({collection: tags}))
    this.$('.checkbox').prop('checked', this.$el.hasClass('active'))
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
    this.render()
  }
, onUnselect: function() {
    this.$el.removeClass('active')
    this.render()
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
