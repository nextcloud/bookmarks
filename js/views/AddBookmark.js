import _ from 'underscore'
import Backbone from 'backbone'
import Bookmark from '../models/Bookmark'
import templateString from '../templates/AddBookmark.html'

const Marionette = Backone.Marionette
const Radio = Backbone.Radio

export default Marionette.View.extend({
  template: _.template(templateString)
, className: 'add-bookmark'
, tagName: 'ul'
, events: {
    'click @ui.link': 'activate'
  , 'click @ui.button': 'submit'
  , 'keydown @ui.input': 'onKeydown'
  , 'blur @ui.input': 'deactivate'
  }
, ui: {
    'link': '.link a'
  , 'linkEntry': '.link'
  , 'formEntry': '.form'
  , 'input': 'input'
  , 'button': 'button'
  }
, activate: function() {
    this.getUI('linkEntry').hide()
    this.getUI('formEntry').show()
    this.getUI('input').focus()
  }
, deactivate: function() {
    this.getUI('linkEntry').show()
    this.getUI('formEntry').hide()
    this.getUI('input').val('')
  }
, onKeydown: function(e) {
    if (e.which != 13) return
    // Enter
    this.submit()
  }
, submit: function(e) {
    var $input = this.getUI('input')
    if (this.pending || $input.val() === '') return
    var url = $input.val()
    var bm = new Bookmark({url: url})
    this.setPending(true)
    var that = this
    bm.save(null,{
      success: function() {
	    // needed in order for the route to be revaluated when it's already active
        Backbone.history.navigate('dummyroute')
        Backbone.history.navigate('all', {trigger: true})
        that.setPending(false)
        that.deactivate()
      }
    , error: function() {
        that.setPending(false)
        that.getUI('button').removeClass('icon-add')
        that.getUI('button').addClass('icon-error-color')
      }
    })
  }
, setPending: function(pending) {
    if (pending) {
      this.getUI('button').removeClass('icon-add')
      this.getUI('button').removeClass('icon-error-color')
      this.getUI('button').addClass('icon-loading-small')
      this.getUI('button').prop('disabled', true)
    }else {
      this.getUI('button').removeClass('icon-error-color')
      this.getUI('button').addClass('icon-add')
      this.getUI('button').removeClass('icon-loading-small')
      this.getUI('button').prop('disabled', false) 
    }
    this.pending = pending
  }
})
