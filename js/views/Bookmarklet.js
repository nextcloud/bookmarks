import _ from 'underscore';
import Backbone from 'backbone';
import Bookmark from '../models/Bookmark';
import Tags from '../models/Tags';
import TagsSelectionView from './TagsSelection';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.View.extend({
	template: false,
	el: '#bookmarklet_form',
	regions: {
		'tags':  {
			el: '#tags',
			replaceElement: true
		},
	},
	events: {
		'click .submit': 'submit'
	},
	initialize: function(options) {
		this.app = options.app;
    
		$(window.document).click(function(e) {
			Radio.channel('documentClicked').trigger('click', e);
		});
		
		this.app.tags.once('reset sync add remove', () => {
			this.selected = new Tags(
				this.$('#tags li')
				.map((e) => $(e).text())
				.map((tagName) => this.app.tags.findWhere({name: tagName}))
			);
			this.showChildView('tags', new TagsSelectionView({app: this.app, selected: this.selected}));
		});
	},
	submit: function(e) {
		e.preventDefault();
		this.$('#add_form_loading').css('visibility', 'visible');
		var bm = new Bookmark({
			title: this.$('.title').val(),
			url: this.$('.url_input').val(),
			description: this.$('.desc').val(),
			tags: this.selected.pluck('name')
		});
		bm.once('sync', () => setTimeout(() => window.close(), 1e3));
		bm.save({
			wait: true,
			error: () => OC.dialogs.alert(t('bookmarks', 'An error occurred while trying to save the bookmark.'),
						t('bookmarks', 'Error'), null, true)
		});
	}
});
