import Backbone from 'backbone';

export var Folder = Backbone.Model.extend({
	urlRoot: 'folder',
	parse: function(obj) {
		return obj.item
			? Object.assign(obj.item, {
					children: new Folders(obj.item.children, { parse: true })
			  })
			: Object.assign(obj, {
					children: new Folders(obj.children, { parse: true })
			  });
	},
	contains: function(id) {
		return this.get('children').contains(id);
	}
});

var Folders = Backbone.Collection.extend({
	model: Folder,
	comparator: 'title',
	url: 'folder',
	parse: function(obj) {
		var list = obj.data ? obj.data : obj;
		return list.map(function(attributes) {
			return new Folder(attributes, { parse: true });
		});
	},
	contains: function(id) {
		if (~this.pluck('id').indexOf(id)) return true;
		if (
			this.some(function(folder) {
				folder.contains(id);
			})
		)
			return true;
		return false;
	}
});

export default Folders;
