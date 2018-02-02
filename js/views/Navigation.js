import _ from 'underscore'
import Backbone from 'backbone'
import templateString from '../templates/Navigation.html'

const Marionette = Backbone.Marionette
const Radio = Backbone.Radio

export default Marionette.View.extend({
  className: 'navigation'
, tagName: 'ul'
, template: _.template(templateString)
, events: {
    'click .all': 'onClick'
  , 'click .favorites': 'onClick'
  , 'click .shared': 'onClick'
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
    if (category && this.$('[data-id='+category+']').length) this.$('[data-id='+category+']').addClass('active')
  }
})
