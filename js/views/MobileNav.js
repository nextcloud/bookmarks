import _ from 'underscore'
import Backbone from 'backbone'
import templateString from '../templates/MobileNav.html'

const Marionette = Backone.Marionette
const Radio = Backbone.Radio

export default Marionette.View.extend({
  className: 'mobile-nav'
, template: _.template(templateString)
, events: {
    'click .toggle-menu': 'toggleMenu'
  }
, toggleMenu: function(e) {
    e.preventDefault()
    $('body').toggleClass('mobile-nav-open')
  }
})
