import _ from 'underscore'
import Backbone from 'backbone'
import templateString from '../templates/EmptyBookmarks.html'

const Marionette = Backbone.Marionette
const Radio = Backbone.Radio

export default Marionette.View.extend({
  template: _.template(templateString)
, className: 'bookmarks-empty'
})
