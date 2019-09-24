import axios from 'nextcloud-axios'
import { generateUrl } from 'nextcloud-router'
import AppGlobal from '../mixins/AppGlobal'
import { mutations } from './mutations'

const BATCH_SIZE = 42

export const actions = {
	ADD_ALL_BOOKMARKS: 'ADD_ALL_BOOKMARKS',
	CREATE_BOOKMARK: 'CREATE_BOOKMARK',
	FIND_BOOKMARK: 'FIND_BOOKMARK',
	DELETE_BOOKMARK: 'DELETE_BOOKMARK',
	OPEN_BOOKMARK: 'OPEN_BOOKMARK',
	SAVE_BOOKMARK: 'SAVE_BOOKMARK',
	MOVE_BOOKMARK: 'MOVE_BOOKMARK',
	IMPORT_BOOKMARKS: 'IMPORT_BOOKMARKS',
	DELETE_BOOKMARKS: 'IMPORT_BOOKMARKS',

	LOAD_TAGS: 'LOAD_TAGS',
	RENAME_TAG: 'RENAME_TAG',
	DELETE_TAG: 'DELETE_TAG',

	LOAD_FOLDERS: 'LOAD_FOLDERS',
	CREATE_FOLDER: 'CREATE_FOLDER',
	SAVE_FOLDER: 'SAVE_FOLDER',
	DELETE_FOLDER: 'DELETE_FOLDER',

	MOVE_SELECTION: 'MOVE_SELECTION',

	RELOAD_VIEW: 'RELOAD_VIEW',

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
}

