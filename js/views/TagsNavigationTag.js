import Backbone from 'backbone'

const Marionette = Backone.Marionette
const Radio = Backbone.Radio

export default Marionette.View.extend({
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
    Backbone.history.navigate('tags/' + encodeURIComponent(this.model.get('name')), {trigger: true});
  }
, onNavigate: function(category, tags) {
    this.$el.removeClass('active')
    if (category === 'tags' && ~tags.indexOf(this.model.get('name'))) {
      this.$el.addClass('active')
    }
  }
})
