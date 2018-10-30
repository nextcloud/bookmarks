import _ from 'underscore';
import Backbone from 'backbone';
import templateStringDefault from '../templates/TagsManagementTag_default.html';
import templateStringEditing from '../templates/TagsManagementTag_editing.html';

const Marionette = Backbone.Marionette;
const Radio = Backbone.Radio;

export default Marionette.View.extend({
	className: 'tag-man-item',
	tagName: 'li',
	getTemplate: function() {
		if (this.editing) {
			return this.templateEditing;
		}
		return this.templateDefault;
	},
	templateDefault: _.template(templateStringDefault),
	templateEditing: _.template(templateStringEditing),
	ui: {
		actionsMenu: '.app-navigation-entry-menu',
		actionsToggle: '.app-navigation-entry-utils-menu-button'
	},
	events: {
		click: 'selectSimple',
		'click @ui.actionsToggle': 'toggleActions',
		'click .menu-filter-add': 'actionSelect',
		'click .menu-filter-remove': 'actionUnselect',
		'click .menu-delete': 'actionDelete',
		'click .menu-edit': 'actionEdit',
		'click .action .submit': 'actionSubmit',
		'click .action .cancel': 'actionCancel'
	},
	initialize: function() {
		this.listenTo(this.model, 'select', this.onSelect);
		this.listenTo(this.model, 'unselect', this.onUnselect);
		this.listenTo(Radio.channel('documentClicked'), 'click', this.closeActions);
	},
	onRender: function() {
		if (this.selected) {
			this.$el.addClass('active');
		} else {
			this.$el.removeClass('active');
		}
		if (this.editing) {
			this.$('input').focus();
		}
	},
	onSelect: function() {
		this.selected = true;
		this.render();
	},
	onUnselect: function() {
		this.selected = false;
		this.render();
	},
	selectSimple: function(e) {
		e.preventDefault();
		if (e && !~[this.el, this.$('a')[0], this.$('span')[0]].indexOf(e.target)) {
			return;
		}
		if (this.editing) return;
		Backbone.history.navigate(
			'tags/' + encodeURIComponent(this.model.get('name')),
			{ trigger: true }
		);
	},
	toggleActions: function() {
		this.getUI('actionsMenu').toggleClass('open');
	},
	closeActions: function(e) {
		if (this.editing || $.contains(this.getUI('actionsToggle')[0], e.target))
			return;
		this.getUI('actionsMenu').removeClass('open');
	},
	actionSelect: function(e) {
		this.model.trigger('select', this.model);
	},
	actionUnselect: function(e) {
		this.model.trigger('unselect', this.model);
	},
	actionDelete: function() {
		this.model.destroy();
	},
	actionEdit: function() {
		this.editing = true;
		this.render();
	},
	actionSubmit: function() {
		this.model.set('name', this.$('input').val());
		this.model.save();
		this.actionCancel();
	},
	actionCancel: function() {
		this.editing = false;
		this.render();
	}
});
