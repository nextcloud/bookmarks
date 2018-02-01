import Backbone from 'backbone'

const Marionette = Backone.Marionette
const Radio = Backbone.Radio

export default Marionette.View.extend({
  className: 'navigation'
, tagName: 'ul'
, template: _.template('<li data-id="all" class="all"><a href="#"><span class="icon-home"></span>All bookmarks</a></li><li data-id="favorites" class="favorites"><a href="#"><span class="icon-favorite"></span>Favorites</a></li><li data-id="shared" class="shared"><a href="#"><span class="icon-share"></span>Shared</a></li>')
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
