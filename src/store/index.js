import Vue from 'vue'
import Vuex, { Store } from 'vuex'
import Mutations from './mutations'
import Actions from './actions'

Vue.use(Vuex)

export { mutations } from './mutations'

export { actions } from './actions'

export default new Store({
	mutations: Mutations,
	actions: Actions,
	state: {
		fetchState: {
			page: 0,
			query: {},
			reachedEnd: false,
		},
		loading: {
			tags: false,
			folders: false,
			bookmarks: false,
			createBookmark: false,
			saveBookmark: false,
			createFolder: false,
			saveFolder: false,
		},
		error: null,
		settings: {
			viewMode: 'list',
			sorting: 'lastmodified',
		},
		bookmarks: [],
		bookmarksById: {},
		tags: [],
		folders: [],
		foldersById: {},
		selection: {
			folders: [],
			bookmarks: [],
		},
		displayNewBookmark: false,
		displayNewFolder: false,
		displayMoveDialog: false,
		sidebar: null,
		viewMode: 'list',
	},

	getters: {
		getBookmark: state => id => {
			return state.bookmarksById[id]
		},
		getFolder: state => id => {
			if (Number(id) === -1) {
				return [{ id: '-1', children: state.folders }]
			}
			return findFolder(id, state.folders)
		},
	},
})

function findFolder(id, children) {
	if (!children || !children.length) return []
	const folders = children.filter(folder => Number(folder.id) === Number(id))
	if (folders.length) {
		return folders
	} else {
		for (const child of children) {
			const folders = findFolder(id, child.children)
			if (folders.length) {
				folders.push(child)
				return folders
			}
		}
		return []
	}
}
