import Backbone from 'backbone'
import TagView from '../views/TagsNavigationTag'

const Marionette = Backone.Marionette
const Radio = Backbone.Radio

export default Marionette.CollectionView.extend({
  tagName: 'ul'
, childView: TagView
})
