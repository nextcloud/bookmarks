import Backbone from 'backbone'
import Tag from './Tag'

var Tags = Backbone.Collection.extend({
  model: Tag
, comparator: function(t) {return -t.get('count')}
, url: 'tag'
})

export default Tags
