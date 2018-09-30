import _ from 'underscore';
import Backbone from 'backbone';
import Settings from '../models/Settings';
import templateString from '../templates/Settings.html';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.View.extend({
	className: 'settings',
	id: 'app-settings',
	template: _.template(templateString),
	ui: {
		content: '#app-settings-content',
		bookmarklet: '.bookmarklet',
		import: '.import',
		form: '.import-form',
		iframe: '.upload',
		status: '.import-status',
		sort: '#sort',
		title: '#title',
		added: '#added',
		clickcount: '#clickcount',
		lastmodified: '#lastmodified',
		rsslink: '.rss-link',
		clearData: '.clear-data'
	},
	events: {
		'click .settings-button': 'open',
		'click @ui.bookmarklet': 'bookmarkletClick',
		'click .import-facade': 'importTrigger',
		'change @ui.import': 'importSubmit',
		'load @ui.iframe': 'importResult',
		'click .export': 'exportTrigger',
		'change @ui.sort': 'setSorting',
		'focus @ui.rsslink': 'clickRssLink',
		'click @ui.clearData': 'deleteAllBookmarks'
	},
	initialize: function(options) {
		this.app = options.app;
		this.listenTo(this.model, 'change:sorting', this.getSorting);
		this.listenTo(this.app.bookmarks.loadingState, 'change:query', this.render);
	},
	onRender: function() {
		const bookmarkletUrl =
			window.location.origin +
			OC.getRootPath() +
			'/index.php/apps/bookmarks/bookmarklet';
		const bookmarkletSrc = `javascript:(function(){var a=window,b=document,c=encodeURIComponent,e=c(document.title),d=a.open('${bookmarkletUrl}?output=popup&url='+c(b.location)+'&title='+e,'bkmk_popup','left='+((a.screenX||a.screenLeft)+10)+',top='+((a.screenY||a.screenTop)+10)+',height=400px,width=550px,resizable=1,alwaysRaised=1');a.setTimeout(function(){d.focus()},300);})();`;
		this.getUI('bookmarklet').prop('href', bookmarkletSrc);

		const rssURL =
			window.location.origin +
			OC.getRootPath() +
			'/index.php/apps/bookmarks/public/rest/v2/bookmark?' +
			$.param(
				Object.assign({}, this.app.bookmarks.loadingState.get('query'), {
					format: 'rss',
					page: -1
				})
			);
		this.getUI('rsslink').val(rssURL);
	},
	open: function(e) {
		e.preventDefault();
		this.getUI('content').slideToggle();
	},
	bookmarkletClick: function(e) {
		e.preventDefault();
	},
	importTrigger: function(e) {
		e.preventDefault();
		this.getUI('import').click();
	},
	importSubmit: function(e) {
		var that = this;
		e.preventDefault();
		if (typeof window.fetch !== 'undefined') {
			// If we have fetch() do a little hapiness dance and go!
			var data = new FormData();
			data.append('bm_import', this.getUI('import')[0].files[0]);
			fetch(this.getUI('form').attr('action'), {
				method: 'POST',
				headers: {
					requesttoken: oc_requesttoken
				},
				body: data,
				mode: 'same-origin',
				credentials: 'same-origin'
			})
				.then(function(res) {
					if (!res.ok) {
						if (res.status === 413) {
							return { status: 'error', data: ['Selected file is too large'] };
						}
						return { status: 'error', data: [res.statusText] };
					}
					return res.json();
				})
				.then(function(json) {
					that.importResult(JSON.stringify(json));
				})
				.catch(function(e) {
					that.importResult(
						JSON.stringify({ status: 'error', data: [e.message] })
					);
				});
		} else {
			// If we don't have fetch() ask grandpa iframe to send it
			this.getUI('iframe').load(function() {
				that.importResult(
					that
						.getUI('iframe')
						.contents()
						.text()
				);
			});
			this.getUI('form').submit();
		}
		this.$('.import-facade .icon-upload')
			.removeClass('icon-upload')
			.addClass('icon-loading-small');
	},
	importResult: function(data) {
		try {
			data = $.parseJSON(data);
		} catch (e) {
			this.getUI('status').text(t('bookmark', 'Import error'));
			return;
		}
		if (data.status == 'error') {
			var list = $('<ul></ul>').addClass('setting_error_list');
			console.log(data);
			$.each(data.data, function(index, item) {
				list.append($('<li></li>').text(item));
			});
			this.getUI('status').html(list);
			return;
		}
		this.getUI('status').text(t('bookmark', 'Import completed successfully.'));
		Backbone.history.navigate('', { trigger: true }); // reload app
		this.app.folders.fetch();
	},
	exportTrigger: function() {
		window.location =
			'bookmark/export?requesttoken=' + encodeURIComponent(oc_requesttoken);
	},
	getSorting: function() {
		this.getUI(this.model.get('sorting')).prop('selected', true);
	},
	setSorting: function(e) {
		e.preventDefault();
		var select = document.getElementById('sort');
		var value = select.options[select.selectedIndex].value;
		this.model.setSorting(value);
	},
	clickRssLink: function() {
		var that = this;
		setTimeout(function() {
			that.getUI('rsslink').select();
		}, 100);
	},
	deleteAllBookmarks: function() {
		var app = this.app;
		$.ajax({
			method: 'DELETE',
			url: 'bookmark',
			headers: {
				requesttoken: oc_requesttoken
			},
			success: function() {
				Backbone.history.navigate('dummy', { trigger: true });
				Backbone.history.navigate('all', { trigger: true });
			}
		});
	}
});
