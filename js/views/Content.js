import _ from 'underscore';
import Backbone from 'backbone';
import Bookmarks from '../models/Bookmarks';
import EmptyBookmarksView from './EmptyBookmarks';
import MobileNavView from './MobileNav';
import BulkActionsView from './BulkActions';
import BookmarksView from './Bookmarks';
import BookmarkDetailView from './BookmarkDetail';
import templateString from '../templates/Content.html';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.View.extend({
	template: _.template(templateString),
	id: 'app-content',
	regions: {
		mobileNav: {
			el: '#mobile-nav-slot',
			replaceElement: true
		},
		bulkActions: {
			el: '#bulk-actions-slot',
			replaceElement: true
		},
		viewBookmarks: {
			el: '#view-bookmarks-slot',
			replaceElement: true
		},
		emptyBookmarks: {
			el: '#empty-bookmarks-slot',
			replaceElement: true
		},
		bookmarkDetail: {
			el: '#bookmark-detail-slot',
			replaceElement: true
		}
	},
	events: {
		scroll: 'infiniteScroll'
	},
	initialize: function(options) {
		this.app = options.app;
		this.bookmarks = this.app.bookmarks;
		this.selected = new Bookmarks();
		this.listenTo(
			this.bookmarks.loadingState,
			'change:fetching',
			this.infiniteScroll
		);
		this.listenTo(this.bookmarks, 'select', this.onSelect);
		this.listenTo(this.bookmarks, 'unselect', this.onUnselect);
		this.listenTo(Radio.channel('nav'), 'navigate', this.onNavigate);
		this.listenTo(Radio.channel('details'), 'show', this.onShowDetails);
		this.listenTo(Radio.channel('details'), 'close', this.onCloseDetails);
	},
	onRender: function() {
		this.showChildView('mobileNav', new MobileNavView());
		this.showChildView(
			'viewBookmarks',
			new BookmarksView({ collection: this.bookmarks, app: this.app })
		);
		this.showChildView(
			'emptyBookmarks',
			new EmptyBookmarksView({ app: this.app })
		);
	},
	infiniteScroll: function(e) {
		if (
			this.$el.prop('scrollHeight') <
			this.$el.prop('scrollTop') + this.$el.height() + 500
		) {
			this.bookmarks.fetchPage();
		}
	},
	onSelect: function(model) {
		if (this.selected.length == 0) {
			this.$el.addClass('selection-active');
			Radio.channel('details').trigger('close');
			this.showChildView(
				'bulkActions',
				new BulkActionsView({ selected: this.selected, app: this.app })
			);
		}
		this.selected.add(model);
	},
	onUnselect: function(model) {
		if (this.selected.length == 1) {
			this.$el.removeClass('selection-active');
			this.detachChildView('bulkActions');
		}
		this.selected.remove(model);
	},
	onShowDetails: function(model) {
		var view = this.getChildView('bookmarkDetail');
		// toggle details when the same card is clicked twice
		if (view && view.model.id === model.id) {
			Radio.channel('details').trigger('close');
		} else {
			var newView = new BookmarkDetailView({
				model: model,
				app: this.app,
				slideIn: !view
			});
			this.showChildView('bookmarkDetail', newView);
		}
	},
	onCloseDetails: function(evt) {
		var that = this;
		var view = this.getChildView('bookmarkDetail');
		if (!view) return;
		that.getChildView('bookmarkDetail').slideOut(function() {
			that.detachChildView('bookmarkDetail');
		});
	}
});
