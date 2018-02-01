import Backbone from 'backbone'

const Marionette = Backone.Marionette
const Radio = Backbone.Radio

export default Marionette.View.extend({
  className: 'settings'
, id: 'app-settings'
, template: _.template('<div id="app-settings-header"><button class="settings-button">Settings</a></div><div id="app-settings-content"><form class="import-form" action="bookmark/import" method="post" target="upload_iframe" enctype="multipart/form-data" encoding="multipart/form-data"><input type="file" class="import" name="bm_import" size="5" /><input type="hidden" name="requesttoken" value="'+oc_requesttoken+'" /><button class="import-facade"><span class="icon-upload"></span> Import</button></form><iframe class="upload" name="upload_iframe" id="upload_iframe"></iframe><button class="export"><span class="icon-download"></span> Export</button><div class="import-status"></div></div>')
, ui: {
    'content': '#app-settings-content'
  , 'import': '.import'
  , 'form': '.import-form'
  , 'iframe': '.upload'
  , 'status': '.import-status'
  }
, events: {
    'click .settings-button': 'open'
  , 'click .import-facade': 'importTrigger'
  , 'change @ui.import': 'importSubmit'
  , 'load @ui.iframe': 'importResult'
  , 'click .export': 'exportTrigger'
  }
, open: function(e) {
    e.preventDefault()
    this.getUI('content').slideToggle()
  }
, importTrigger: function(e) {
    e.preventDefault()
    this.getUI('import').click()
  }
, importSubmit: function(e) {
    var that = this
    e.preventDefault()
    if (typeof(window.fetch) !== 'undefined') {
      // If we have fetch() do a little hapiness dance and go!
      var data = new FormData()
      data.append('bm_import', this.getUI('import')[0].files[0])
      fetch(this.getUI('form').attr('action'), {
        method: 'POST'
      , headers: {
          requesttoken: oc_requesttoken
        }
      , body: data 
      , mode: 'same-origin'
      , credentials: 'same-origin'
      })
      .then(function(res) {
        if (!res.ok) {
          if (res.status === 413) {
            return {status: 'error', data: ['Selected file is too large']}
          }
          return {status: 'error', data: [res.statusText]} 
        }
        return res.json()
      })
      .then(function(json) {
        that.importResult(JSON.stringify(json));
      })
      .catch(function(e) {
        that.importResult(JSON.stringify({status: 'error', data: [e.message]}));
      })
    } else {
      // If we don't have fetch() ask grandpa iframe to send it
      this.getUI('iframe').load(function() {
        that.importResult(that.getUI('iframe').contents().text());
      })
      this.getUI('form').submit();
    }
    this.getUI('status').text(t('bookmark', 'Uploading...'));
  }
, importResult: function (data) {
    var data;
    try {
      data = $.parseJSON(data);
    } catch (e) {
      this.getUI('status').text(t('bookmark', 'Import error'));
      return;
    }
    if (data.status == 'error') {
      var list = $("<ul></ul>").addClass('setting_error_list');
      console.log(data);
      $.each(data.data, function (index, item) {
        list.append($("<li></li>").text(item));
      });
      this.getUI('status').html(list);
      return
    }
    this.getUI('status').text(t('bookmark', 'Import completed successfully.'));
    Backbone.history.navigate('all', {trigger: true})
  }
, exportTrigger: function() {
    window.location = 'bookmark/export?requesttoken='+oc_requesttoken
  }
})
