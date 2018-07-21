(function(window, OCP, $) {
	[
		{
			el: '#bookmarks_previews_screenly_token',
			setting: 'previews.screenly.token'
		},
		{
			el: '#bookmarks_previews_screenly_url',
			setting: 'previews.screenly.url'
		}
	].forEach(function(entry) {
		$el = $(entry.el);
		$statusSuccess = $(entry.el + ' ~ .success-status');
		$statusError = $(entry.el + ' ~ .error-status');

		$statusSuccess.hide();
		$statusError.hide();

		$el.on('change', function() {
			OCP.AppConfig.setValue('bookmarks', entry.setting, $el.val(), {
				success: function() {
					$statusSuccess.show();
					setTimeout(function() {
						$statusSuccess.fadeOut();
					}, 3000);
				},
				error: function() {
					$statusError.show();
					setTimeout(function() {
						$statusError.fadeOut();
					}, 3000);
				}
			});
		});
	});
})(window, OCP, $);
