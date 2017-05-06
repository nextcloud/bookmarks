(function() {
	/**
	 * Construct a new FileActions instance
	 * @constructs Files
	 */
	var Bookmarks = function() {
		this.initialize();
	};
	/**
	 * @memberof OCA.Search
	 */
	Bookmarks.prototype = {
		initialize: function() {
			OC.Plugins.register('OCA.Search', this);
		},
		attach: function(search) {
			var self = this;
			search.setFilter('bookmarks', function (query) {
				if (query.length > 2) {
					//search is not started until 500msec have passed
					window.setTimeout(function() {
						$('.bookmarks_list').addClass('hidden');
					}, 500);
				}else{
						$('.bookmarks_list').removeClass('hidden');
				}
			});

			search.setRenderer('bookmark', this.renderResult.bind(this));
		},
		renderResult: function($row, item) {
			$pathDiv = $('<div></div>').text(item.link)
			$row.find('td.info div.name').after($pathDiv).text(item.title)
			$row.find('td.result a').attr('href',item.link)
			return $row
		}
	};
	OCA.Search.Bookmarks = Bookmarks;
	OCA.Search.bookmarks = new Bookmarks();
})()
