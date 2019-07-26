import Vue from 'vue';
import Vuex from 'vuex';
import axios from 'nextcloud-axios';
import AppGlobal from './mixins/AppGlobal';

Vue.use(Vuex);

const BATCH_SIZE = 42;

export const mutations = {
	ADD: 'ADD',
	REMOVE: 'REMOVE',
	REMOVE_ALL: 'REMOVE_ALL',
	SET_SIDEBAR_OPEN: 'SET_SIDEBAR_OPEN',
	INCREMENT_PAGE: 'INCREMENT_PAGE',
	SET_QUERY: 'SET_QUERY',
	SET_SORTBY: 'SET_SORTBY',
	SET_FETCHING: 'SET_FETCHING',
	SET_REACHED_END: 'SET_REACHED_END',
	SET_ERROR: 'SET_ERROR'
};

export const actions = {
	ADD_ALL: 'ADD_ALL',
	FILTER_BY_TAGS: 'FILTER_BY_TAGS',
	FILTER_BY_FOLDER: 'FILTER_BY_FOLDER',
	FILTER_BY_SEARCH: 'FILTER_BY_SEARCH'
};

export default new Vuex.Store({
	state: {
		fetchState: {
			page: 0,
			query: {},
			fetching: false,
			reachedEnd: false,
			sortby: 'lastmodified'
		},
		bookmarks: [],
		bookmarksById: {},
		sidebarOpen: false,
		page: 0
	},

	getters: {
		getNote: state => id => {
			if (state.bookmarksById[id] === undefined) {
				return null;
			}
			return state.bookmarksById[id];
		}
	},

	mutations: {
		[mutations.SET_ERROR](state, error) {
			state.error = error;
		},

		[mutations.ADD](state, bookmark) {
			const existingBookmark = state.bookmarksById[bookmark.id];
			if (!existingBookmark) {
				state.bookmarks.push(bookmark);
				Vue.set(state.bookmarksById, bookmark.id, bookmark);
			}
		},

		[mutations.REMOVE](state, id) {
			const index = state.bookmarks.findIndex(bookmark => bookmark.id === id);
			if (index !== -1) {
				state.bookmarks.splice(index, 1);
				Vue.delete(state.bookmarksById, id);
			}
		},

		[mutations.REMOVE_ALL](state) {
			state.notes = [];
			state.bookmarksById = {};
		},

		[mutations.SET_SIDEBAR_OPEN](state, open) {
			state.sidebarOpen = open;
		},

		[mutations.INCREMENT_PAGE](state) {
			Vue.set(state.fetchState, 'page', state.fetchState.page + 1);
		},

		[mutations.SET_QUERY](state, query) {
			Vue.set(state.fetchState, 'page', 0);
			Vue.set(state.fetchState, 'reachedEnd', false);
			Vue.set(state.fetchState, 'query', query);
		},

		[mutations.FETCH_START](state) {
			Vue.set(state.fetchState, 'fetching', true);
		},

		[mutations.FETCH_END](state) {
			Vue.set(state.fetchState, 'fetching', false);
		},

		[mutations.REACHED_END](state) {
			Vue.set(state.fetchState, 'reachedEnd', true);
		}
	},

	actions: {
		[actions.ADD_ALL]({ commit }, bookmarks) {
			for (const bookmark of bookmarks) {
				commit(mutations.ADD, bookmark);
			}
		},
		[actions.FILTER_BY_SEARCH]({ dispatch, commit }, search) {
			commit(mutations.SET_QUERY, { search: search.split(' ') });
			return dispatch(actions.FETCH_PAGE);
		},

		[actions.FILTER_BY_TAGS]({ dispatch, commit }, tags) {
			commit(mutations.SET_QUERY, { tags });
			return dispatch(actions.FETCH_PAGE);
		},
		[actions.FILTER_BY_FOLDER]({ dispatch, commit }, folder) {
			commit(mutations.SET_QUERY, { folder });
			return dispatch(actions.FETCH_PAGE);
		},
		[actions.FETCH_PAGE]({ dispatch, commit, state }) {
			if (state.fetchState.fetching) return;
			commit(mutations.FETCH_START);
			return axios
				.get(url('/bookmark'), {
					...state.fetchState.query,
					limit: BATCH_SIZE,
					page: state.fetchQuery.page
				})
				.then(response => {
					const bookmarks = response.data;
					commit(mutations.INCREMENT_PAGE);
					return dispatch(actions.ADD_ALL, bookmarks);
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
					commit(mutations.FETCH_END);
				});
		}
	}
});

function url(url) {
	url = `/apps/bookmarks/public/rest/v2${url}`;
	return OC.generateUrl(url);
}
