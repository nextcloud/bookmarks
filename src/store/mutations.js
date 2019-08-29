import Vue from 'vue';

export const mutations = {
	DISPLAY_NEW_BOOKMARK: 'DISPLAY_NEW_BOOKMARK',
	DISPLAY_NEW_FOLDER: 'DISPLAY_NEW_FOLDER',
	DISPLAY_MOVE_DIALOG: 'DISPLAY_MOVE_DIALOG',
	RESET_SELECTION: 'RESET_SELECTION',
	REMOVE_SELECTION_BOOKMARK: 'REMOVE_SELECTION_BOOKMARK',
	ADD_SELECTION_BOOKMARK: 'ADD_SELECTION_BOOKMARK',
	REMOVE_SELECTION_FOLDER: 'REMOVE_SELECTION_FOLDER',
	ADD_SELECTION_FOLDER: 'ADD_SELECTION_FOLDER',
	ADD_BOOKMARK: 'ADD_BOOKMARK',
	REMOVE_BOOKMARK: 'REMOVE_BOOKMARK',
	REMOVE_ALL_BOOKMARK: 'REMOVE_ALL_BOOKMARK',
	SET_TAGS: 'SET_TAGS',
	INCREMENT_PAGE: 'INCREMENT_PAGE',
	SET_QUERY: 'SET_QUERY',
	SET_SORTBY: 'SET_SORTBY',
	FETCH_START: 'FETCH_START',
	FETCH_END: 'FETCH_END',
	REACHED_END: 'REACHED_END',
	SET_ERROR: 'SET_ERROR',
	SET_FOLDERS: 'SET_FOLDERS',
	SET_SIDEBAR: 'SET_SIDEBAR',
	SET_SETTING: 'SET_SETTING',
	SET_VIEW_MODE: 'SET_VIEW_MODE'
};
export default {
	[mutations.SET_VIEW_MODE](state, viewMode) {
		state.viewMode = viewMode;
	},
	[mutations.SET_ERROR](state, error) {
		state.error = error;
	},
	[mutations.SET_SETTING](state, { key, value }) {
		Vue.set(state.settings, key, value);
	},
	[mutations.SET_FOLDERS](state, folders) {
		state.folders = folders;
	},
	[mutations.SET_TAGS](state, tags) {
		state.tags = tags;
	},
	[mutations.DISPLAY_NEW_BOOKMARK](state, display) {
		state.displayNewBookmark = display;
		if (display) {
			state.displayNewFolder = false;
		}
	},
	[mutations.DISPLAY_NEW_FOLDER](state, display) {
		state.displayNewFolder = display;
		if (display) {
			state.displayNewBookmark = false;
		}
	},
	[mutations.DISPLAY_MOVE_DIALOG](state, display) {
		state.displayMoveDialog = display;
	},

	[mutations.RESET_SELECTION](state) {
		state.selection = { folders: [], bookmarks: [] };
	},
	[mutations.ADD_SELECTION_BOOKMARK](state, item) {
		state.selection.bookmarks.push(item);
	},
	[mutations.REMOVE_SELECTION_BOOKMARK](state, item) {
		Vue.set(
			state.selection,
			'bookmarks',
			state.selection.bookmarks.filter(s => !(s.id === item.id))
		);
	},
	[mutations.ADD_SELECTION_FOLDER](state, item) {
		state.selection.folders.push(item);
	},
	[mutations.REMOVE_SELECTION_FOLDER](state, item) {
		Vue.set(
			state.selection,
			'folders',
			state.selection.folders.filter(s => !(s.id === item.id))
		);
	},

	[mutations.ADD_BOOKMARK](state, bookmark) {
		const existingBookmark = state.bookmarksById[bookmark.id];
		if (!existingBookmark) {
			state.bookmarks.push(bookmark);
			Vue.set(state.bookmarksById, bookmark.id, bookmark);
		}
	},
	[mutations.REMOVE_BOOKMARK](state, id) {
		const index = state.bookmarks.findIndex(bookmark => bookmark.id === id);
		if (index !== -1) {
			state.bookmarks.splice(index, 1);
			Vue.delete(state.bookmarksById, id);
		}
	},
	[mutations.REMOVE_ALL_BOOKMARKS](state) {
		state.bookmarks = [];
		state.bookmarksById = {};
	},

	[mutations.SET_SIDEBAR](state, sidebar) {
		state.sidebar = sidebar;
	},

	[mutations.INCREMENT_PAGE](state) {
		Vue.set(state.fetchState, 'page', state.fetchState.page + 1);
	},
	[mutations.SET_QUERY](state, query) {
		state.bookmarks = [];
		state.bookmarksById = {};
		Vue.set(state.fetchState, 'page', 0);
		Vue.set(state.fetchState, 'reachedEnd', false);
		Vue.set(state.fetchState, 'query', query);
	},
	[mutations.FETCH_START](state, type) {
		Vue.set(state.loading, type, true);
	},
	[mutations.FETCH_END](state, type) {
		Vue.set(state.loading, type, false);
	},

	[mutations.REACHED_END](state) {
		Vue.set(state.fetchState, 'reachedEnd', true);
	}
};
