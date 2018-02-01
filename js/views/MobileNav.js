import Backbone from 'backbone'

const Marionette = Backone.Marionette
const Radio = Backbone.Radio

export default Marionette.View.extend({
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
