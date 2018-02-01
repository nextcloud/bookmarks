import Backbone from 'backbone'

const Marionette = Backone.Marionette
const Radio = Backbone.Radio

export default Marionette.View.extend({
  className: 'tag-man-item'
, tagName: 'li'
, getTemplate: function() {
    if (this.editing) {
      return this.templateEditing
    }
    return this.templateDefault
  }
, templateDefault: _.template('<a href="#"><span><%- name %></span><div class="app-navigation-entry-utils"><ul><li class="app-navigation-entry-utils-counter"><%- count > 999 ? "999+" :count  %></li><li class="app-navigation-entry-utils-menu-button"><button></button></li></ul></div></a><div class="popovermenu"><ul><li><button class="menu-filter-add"><span><input type="checkbox" name="select" class="checkbox" /><label for="select"></label></span><span>Add to filter</span></button></li><li><button class="menu-filter-remove"><span><input type="checkbox" name="select" checked class="checkbox" /><label for="select"></label></span><span>Remove from filter</span></button></li><li><button class="menu-edit"><span class="icon-rename"></span><span>Rename</span></button></li><li><button class="menu-delete"><span class="icon-delete"></span><span>Delete</span></button></li></ul></div>')
, templateEditing: _.template('<a href="#"><input type="text" value="<%- name %>"><div class="actions"><ul><li class="action"><button class="submit icon-checkmark"></button></li><li class="action"><button class="cancel icon-close"></button></li></ul></div></a>')
, ui: {
    'actionsMenu': '.popovermenu'
  , 'actionsToggle': '.app-navigation-entry-utils-menu-button'
  }
, events: {
    'click': 'selectSimple'
  , 'click @ui.actionsToggle': 'toggleActions'
  , 'click .menu-filter-add': 'actionSelect'
  , 'click .menu-filter-remove': 'actionUnselect'
  , 'click .menu-delete': 'actionDelete'
  , 'click .menu-edit': 'actionEdit'
  , 'click .action .submit': 'actionSubmit'
  , 'click .action .cancel': 'actionCancel'
  }
, initialize: function() {
    this.listenTo(this.model, 'select', this.onSelect)
    this.listenTo(this.model, 'unselect', this.onUnselect)
    this.listenTo(Radio.channel('documentClicked'), 'click', this.closeActions)   
  }
, onRender: function() {
    if (this.selected) {
      this.$el.addClass('active')
    }else{
      this.$el.removeClass('active')
    }
    if (this.editing) {
      this.$('input').focus()
    }
  }
, onSelect: function() {
    this.selected = true
    this.render()
  }
, onUnselect: function() {
    this.selected = false
    this.render()
  }
, selectSimple: function(e) {
    e.preventDefault()
    if (e && !~[ this.el, this.$('a')[0], this.$('span')[0] ].indexOf(e.target)) {
      return
    }
    if (this.editing) return
    Backbone.history.navigate('tags/'+encodeURIComponent(this.model.get('name')), {trigger: true})
  }
, toggleActions: function() {
    this.getUI('actionsMenu').toggleClass('open')
  }
, closeActions: function(e) {
    if (this.editing || $.contains(this.getUI('actionsToggle')[0], e.target)) return
    this.getUI('actionsMenu').removeClass('open')
  }
, actionSelect: function(e) {
    this.model.trigger('select', this.model)
  }
, actionUnselect: function(e) {
    this.model.trigger('unselect', this.model)
  }
, actionDelete: function() {
    this.model.destroy()
  }
, actionEdit: function() {
    this.editing = true
    this.render()
  }
, actionSubmit: function() {
    this.model.set('name', this.$('input').val())
    this.model.save()
    this.actionCancel()
  }
, actionCancel: function() {
    this.editing = false
    this.render()
  }
})
