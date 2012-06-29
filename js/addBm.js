(function($){
  $.bookmark_dialog = function(el, options){
    // To avoid scope issues, use 'base' instead of 'this'
    // to reference this class from internal events and functions.
    var base = this;
    
    // Access to jQuery and DOM versions of element
    base.$el = $(el);
    base.el = el;
    
    // Add a reverse reference to the DOM object
    base.$el.data("bookmark_dialog", base);
    
    base.form_submit = function search_form_submit(event)
    {
      event.preventDefault();
			$.ajax({
				type: 'POST',
				url: $(this).attr('action'),
				data: $(this).serialize(),
				success: function(data){ 
					if(data.status == 'success'){
						base.options['on_success'](data);
					}
				}
			});
      return false;
    }
		
    base.edit_url = function (event) {
			base.$el.find('.url_input').slideToggle();
		}
		
    base.change_url = function (event) {
			base.$el.find('.url-ro code').text(base.$el.find('.url_input').val());
		}
		
    base.init = function(){
      base.options = $.extend({},$.bookmark_dialog.defaultOptions, options);
      base.$el.find('form').bind('submit.addBmform',base.form_submit);
			base.$el.find('.url-ro img').bind('click',base.edit_url);
			base.$el.find('.url_input').bind('keypress',base.change_url);
			// Init Tagging thing
			base.$el.find('.tags').tagit({
				allowSpaces: true,
				availableTags: fullTags
			});

			if(base.options['record']) { //Fill the form if it's an edit
				record = base.options['record'];
				base.$el.find('.record_id').val(record.id);
				base.$el.find('.title').val(record.title);
				base.$el.find('.url-ro code').text(record.url);
				base.$el.find('.url_input').val(record.url);
				base.$el.find('.desc').val(record.description);
				base.$el.find('.is_public').val(record.public);
				tagit_elem = base.$el.find('.tags');
				for(var i=0;i<record.tags.length;i++) {
					tagit_elem.tagit('createTag', record.tags[i]);
					console.log(record.tags[i]);
				}
			}

			if(base.$el.find('.url-ro code').text() != '')
				base.$el.find('.url_input').hide();
			else
				base.$el.find('.url_input').shwo()
    };
		
    base.init();
  };
  


    
  $.bookmark_dialog.defaultOptions = {
		on_success: function(){},
		bookmark_record: undefined
  };
  
  $.fn.bookmark_dialog = function(options){
    return this.each(function(){
      (new $.bookmark_dialog(this, options));
    });
  };

})(jQuery);