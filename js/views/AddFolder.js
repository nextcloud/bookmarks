import _ from 'underscore';
import Backbone from 'backbone';
import templateStringDefault from '../templates/AddFolder.html';
import Folder from '../models/Folder';
import Folders from '../models/Folders';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.View.extend({
	className: 'folders-item',
	tagName: 'li',
	template: _.template(templateStringDefault),
	events: {
		'click a': 'actionEdit',
		'click .action.submit': 'actionSubmit',
		'click .action.cancel': 'actionCancel',
		'keyup input.title': 'keyup'
	},
	initialize: function(options) {
		this.parentFolder = options.parentFolder;
		this.collection = options.collection;
		this.listenTo(Radio.channel('documentClicked'), 'click', this.click);
	},
	onRender: function() {
		this.$el.removeClass('editing');
		if (this.editing) {
			this.$el.addClass('editing');
			this.$('input.title').focus();
		}
	},
	actionEdit: function(e) {
		e.preventDefault();
		e.stopPropagation();
		if (this.editing) {
			return;
		}
		this.editing = true;
		this.render();
	},
	actionSubmit: function(e) {
		var that = this;
		if (e) {
			e.preventDefault();
			e.stopPropagation();
		}
		var folder = new Folder();
		folder.set('title', this.$('input.title').val());
		folder.set('children', new Folders());
		folder.set(
			'parent_folder',
			this.parentFolder ? this.parentFolder.get('id') : -1
		);
		folder.once('sync', function() {
			that.collection.add(folder);
		});
		folder.save();
		this.actionCancel();
	},
	actionCancel: function(e) {
		this.editing = false;
		this.render();
	},
	click: function(e) {
		if ($.contains(this.el, e.target)) {
			return;
		}
		this.actionCancel();
	},
	keyup: function(e) {
		if (e.which === 13) {
			this.actionSubmit();
		}
	}
});
