import Backbone from 'backbone'
import Marionette from 'backbone-marionette'
import Tag from './models/Tag'
import Tags from './models/Tags'
import App from './Application'

var _sync = Backbone.sync
Backbone.sync = function(method, model, options) {
  var overrideOptions = {
   success: function(json) {
      console.log(json)
      if (!(model instanceof Tags)) {
        options.success(json.item || json.data)
      } else {
        options.success(json)
      }
    }
  , error: function() {
      console.log(arguments)
    }
  }
  if (method === 'update' && model instanceof Tag) {
    overrideOptions.url = model.urlRoot+'/'+model.previous('name') 
  }
  _sync(method, model, _.extend({}, options, overrideOptions))
}

// init

var app = new App()
$(function() {
  app.start()
})
