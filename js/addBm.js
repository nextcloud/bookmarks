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
    };
		
    base.init();
  };
  


    
  $.bookmark_dialog.defaultOptions = {
		on_success: function(){}
  };
  
  $.fn.bookmark_dialog = function(options){
    return this.each(function(){
      (new $.bookmark_dialog(this, options));
    });
  };

})(jQuery);