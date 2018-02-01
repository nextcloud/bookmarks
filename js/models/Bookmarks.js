import Backbone from 'backbone'

export default Backbone.Collection.extend({
  model: Bookmark
, url: 'bookmark'
})
