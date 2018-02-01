import Backbone from 'backbone'

const Marionette = Backone.Marionette
const Radio = Backbone.Radio

export default Marionette.View.extend({
  template: _.template('<h2>No bookmarks, here.</h2><p>There are no bookmarks available for this query. Try adding some using the above form.</p>')
, className: 'bookmarks-empty'
})
