import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
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
	DELETE_BOOKMARKS: 'DELETE_BOOKMARKS',

	LOAD_TAGS: 'LOAD_TAGS',
	RENAME_TAG: 'RENAME_TAG',
	DELETE_TAG: 'DELETE_TAG',

	LOAD_FOLDERS: 'LOAD_FOLDERS',
	CREATE_FOLDER: 'CREATE_FOLDER',
	SAVE_FOLDER: 'SAVE_FOLDER',
	DELETE_FOLDER: 'DELETE_FOLDER',
	OPEN_FOLDER_DETAILS: 'OPEN_FOLDER_DETAILS',

	MOVE_SELECTION: 'MOVE_SELECTION',
	DELETE_SELECTION: 'DELETE_SELECTION',

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
	LOAD_SETTINGS: 'SLOAD_SETTINGS',

	LOAD_SHARES_OF_FOLDER: 'LOAD_SHARES_OF_FOLDER',
	CREATE_SHARE: 'CREATE_SHARE',
	EDIT_SHARE: 'EDIT_SHARE',
	DELETE_SHARE: 'DELETE_SHARE',

	LOAD_PUBLIC_LINK: 'LOAD_PUBLIC_LINK',
	CREATE_PUBLIC_LINK: 'CREATE_PUBLIC_LINK',
	DELETE_PUBLIC_LINK: 'DELETE_PUBLIC_LINK',
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
			const response = await axios.get(url(state, '/bookmark'), {
				params: {
					url: link,
				},
			})
			const {
				data: { data: bookmarks, status },
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
		commit(mutations.FETCH_START, { type: 'createBookmark' })
		return axios
			.post(url(state, '/bookmark'), {
				url: data.url,
				title: data.title,
				description: data.description,
				folders: data.folders && data.folders.map(parseInt),
				tags: data.tags,
			})
			.then(response => {
				const {
					data: { item: bookmark, status },
				} = response
				if (status !== 'success') {
					throw new Error(response.data.data.join('\n'))
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
		commit(mutations.FETCH_START, { type: 'saveBookmark' })
		return axios
			.put(url(state, `/bookmark/${id}`), this.getters.getBookmark(id))
			.then(response => {
				const {
					data: { status },
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
		commit(mutations.FETCH_START, { type: 'moveBookmark' })
		try {
			const response = await axios.post(
				url(state, `/folder/${newFolder}/bookmarks/${bookmark}`)
			)
			if (response.data.status !== 'success') {
				throw new Error(response.data)
			}
			const response2 = await axios.delete(
				url(state, `/folder/${oldFolder}/bookmarks/${bookmark}`)
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
					url(state, `/folder/${folder}/bookmarks/${id}`)
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
			const response = await axios.delete(url(state, `/bookmark/${id}`))
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
		const data = new FormData()
		data.append('bm_import', file)
		return axios
			.post(url(state, `/bookmark/import`), data)
			.then(response => {
				if (!response.data || !response.data.status === 'success') {
					if (response.status === 413) {
						throw new Error('Selected file is too large')
					}
					console.error('Failed to import bookmarks', response)
					throw new Error(response.data)
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
			.delete(url(state, `/bookmark`))
			.then(response => {
				const {
					data: { status },
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
		commit(mutations.FETCH_START, { type: 'tag' })
		return axios
			.put(url(state, `/tag/${oldName}`), {
				name: newName,
			})
			.then(response => {
				const {
					data: { status },
				} = response
				if (status !== 'success') {
					throw new Error(response.data)
				}
				commit(mutations.RENAME_TAG, { oldName, newName })
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
	[actions.LOAD_TAGS]({ commit, dispatch, state }) {
		commit(mutations.FETCH_START, { type: 'tags' })
		return axios
			.get(url(state, '/tag'), { params: { count: true } })
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
			.delete(url(state, `/tag/${tag}`))
			.then(response => {
				const {
					data: { status },
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
		let canceled = false
		commit(mutations.FETCH_START, {
			type: 'folders',
			cancel: () => {
				canceled = true
			},
		})
		return axios
			.get(url(state, '/folder'), { params: {} })
			.then(response => {
				if (canceled) return
				const {
					data: { data, status },
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
			.delete(url(state, `/folder/${id}`))
			.then(response => {
				const {
					data: { status },
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
			.post(url(state, `/folder`), {
				parent_folder: parentFolder,
				title,
			})
			.then(response => {
				const {
					data: { status },
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
		commit(mutations.FETCH_START, { type: 'saveFolder' })
		return axios
			.put(url(state, `/folder/${id}`), {
				parent_folder: folder.parent_folder,
				title: folder.title,
			})
			.then(response => {
				const {
					data: { status },
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
	[actions.OPEN_FOLDER_DETAILS]({ commit }, id) {
		commit(mutations.SET_SIDEBAR, { type: 'folder', id })
	},

	async [actions.MOVE_SELECTION]({ commit, dispatch, state }, folderId) {
		commit(mutations.FETCH_START, { type: 'moveSelection' })
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
					bookmark: bookmark.id,
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
	async [actions.DELETE_SELECTION]({ commit, dispatch, state }) {
		commit(mutations.FETCH_START, { type: 'deleteSelection' })
		try {
			for (const folder of state.selection.folders) {
				await dispatch(actions.DELETE_FOLDER, folder.id)
			}

			for (const bookmark of state.selection.bookmarks) {
				await dispatch(actions.DELETE_BOOKMARK, { id: bookmark.id })
			}
		} catch (err) {
			console.error(err)
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t('bookmarks', 'Failed to delete parts of selection')
			)
			throw err
		} finally {
			commit(mutations.FETCH_END, 'deleteSelection')
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
		if (state.fetchState.reachedEnd) return
		let canceled = false
		commit(mutations.FETCH_START, {
			type: 'bookmarks',
			cancel() {
				canceled = true
			},
		})
		axios
			.get(url(state, '/bookmark'), {
				params: {
					limit: BATCH_SIZE,
					page: state.fetchState.page,
					sortby: state.settings.sorting,
					...state.fetchState.query,
				},
			})
			.then(response => {
				if (canceled) return
				const {
					data: { data, status },
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
				if (canceled) return
				commit(mutations.FETCH_END, 'bookmarks')
			})
	},

	async [actions.SET_SETTING]({ commit, dispatch, state }, { key, value }) {
		await commit(mutations.SET_SETTING, { key, value })
		if (key === 'viewMode') {
			await commit(mutations.SET_VIEW_MODE, value)
		}
		if (key === 'sorting') {
			await commit(mutations.RESET_PAGE)
		}
		if (state.public) {
			return
		}
		return axios
			.post(url(state, `/settings/${key}`), {
				[key]: value,
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
			.get(url(state, `/settings/${key}`))
			.then(async response => {
				const {
					data: { [key]: value },
				} = response
				await commit(mutations.SET_SETTING, { key, value })
				if (key === 'viewMode') {
					await commit(mutations.SET_VIEW_MODE, value)
				}
				if (key === 'sorting') {
					await commit(mutations.RESET_PAGE)
				}
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to load setting {key}', { key })
				)
				throw err
			})
	},
	[actions.LOAD_SETTINGS]({ commit, dispatch, state }) {
		return Promise.all(
			['sorting', 'viewMode'].map(key => dispatch(actions.LOAD_SETTING, key))
		)
	},

	[actions.LOAD_SHARES_OF_FOLDER]({ commit, dispatch, state }, folderId) {
		if (folderId === -1 || folderId === '-1') {
			return Promise.resolve()
		}
		return axios
			.get(url(state, `/folder/${folderId}/shares`))
			.then(async response => {
				const {
					data: { data, status },
				} = response
				if (status !== 'success') throw new Error(data)
				const shares = data
				for (const share of shares) {
					await commit(mutations.ADD_SHARE, share)
				}
			})
			.catch(err => {
				console.error(err)
				// Don't set a notification as this is expected to happen for subfolders of shares that we don't have a RESHAR permission for
				throw err
			})
	},
	[actions.CREATE_SHARE]({ commit, dispatch, state }, { folderId, type, participant }) {
		return axios
			.post(url(state, `/folder/${folderId}/shares`), {
				folderId,
				participant,
				type,
			})
			.then(async response => {
				const {
					data: { item, data, status },
				} = response
				if (status !== 'success') throw new Error(data)
				await commit(mutations.ADD_SHARE, item)
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to create share for folder {folderId}', { folderId })
				)
				throw err
			})
	},
	[actions.EDIT_SHARE]({ commit, dispatch, state }, { shareId, canWrite, canShare }) {
		return axios
			.put(url(state, `/share/${shareId}`), {
				canWrite,
				canShare,
			})
			.then(async response => {
				const {
					data: { item, data, status },
				} = response
				if (status !== 'success') throw new Error(data)
				await commit(mutations.ADD_SHARE, item)
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to update share {shareId}', { shareId })
				)
				throw err
			})
	},
	[actions.DELETE_SHARE]({ commit, dispatch, state }, shareId) {
		return axios
			.delete(url(state, `/share/${shareId}`))
			.then(async response => {
				const {
					data: { data, status },
				} = response
				if (status !== 'success') throw new Error(data)
				await commit(mutations.REMOVE_SHARE, shareId)
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to delete share {shareId}', { shareId })
				)
				throw err
			})
	},

	[actions.LOAD_PUBLIC_LINK]({ commit, dispatch, state }, folderId) {
		return axios
			.get(url(state, `/folder/${folderId}/publictoken`), {
				validateStatus: (status) => status === 404 || status === 200,
			})
			.then(async response => {
				const {
					data: { item, data, status },
				} = response
				if (response.status === 404) {
					return
				}
				if (status !== 'success') throw new Error(data)
				const token = item
				await commit(mutations.ADD_PUBLIC_TOKEN, { folderId, token })
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to load public link of folder {folderId}', { folderId })
				)
				throw err
			})
	},
	[actions.CREATE_PUBLIC_LINK]({ commit, dispatch, state }, folderId) {
		return axios
			.post(url(state, `/folder/${folderId}/publictoken`))
			.then(async response => {
				const {
					data: { item, data, status },
				} = response
				if (status !== 'success') throw new Error(data)
				const token = item
				await commit(mutations.ADD_PUBLIC_TOKEN, { folderId, token })
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to create public link for folder {folderId}', { folderId })
				)
				throw err
			})
	},
	[actions.DELETE_PUBLIC_LINK]({ commit, dispatch, state }, folderId) {
		return axios
			.delete(url(state, `/folder/${folderId}/publictoken`))
			.then(async response => {
				const {
					data: { data, status },
				} = response
				if (status !== 'success') throw new Error(data)
				await commit(mutations.REMOVE_PUBLIC_TOKEN, { folderId })
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to delete public link for folder {folderId}', { folderId })
				)
				throw err
			})
	},
}

function url(state, url) {
	if (state.public) {
		url = `/apps/bookmarks/public/rest/v2${url}`
	} else {
		url = `/apps/bookmarks${url}`
	}
	return generateUrl(url)
}
