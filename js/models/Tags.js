import Backbone from 'backbone'

var Tags = Backbone.Collection.extend({
  model: Tag
, comparator: function(t) {return -t.get('count')}
, url: 'tag'
})

export default Tags