export default {
	[actions.ADD_ALL_BOOKMARKS]({ commit }, bookmarks) {
		for (const bookmark of bookmarks) {
			commit(mutations.ADD_BOOKMARK, bookmark)
		}
	},

	async [actions.FIND_BOOKMARK]({ commit, dispatch, state }, link) {
		if (state.loading.bookmarks) return
		try {
			const response = await axios.get(url('/bookmark'), {
				params: {
					url: link
				}
			})
			const {
				data: { data: bookmarks, status }
			} = response
			if (status !== 'success') {
				throw new Error(response.data)
			}
			if (!bookmarks.length) return
			commit(mutations.ADD_BOOKMARK, bookmarks[0])
			return bookmarks[0]
		} catch (err) {
			console.error(err)
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t('bookmarks', 'Failed to find existing bookmark')
			)
			throw err
		}
	},
	[actions.CREATE_BOOKMARK]({ commit, dispatch, state }, data) {
		if (state.loading.bookmarks) return
		commit(mutations.FETCH_START, 'createBookmark')
		return axios
			.post(url('/bookmark'), {
				url: data.url,
				title: data.title,
				description: data.description,
				folders: data.folders,
				tags: data.tags
			})
			.then(response => {
				const {
					data: { item: bookmark, status }
				} = response
				if (status !== 'success') {
					throw new Error(response.data)
				}
				commit(mutations.DISPLAY_NEW_BOOKMARK, false)
				commit(mutations.ADD_BOOKMARK, bookmark)
				return dispatch(actions.OPEN_BOOKMARK, bookmark.id)
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to create bookmark')
				)
				throw err
			})
			.finally(() => {
				commit(mutations.FETCH_END, 'createBookmark')
			})
	},
	[actions.SAVE_BOOKMARK]({ commit, dispatch, state }, id) {
		commit(mutations.FETCH_START, 'saveBookmark')
		return axios
			.put(url(`/bookmark/${id}`), this.getters.getBookmark(id))
			.then(response => {
				const {
					data: { status }
				} = response
				if (status !== 'success') {
					throw new Error(response.data)
				}
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to save bookmark')
				)
				throw err
			})
			.finally(() => {
				commit(mutations.FETCH_END, 'saveBookmark')
			})
	},
	async [actions.MOVE_BOOKMARK](
		{ commit, dispatch, state },
		{ bookmark, oldFolder, newFolder }
	) {
		commit(mutations.FETCH_START, 'moveBookmark')
		try {
			let response = await axios.post(
				url(`/folder/${newFolder}/bookmarks/${bookmark}`)
			)
			if (response.data.status !== 'success') {
				throw new Error(response.data)
			}
			let response2 = await axios.delete(
				url(`/folder/${oldFolder}/bookmarks/${bookmark}`)
			)
			if (response2.data.status !== 'success') {
				throw new Error(response2.data)
			}
		} catch (err) {
			console.error(err)
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t('bookmarks', 'Failed to move bookmark')
			)
			throw err
		} finally {
			commit(mutations.FETCH_END, 'moveBookmark')
		}
	},
	[actions.OPEN_BOOKMARK]({ commit }, id) {
		commit(mutations.SET_SIDEBAR, { type: 'bookmark', id })
	},
	async [actions.DELETE_BOOKMARK]({ commit, dispatch, state }, { id, folder }) {
		if (folder) {
			try {
				const response = await axios.delete(
					url(`/folder/${folder}/bookmarks/${id}`)
				)
				if (response.data.status !== 'success') {
					throw new Error(response.data)
				}
				commit(mutations.REMOVE_BOOKMARK, id)
			} catch (err) {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to delete bookmark')
				)
				throw err
			}
			return
		}
		try {
			const response = await axios.delete(url(`/bookmark/${id}`))
			if (response.data.status !== 'success') {
				throw new Error(response.data)
			}
			commit(mutations.REMOVE_BOOKMARK, id)
		} catch (err) {
			console.error(err)
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t('bookmarks', 'Failed to delete bookmark')
			)
			throw err
		}
	},
	[actions.IMPORT_BOOKMARKS]({ commit, dispatch, state }, file) {
		var data = new FormData()
		data.append('bm_import', file)
		return axios
			.post(url(`/bookmark/import`), data)
			.then(response => {
				if (!response.ok) {
					if (response.status === 413) {
						throw new Error('Selected file is too large')
					}
					throw new Error(response.statusText)
				} else {
					const {
						data: { status }
					} = response
					if (status !== 'success') {
						throw new Error(response.data)
					}
				}
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', err.message)
				)
				throw err
			})
	},
	[actions.DELETE_BOOKMARKS]({ commit, dispatch, state }) {
		return axios
			.delete(url(`/bookmark`))
			.then(response => {
				const {
					data: { status }
				} = response
				if (status !== 'success') {
					throw new Error(response.data)
				}
				return dispatch(actions.LOAD_FOLDERS)
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', err.message)
				)
				throw err
			})
	},

	[actions.RENAME_TAG]({ commit, dispatch, state }, { oldName, newName }) {
		commit(mutations.FETCH_START, 'tag')
		return axios
			.put(url(`/tag/${oldName}`), {
				name: newName
			})
			.then(response => {
				const {
					data: { status }
				} = response
				if (status !== 'success') {
					throw new Error(response.data)
				}
				return dispatch(actions.LOAD_TAGS)
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to create bookmark')
				)
				throw err
			})
			.finally(() => {
				commit(mutations.FETCH_END, 'tag')
			})
	},
	[actions.LOAD_TAGS]({ commit, dispatch, state }, link) {
		if (state.loading.bookmarks) return
		commit(mutations.FETCH_START, 'tags')
		return axios
			.get(url('/tag'), { params: { count: true } })
			.then(response => {
				const { data: tags } = response
				return commit(mutations.SET_TAGS, tags)
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to load tags')
				)
				throw err
			})
			.finally(() => {
				commit(mutations.FETCH_END, 'tags')
			})
	},
	[actions.DELETE_TAG]({ commit, dispatch, state }, tag) {
		return axios
			.delete(url(`/tag/${tag}`))
			.then(response => {
				const {
					data: { status }
				} = response
				if (status !== 'success') {
					throw new Error(response.data)
				}
				dispatch(actions.LOAD_TAGS)
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to delete bookmark')
				)
				throw err
			})
	},

	[actions.LOAD_FOLDERS]({ commit, dispatch, state }) {
		if (state.loading.bookmarks) return
		commit(mutations.FETCH_START, 'folders')
		return axios
			.get(url('/folder'), { params: {} })
			.then(response => {
				const {
					data: { data, status }
				} = response
				if (status !== 'success') throw new Error(data)
				const folders = data
				return commit(mutations.SET_FOLDERS, folders)
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to load folders')
				)
				throw err
			})
			.finally(() => {
				commit(mutations.FETCH_END, 'folders')
			})
	},
	[actions.DELETE_FOLDER]({ commit, dispatch, state }, id) {
		return axios
			.delete(url(`/folder/${id}`))
			.then(response => {
				const {
					data: { status }
				} = response
				if (status !== 'success') {
					throw new Error(response.data)
				}
				dispatch(actions.LOAD_FOLDERS)
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to delete folder')
				)
				throw err
			})
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
				} = response
				if (status !== 'success') {
					throw new Error(response.data)
				}
				commit(mutations.DISPLAY_NEW_FOLDER, false)
				dispatch(actions.LOAD_FOLDERS)
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to create folder')
				)
				throw err
			})
	},
	[actions.SAVE_FOLDER]({ commit, dispatch, state }, id) {
		const folder = this.getters.getFolder(id)[0]
		commit(mutations.FETCH_START, 'saveFolder')
		return axios
			.put(url(`/folder/${id}`), {
				parent_folder: folder.parent_folder,
				title: folder.title
			})
			.then(response => {
				const {
					data: { status }
				} = response
				if (status !== 'success') {
					throw new Error(response.data)
				}
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to create folder')
				)
				throw err
			})
			.finally(() => {
				commit(mutations.FETCH_END, 'saveFolder')
			})
	},

	async [actions.MOVE_SELECTION]({ commit, dispatch, state }, folderId) {
		commit(mutations.FETCH_START, 'moveSelection')
		try {
			for (const folder of state.selection.folders) {
				if (folderId === folder.id) {
					throw new Error('Cannot move folder into itself')
				}
				folder.parent_folder = folderId
				await dispatch(actions.SAVE_FOLDER, folder.id)
			}

			for (const bookmark of state.selection.bookmarks) {
				await dispatch(actions.MOVE_BOOKMARK, {
					oldFolder: bookmark.folders[bookmark.folders.length - 1], // FIXME This is veeeery ugly and will cause issues. Inevitably.
					newFolder: folderId,
					bookmark: bookmark.id
				})
			}
		} catch (err) {
			console.error(err)
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t('bookmarks', 'Failed to move parts of selection')
			)
			throw err
		} finally {
			commit(mutations.FETCH_END, 'moveSelection')
		}
	},

	[actions.RELOAD_VIEW]({ state, dispatch, commit }) {
		commit(mutations.SET_QUERY, state.fetchState.query)
		dispatch(actions.FETCH_PAGE)
		dispatch(actions.LOAD_FOLDERS)
		dispatch(actions.LOAD_TAGS)
	},

	[actions.NO_FILTER]({ dispatch, commit }) {
		commit(mutations.SET_QUERY, {})
		return dispatch(actions.FETCH_PAGE)
	},
	[actions.FILTER_BY_RECENT]({ dispatch, commit }, search) {
		commit(mutations.SET_QUERY, { sortby: 'lastmodified' })
		return dispatch(actions.FETCH_PAGE)
	},
	[actions.FILTER_BY_SEARCH]({ dispatch, commit }, search) {
		commit(mutations.SET_QUERY, { search: search.split(' ') })
		return dispatch(actions.FETCH_PAGE)
	},
	[actions.FILTER_BY_TAGS]({ dispatch, commit }, tags) {
		commit(mutations.SET_QUERY, { tags, conjunction: 'and' })
		return dispatch(actions.FETCH_PAGE)
	},
	[actions.FILTER_BY_UNTAGGED]({ dispatch, commit }) {
		commit(mutations.SET_QUERY, { untagged: true })
		return dispatch(actions.FETCH_PAGE)
	},
	[actions.FILTER_BY_FOLDER]({ dispatch, commit }, folder) {
		commit(mutations.SET_QUERY, { folder })
		return dispatch(actions.FETCH_PAGE)
	},
	[actions.FETCH_PAGE]({ dispatch, commit, state }) {
		if (state.loading.bookmarks) return
		if (state.fetchState.reachedEnd) return
		commit(mutations.FETCH_START, 'bookmarks')
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
				} = response
				if (status !== 'success') throw new Error(data)
				const bookmarks = data
				commit(mutations.INCREMENT_PAGE)
				if (bookmarks.length < BATCH_SIZE) {
					commit(mutations.REACHED_END)
				}
				return dispatch(actions.ADD_ALL_BOOKMARKS, bookmarks)
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.t('bookmarks', 'Failed to fetch bookmarks.')
				)
				throw err
			})
			.finally(() => {
				commit(mutations.FETCH_END, 'bookmarks')
			})
	},

	[actions.SET_SETTING]({ commit, dispatch, state }, { key, value }) {
		commit(mutations.SET_SETTING, key, value)
		if (key === 'viewMode' && state.viewMode !== value) {
			commit(mutations.SET_VIEW_MODE, value)
		}
		return axios
			.post(url(`/settings/${key}`), {
				[key]: value
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to change setting')
				)
				throw err
			})
	},
	[actions.LOAD_SETTING]({ commit, dispatch, state }, key) {
		return axios
			.get(url(`/settings/${key}`))
			.then(response => {
				const {
					data: { [key]: value }
				} = response
				commit(mutations.SET_SETTING, { key, value })
				if (key === 'viewMode' && state.viewMode !== value) {
					commit(mutations.SET_VIEW_MODE, value)
				}
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to load setting ' + key)
				)
				throw err
			})
	},
	[actions.LOAD_SETTINGS]({ commit, dispatch, state }) {
		return Promise.all(
			['sorting', 'viewMode'].map(key => dispatch(actions.LOAD_SETTING, key))
		)
	}
}

function url(url) {
	url = `/apps/bookmarks${url}`
	return generateUrl(url)
}
