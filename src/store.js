import Vue from 'vue';
import Vuex from 'vuex';
import axios from 'nextcloud-axios';
import AppGlobal from './mixins/AppGlobal';

Vue.use(Vuex);

const BATCH_SIZE = 42;

export const mutations = {
	DISPLAY_NEW_BOOKMARK: 'DISPLAY_NEW_BOOKMARK',
	DISPLAY_NEW_FOLDER: 'DISPLAY_NEW_FOLDER',
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

export const actions = {
	ADD_ALL_BOOKMARKS: 'ADD_ALL_BOOKMARKS',
	CREATE_BOOKMARK: 'CREATE_BOOKMARK',
	DELETE_BOOKMARK: 'DELETE_BOOKMARK',
	OPEN_BOOKMARK: 'OPEN_BOOKMARK',
	SAVE_BOOKMARK: 'SAVE_BOOKMARK',
	IMPORT_BOOKMARKS: 'IMPORT_BOOKMARKS',
	DELETE_BOOKMARKS: 'IMPORT_BOOKMARKS',

	LOAD_TAGS: 'LOAD_TAGS',
	RENAME_TAG: 'RENAME_TAG',
	DELETE_TAG: 'DELETE_TAG',

	LOAD_FOLDERS: 'LOAD_FOLDERS',
	CREATE_FOLDER: 'CREATE_FOLDER',
	SAVE_FOLDER: 'SAVE_FOLDER',
	DELETE_FOLDER: 'DELETE_FOLDER',

	NO_FILTER: 'NO_FILTER',
	FILTER_BY_RECENT: 'FILTER_BY_RECENT',
	FILTER_BY_UNTAGGED: 'FILTER_BY_UNTAGGED',
	FILTER_BY_TAGS: 'FILTER_BY_TAGS',
	FILTER_BY_FOLDER: 'FILTER_BY_FOLDER',
	FILTER_BY_SEARCH: 'FILTER_BY_SEARCH',
	FETCH_PAGE: 'FETCH_PAGE',

	SET_SETTING: 'SET_SETTING',
	LOAD_SETTING: 'LOAD_SETTING',
	LOAD_SETTINGS: 'SLOAD_SETTINGS'
};

export default new Vuex.Store({
	state: {
		fetchState: {
			page: 0,
			query: {},
			reachedEnd: false
		},
		loading: {
			tags: false,
			folders: false,
			bookmarks: false,
			createBookmark: false,
			saveBookmark: false,
			createFolder: false,
			saveFolder: false
		},
		error: null,
		settings: {
			viewMode: 'list',
			sorting: 'lastmodified'
		},
		bookmarks: [],
		bookmarksById: {},
		tags: [],
		folders: [],
		foldersById: {},
		displayNewBookmark: false,
		displayNewFolder: false,
		sidebar: null,
		viewMode: 'list'
	},

	getters: {
		getBookmark: state => id => {
			return state.bookmarksById[id];
		},
		getFolder: state => id => {
			if (Number(id) === -1) {
				return [{ id: '-1', children: state.folders }];
			}
			return findFolder(id, state.folders);
		}
	},

	mutations: {
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
		},

		[mutations.DISPLAY_NEW_FOLDER](state, display) {
			state.displayNewFolder = display;
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
	},

	actions: {
		[actions.ADD_ALL_BOOKMARKS]({ commit }, bookmarks) {
			for (const bookmark of bookmarks) {
				commit(mutations.ADD_BOOKMARK, bookmark);
			}
		},

		[actions.CREATE_BOOKMARK]({ commit, dispatch, state }, link) {
			if (state.loading.bookmarks) return;
			commit(mutations.FETCH_START, 'createBookmark');
			return axios
				.post(url('/bookmark'), {
					url: link
				})
				.then(response => {
					const {
						data: { item: bookmark, status }
					} = response;
					if (status !== 'success') {
						throw new Error(response.data);
					}
					commit(mutations.DISPLAY_NEW_BOOKMARK, false);
					commit(mutations.ADD_BOOKMARK, bookmark);
					return dispatch(actions.OPEN_BOOKMARK, bookmark.id);
				})
				.catch(err => {
					console.error(err);
					commit(
						mutations.SET_ERROR,
						AppGlobal.methods.t('bookmarks', 'Failed to create bookmark')
					);
					throw err;
				})
				.finally(() => {
					commit(mutations.FETCH_END, 'createBookmark');
				});
		},
		[actions.SAVE_BOOKMARK]({ commit, dispatch, state }, id) {
			commit(mutations.FETCH_START, 'saveBookmark');
			return axios
				.put(url(`/bookmark/${id}`), this.getters.getBookmark(id))
				.then(response => {
					const {
						data: { status }
					} = response;
					if (status !== 'success') {
						throw new Error(response.data);
					}
				})
				.catch(err => {
					console.error(err);
					commit(
						mutations.SET_ERROR,
						AppGlobal.methods.t('bookmarks', 'Failed to save bookmark')
					);
					throw err;
				})
				.finally(() => {
					commit(mutations.FETCH_END, 'saveBookmark');
				});
		},
		[actions.OPEN_BOOKMARK]({ commit }, id) {
			commit(mutations.SET_SIDEBAR, { type: 'bookmark', id });
		},
		[actions.DELETE_BOOKMARK]({ commit, dispatch, state }, id) {
			return axios
				.delete(url(`/bookmark/${id}`))
				.then(response => {
					const {
						data: { status }
					} = response;
					if (status !== 'success') {
						throw new Error(response.data);
					}
					commit(mutations.REMOVE_BOOKMARK, id);
				})
				.catch(err => {
					console.error(err);
					commit(
						mutations.SET_ERROR,
						AppGlobal.methods.t('bookmarks', 'Failed to delete bookmark')
					);
					throw err;
				});
		},
		[actions.IMPORT_BOOKMARKS]({ commit, dispatch, state }, file) {
			var data = new FormData();
			data.append('bm_import', file);
			return axios
				.post(url(`/bookmark/import`), data)
				.then(response => {
					if (!response.ok) {
						if (response.status === 413) {
							throw new Error('Selected file is too large');
						}
						throw new Error(response.statusText);
					} else {
						const {
							data: { status }
						} = response;
						if (status !== 'success') {
							throw new Error(response.data);
						}
					}
				})
				.catch(err => {
					console.error(err);
					commit(
						mutations.SET_ERROR,
						AppGlobal.methods.t('bookmarks', err.message)
					);
					throw err;
				});
		},
		[actions.DELETE_BOOKMARKS]({ commit, dispatch, state }) {
			return axios
				.delete(url(`/bookmark`))
				.then(response => {
					const {
						data: { status }
					} = response;
					if (status !== 'success') {
						throw new Error(response.data);
					}
					return dispatch(actions.LOAD_FOLDERS);
				})
				.catch(err => {
					console.error(err);
					commit(
						mutations.SET_ERROR,
						AppGlobal.methods.t('bookmarks', err.message)
					);
					throw err;
				});
		},

		[actions.RENAME_TAG]({ commit, dispatch, state }, { oldName, newName }) {
			commit(mutations.FETCH_START, 'tag');
			return axios
				.put(url(`/tag/${oldName}`), {
					name: newName
				})
				.then(response => {
					const {
						data: { status }
					} = response;
					if (status !== 'success') {
						throw new Error(response.data);
					}
					return dispatch(actions.LOAD_TAGS);
				})
				.catch(err => {
					console.error(err);
					commit(
						mutations.SET_ERROR,
						AppGlobal.methods.t('bookmarks', 'Failed to create bookmark')
					);
					throw err;
				})
				.finally(() => {
					commit(mutations.FETCH_END, 'tag');
				});
		},
		[actions.LOAD_TAGS]({ commit, dispatch, state }, link) {
			if (state.loading.bookmarks) return;
			commit(mutations.FETCH_START, 'tags');
			return axios
				.get(url('/tag'), { params: { count: true } })
				.then(response => {
					const { data: tags } = response;
					return commit(mutations.SET_TAGS, tags);
				})
				.catch(err => {
					console.error(err);
					commit(
						mutations.SET_ERROR,
						AppGlobal.methods.t('bookmarks', 'Failed to load tags')
					);
					throw err;
				})
				.finally(() => {
					commit(mutations.FETCH_END, 'tags');
				});
		},
		[actions.DELETE_TAG]({ commit, dispatch, state }, tag) {
			return axios
				.delete(url(`/tag/${tag}`))
				.then(response => {
					const {
						data: { status }
					} = response;
					if (status !== 'success') {
						throw new Error(response.data);
					}
					dispatch(actions.LOAD_TAGS);
				})
				.catch(err => {
					console.error(err);
					commit(
						mutations.SET_ERROR,
						AppGlobal.methods.t('bookmarks', 'Failed to delete bookmark')
					);
					throw err;
				});
		},

		[actions.LOAD_FOLDERS]({ commit, dispatch, state }) {
			if (state.loading.bookmarks) return;
			commit(mutations.FETCH_START, 'folders');
			return axios
				.get(url('/folder'), { params: {} })
				.then(response => {
					const {
						data: { data, status }
					} = response;
					if (status !== 'success') throw new Error(data);
					const folders = data;
					return commit(mutations.SET_FOLDERS, folders);
				})
				.catch(err => {
					console.error(err);
					commit(
						mutations.SET_ERROR,
						AppGlobal.methods.t('bookmarks', 'Failed to load folders')
					);
					throw err;
				})
				.finally(() => {
					commit(mutations.FETCH_END, 'folders');
				});
		},
		[actions.DELETE_FOLDER]({ commit, dispatch, state }, id) {
			return axios
				.delete(url(`/folder/${id}`))
				.then(response => {
					const {
						data: { status }
					} = response;
					if (status !== 'success') {
						throw new Error(response.data);
					}
					dispatch(actions.LOAD_FOLDERS);
				})
				.catch(err => {
					console.error(err);
					commit(
						mutations.SET_ERROR,
						AppGlobal.methods.t('bookmarks', 'Failed to delete folder')
					);
					throw err;
				});
		},
		[actions.CREATE_FOLDER](
			{ commit, dispatch, state },
			{ parentFolder, title }
		) {
			return axios
				.post(url(`/folder`), {
					parent_folder: parentFolder,
					title
				})
				.then(response => {
					const {
						data: { status }
					} = response;
					if (status !== 'success') {
						throw new Error(response.data);
					}
					commit(mutations.DISPLAY_NEW_FOLDER, false);
					dispatch(actions.LOAD_FOLDERS);
				})
				.catch(err => {
					console.error(err);
					commit(
						mutations.SET_ERROR,
						AppGlobal.methods.t('bookmarks', 'Failed to create folder')
					);
					throw err;
				});
		},
		[actions.SAVE_FOLDER]({ commit, dispatch, state }, id) {
			const folder = this.getters.getFolder(id)[0];
			commit(mutations.FETCH_END, 'saveFolder');
			// ! todo: Check endpoint -- 405
			return axios
				.put(url(`/folder`), {
					parent_folder: folder.parent_folder,
					title: folder.title
				})
				.then(response => {
					const {
						data: { status }
					} = response;
					if (status !== 'success') {
						throw new Error(response.data);
					}
				})
				.catch(err => {
					console.error(err);
					commit(
						mutations.SET_ERROR,
						AppGlobal.methods.t('bookmarks', 'Failed to create folder')
					);
					throw err;
				})
				.finally(() => {
					commit(mutations.FETCH_END, 'saveFolder');
				});
		},

		[actions.NO_FILTER]({ dispatch, commit }) {
			commit(mutations.SET_QUERY, {});
			return dispatch(actions.FETCH_PAGE);
		},
		[actions.FILTER_BY_RECENT]({ dispatch, commit }, search) {
			commit(mutations.SET_QUERY, { sortby: 'lastmodified' });
			return dispatch(actions.FETCH_PAGE);
		},
		[actions.FILTER_BY_SEARCH]({ dispatch, commit }, search) {
			commit(mutations.SET_QUERY, { search: search.split(' ') });
			return dispatch(actions.FETCH_PAGE);
		},
		[actions.FILTER_BY_TAGS]({ dispatch, commit }, tags) {
			commit(mutations.SET_QUERY, { tags });
			return dispatch(actions.FETCH_PAGE);
		},
		[actions.FILTER_BY_UNTAGGED]({ dispatch, commit }) {
			commit(mutations.SET_QUERY, { untagged: true });
			return dispatch(actions.FETCH_PAGE);
		},
		[actions.FILTER_BY_FOLDER]({ dispatch, commit }, folder) {
			commit(mutations.SET_QUERY, { folder });
			return dispatch(actions.FETCH_PAGE);
		},
		[actions.FETCH_PAGE]({ dispatch, commit, state }) {
			if (state.loading.bookmarks) return;
			if (state.fetchState.reachedEnd) return;
			commit(mutations.FETCH_START, 'bookmarks');
			return axios
				.get(url('/bookmark'), {
					params: {
						limit: BATCH_SIZE,
						page: state.fetchState.page,
						sortby: state.settings.sorting,
						...state.fetchState.query
					}
				})
				.then(response => {
					const {
						data: { data, status }
					} = response;
					if (status !== 'success') throw new Error(data);
					const bookmarks = data;
					commit(mutations.INCREMENT_PAGE);
					if (bookmarks.length < BATCH_SIZE) {
						commit(mutations.REACHED_END);
					}
					return dispatch(actions.ADD_ALL_BOOKMARKS, bookmarks);
				})
				.catch(err => {
					console.error(err);
					commit(
						mutations.SET_ERROR,
						AppGlobal.t('bookmarks', 'Failed to fetch bookmarks.')
					);
					throw err;
				})
				.finally(() => {
					commit(mutations.FETCH_END, 'bookmarks');
				});
		},

		[actions.SET_SETTING]({ commit, dispatch, state }, { key, value }) {
			return axios
				.post(url(`/settings/${key}`), {
					[key]: value
				})
				.then(response => {
					commit(mutations.SET_SETTING, key, value);
					if (key === 'viewMode' && state.settings.viewMode !== value) {
						commit(mutations.SET_VIEW_MODE, value);
					}
				})
				.catch(err => {
					console.error(err);
					commit(
						mutations.SET_ERROR,
						AppGlobal.methods.t('bookmarks', 'Failed to change setting')
					);
					throw err;
				});
		},
		[actions.LOAD_SETTING]({ commit, dispatch, state }, key) {
			return axios
				.get(url(`/settings/${key}`))
				.then(response => {
					const {
						data: { [key]: value }
					} = response;
					commit(mutations.SET_SETTING, { key, value });
					if (key === 'viewMode' && state.settings.viewMode !== value) {
						commit(mutations.SET_VIEW_MODE, value);
					}
				})
				.catch(err => {
					console.error(err);
					commit(
						mutations.SET_ERROR,
						AppGlobal.methods.t('bookmarks', 'Failed to load setting ' + key)
					);
					throw err;
				});
		},
		[actions.LOAD_SETTINGS]({ commit, dispatch, state }) {
			return Promise.all(
				['sorting', 'viewMode'].map(key => dispatch(actions.LOAD_SETTING, key))
			);
		}
	}
});

function url(url) {
	url = `/apps/bookmarks${url}`;
	return OC.generateUrl(url);
}

function findFolder(id, children) {
	if (!children || !children.length) return [];
	let folders = children.filter(folder => Number(folder.id) === Number(id));
	if (folders.length) {
		return folders;
	} else {
		for (let child of children) {
			let folders = findFolder(id, child.children);
			if (folders.length) {
				folders.push(child);
				return folders;
			}
		}
		return [];
	}
}
