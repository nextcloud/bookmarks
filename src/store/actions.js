/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import AppGlobal from '../mixins/AppGlobal'
import { mutations } from './mutations'
import * as Parallel from 'async-parallel'

const BATCH_SIZE = 42

export const actions = {
	ADD_ALL_BOOKMARKS: 'ADD_ALL_BOOKMARKS',
	COUNT_BOOKMARKS: 'COUNT_BOOKMARKS',
	COUNT_UNAVAILABLE: 'COUNT_UNAVAILABLE',
	COUNT_ARCHIVED: 'COUNT_ARCHIVED',
	CREATE_BOOKMARK: 'CREATE_BOOKMARK',
	FIND_BOOKMARK: 'FIND_BOOKMARK',
	LOAD_BOOKMARK: 'LOAD_BOOKMARK',
	DELETE_BOOKMARK: 'DELETE_BOOKMARK',
	OPEN_BOOKMARK: 'OPEN_BOOKMARK',
	SAVE_BOOKMARK: 'SAVE_BOOKMARK',
	MOVE_BOOKMARK: 'MOVE_BOOKMARK',
	CLICK_BOOKMARK: 'CLICK_BOOKMARK',
	IMPORT_BOOKMARKS: 'IMPORT_BOOKMARKS',
	DELETE_BOOKMARKS: 'DELETE_BOOKMARKS',

	LOAD_TAGS: 'LOAD_TAGS',
	RENAME_TAG: 'RENAME_TAG',
	DELETE_TAG: 'DELETE_TAG',

	LOAD_FOLDERS: 'LOAD_FOLDERS',
	CREATE_FOLDER: 'CREATE_FOLDER',
	SAVE_FOLDER: 'SAVE_FOLDER',
	DELETE_FOLDER: 'DELETE_FOLDER',
	LOAD_FOLDER_CHILDREN_ORDER: 'LOAD_FOLDER_CHILDREN_ORDER',
	OPEN_FOLDER_DETAILS: 'OPEN_FOLDER_DETAILS',

	MOVE_SELECTION: 'MOVE_SELECTION',
	DELETE_SELECTION: 'DELETE_SELECTION',

	RELOAD_VIEW: 'RELOAD_VIEW',

	NO_FILTER: 'NO_FILTER',
	FILTER_BY_RECENT: 'FILTER_BY_RECENT',
	FILTER_BY_UNTAGGED: 'FILTER_BY_UNTAGGED',
	FILTER_BY_UNAVAILABLE: 'FILTER_BY_UNAVAILABLE',
	FILTER_BY_ARCHIVED: 'FILTER_BY_ARCHIVED',
	FILTER_BY_TAGS: 'FILTER_BY_TAGS',
	FILTER_BY_FOLDER: 'FILTER_BY_FOLDER',
	FILTER_BY_SEARCH: 'FILTER_BY_SEARCH',
	FETCH_PAGE: 'FETCH_PAGE',
	FETCH_ALL: 'FETCH_ALL',

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

	async [actions.COUNT_UNAVAILABLE]({ commit, dispatch, state }, folderId) {
		try {
			const response = await axios.get(
				url(state, '/bookmark/unavailable')
			)
			const {
				data: { item: count, data, status },
			} = response
			if (status !== 'success') {
				throw new Error(data)
			}
			commit(mutations.SET_UNAVAILABLE_COUNT, count)
		} catch (err) {
			console.error(err)
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t(
					'bookmarks',
					'Failed to count unavailable bookmarks'
				)
			)
			throw err
		}
	},
	async [actions.COUNT_ARCHIVED]({ commit, dispatch, state }, folderId) {
		try {
			const response = await axios.get(url(state, '/bookmark/archived'))
			const {
				data: { item: count, data, status },
			} = response
			if (status !== 'success') {
				throw new Error(data)
			}
			commit(mutations.SET_ARCHIVED_COUNT, count)
		} catch (err) {
			console.error(err)
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t(
					'bookmarks',
					'Failed to count archived bookmarks'
				)
			)
			throw err
		}
	},
	async [actions.COUNT_BOOKMARKS]({ commit, dispatch, state }, folderId) {
		try {
			const response = await axios.get(
				url(state, `/folder/${folderId}/count`)
			)
			const {
				data: { item: count, data, status },
			} = response
			if (status !== 'success') {
				throw new Error(data)
			}
			commit(mutations.SET_BOOKMARK_COUNT, { folderId, count })
		} catch (err) {
			console.error(err)
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t('bookmarks', 'Failed to count bookmarks')
			)
			throw err
		}
	},
	async [actions.LOAD_BOOKMARK]({ commit, dispatch, state }, id) {
		try {
			const response = await axios.get(url(state, `/bookmark/${id}`))
			const {
				data: { item: bookmark, status },
			} = response
			if (status !== 'success') {
				throw new Error(response.data)
			}
			commit(mutations.ADD_BOOKMARK, bookmark)
			return bookmark
		} catch (err) {
			console.error(err)
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t('bookmarks', 'Failed to load bookmark')
			)
			throw err
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
				AppGlobal.methods.t(
					'bookmarks',
					'Failed to find existing bookmark'
				)
			)
			throw err
		}
	},
	async [actions.CREATE_BOOKMARK]({ commit, dispatch, state }, data) {
		if (state.loading.bookmarks) return
		commit(mutations.FETCH_START, { type: 'createBookmark' })
		try {
			const response = await axios.post(url(state, '/bookmark'), {
				url: data.url,
				title: data.title,
				description: data.description,
				folders: data.folders && data.folders.map(parseInt),
				tags: data.tags,
			})
			const {
				data: { item: bookmark, status },
			} = response
			if (status !== 'success') {
				throw new Error(response.data.data.join('\n'))
			}
			commit(mutations.DISPLAY_NEW_BOOKMARK, false)
			commit(mutations.FETCH_END, 'createBookmark')
			commit(mutations.SET_BOOKMARK_COUNT, {
				folderId: -1,
				count: state.countsByFolder[-1] + 1,
			})
			dispatch(actions.LOAD_FOLDER_CHILDREN_ORDER, -1)
			dispatch(actions.RELOAD_VIEW)
			if (data.folders) {
				for (const folderId of data.folders) {
					commit(mutations.SET_BOOKMARK_COUNT, {
						folderId,
						count: state.countsByFolder[folderId] + 1,
					})
					dispatch(actions.LOAD_FOLDER_CHILDREN_ORDER, folderId)
				}
			}
			return dispatch(actions.OPEN_BOOKMARK, bookmark.id)
		} catch (err) {
			console.error(err)
			commit(mutations.FETCH_END, 'createBookmark')
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t('bookmarks', 'Failed to create bookmark')
			)
			throw err
		}
	},
	async [actions.SAVE_BOOKMARK]({ commit, dispatch, state }, id) {
		commit(mutations.FETCH_START, { type: 'saveBookmark' })
		try {
			const response = await axios.put(
				url(state, `/bookmark/${id}`),
				this.getters.getBookmark(id)
			)
			const {
				data: { status },
			} = response
			if (status !== 'success') {
				throw new Error(response.data)
			}
			commit(mutations.FETCH_END, 'saveBookmark')
		} catch (err) {
			console.error(err)
			commit(mutations.FETCH_END, 'saveBookmark')
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t('bookmarks', 'Failed to save bookmark')
			)
			throw err
		}
	},
	async [actions.MOVE_BOOKMARK](
		{ commit, dispatch, state },
		{ bookmark, oldFolder, newFolder }
	) {
		if (Number(oldFolder) === Number(newFolder)) {
			return
		}
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
			commit(mutations.FETCH_END, 'moveBookmark')
			dispatch(actions.LOAD_FOLDER_CHILDREN_ORDER, oldFolder)
			dispatch(actions.LOAD_FOLDER_CHILDREN_ORDER, newFolder)
		} catch (err) {
			console.error(err)
			commit(mutations.FETCH_END, 'moveBookmark')
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t('bookmarks', 'Failed to move bookmark')
			)
			throw err
		}
	},
	async [actions.CLICK_BOOKMARK]({ commit, dispatch, state }, bookmark) {
		commit(mutations.FETCH_START, { type: 'clickBookmark' })
		try {
			const response = await axios.post(url(state, '/bookmark/click'), {
				url: bookmark.url,
			})
			if (response.data.status !== 'success') {
				throw new Error(response.data)
			}
			commit(mutations.FETCH_END, 'clickBookmark')
		} catch (err) {
			console.error(err)
			commit(mutations.FETCH_END, 'clickBookmark')
			// Don't bother the user
		}
	},
	[actions.OPEN_BOOKMARK]({ commit }, id) {
		commit(mutations.SET_SIDEBAR, { type: 'bookmark', id })
	},
	async [actions.DELETE_BOOKMARK](
		{ commit, dispatch, state },
		{ id, folder, avoidReload }
	) {
		if (folder) {
			try {
				const response = await axios.delete(
					url(state, `/folder/${folder}/bookmarks/${id}`)
				)
				if (response.data.status !== 'success') {
					throw new Error(response.data)
				}
				commit(mutations.REMOVE_BOOKMARK, id)
				if (!avoidReload) {
					await dispatch(actions.COUNT_BOOKMARKS, -1)
					await dispatch(actions.LOAD_FOLDER_CHILDREN_ORDER, folder)
				}
			} catch (err) {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t(
						'bookmarks',
						'Failed to delete bookmark'
					)
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
			await dispatch(actions.COUNT_BOOKMARKS, -1)
			await commit(mutations.REMOVE_BOOKMARK, id)
		} catch (err) {
			console.error(err)
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t('bookmarks', 'Failed to delete bookmark')
			)
			throw err
		}
	},
	async [actions.IMPORT_BOOKMARKS](
		{ commit, dispatch, state },
		{ file, folder }
	) {
		commit(mutations.FETCH_START, { type: 'importBookmarks' })
		const data = new FormData()
		data.append('bm_import', file)
		try {
			const response = await axios.post(
				url(state, `/folder/${folder || -1}/import`),
				data
			)
			if (!response.data || response.data.status !== 'success') {
				if (response.status === 413) {
					throw new Error('Selected file is too large')
				}
				console.error('Failed to import bookmarks', response)
				throw new Error(
					Array.isArray(response.data.data)
						? response.data.data.join('. ')
						: response.data.data
				)
			}
			await dispatch(actions.COUNT_BOOKMARKS, -1)
			await dispatch(actions.LOAD_FOLDER_CHILDREN_ORDER, -1)
			await dispatch(actions.RELOAD_VIEW)
			commit(mutations.FETCH_END, 'importBookmarks')
			return commit(
				mutations.SET_NOTIFICATION,
				AppGlobal.methods.t('bookmarks', 'Import successful')
			)
		} catch (err) {
			console.error(err)
			commit(mutations.FETCH_END, 'importBookmarks')
			commit(mutations.SET_ERROR, err.message)
			throw err
		}
	},
	[actions.DELETE_BOOKMARKS]({ commit, dispatch, state }) {
		commit(mutations.FETCH_START, { type: 'deleteBookmarks' })
		return axios
			.delete(url(state, '/bookmark'))
			.then(response => {
				const {
					data: { status },
				} = response
				if (status !== 'success') {
					throw new Error(response.data)
				}
				dispatch(actions.COUNT_BOOKMARKS, -1)
				dispatch(actions.LOAD_FOLDER_CHILDREN_ORDER, -1)
				commit(mutations.FETCH_END, 'deleteBookmarks')
				return dispatch(actions.RELOAD_VIEW)
			})
			.catch(err => {
				console.error(err)
				commit(mutations.FETCH_END, 'deleteBookmarks')
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', err.message)
				)
				throw err
			})
	},

	async [actions.RENAME_TAG](
		{ commit, dispatch, state },
		{ oldName, newName }
	) {
		commit(mutations.FETCH_START, { type: 'tag' })
		try {
			const response = await axios.put(url(state, `/tag/${oldName}`), {
				name: newName,
			})
			const {
				data: { status },
			} = response
			if (status !== 'success') {
				throw new Error(response.data)
			}
			commit(mutations.RENAME_TAG, { oldName, newName })
			commit(mutations.FETCH_END, 'tag')
			return dispatch(actions.LOAD_TAGS)
		} catch (err) {
			console.error(err)
			commit(mutations.FETCH_END, 'tag')
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t('bookmarks', 'Failed to rename tag')
			)
			throw err
		}
	},
	[actions.LOAD_TAGS]({ commit, dispatch, state }) {
		commit(mutations.FETCH_START, { type: 'tags' })
		return axios
			.get(url(state, '/tag'), { params: { count: true } })
			.then(response => {
				const { data: tags } = response
				commit(mutations.FETCH_END, 'tags')
				return commit(mutations.SET_TAGS, tags)
			})
			.catch(err => {
				console.error(err)
				commit(mutations.FETCH_END, 'tags')
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t('bookmarks', 'Failed to load tags')
				)
				throw err
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
				commit(mutations.REMOVE_TAG, tag)
				dispatch(actions.LOAD_TAGS)
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t(
						'bookmarks',
						'Failed to delete bookmark'
					)
				)
				throw err
			})
	},

	async [actions.LOAD_FOLDERS]({ commit, dispatch, state }) {
		if (!state.folders.length) {
			try {
				const folders = loadState('bookmarks', 'folders')
				return commit(mutations.SET_FOLDERS, folders)
			} catch (e) {
				console.warn(
					'Could not load initial folder state, continuing with HTTP request'
				)
			}
		}
		let canceled = false
		commit(mutations.FETCH_START, {
			type: 'folders',
			cancel: () => {
				canceled = true
			},
		})
		try {
			const response = await axios.get(url(state, '/folder'), {
				params: {},
			})
			if (canceled) return
			const {
				data: { data, status },
			} = response
			if (status !== 'success') throw new Error(data)
			const folders = data
			commit(mutations.FETCH_END, 'folders')
			return commit(mutations.SET_FOLDERS, folders)
		} catch (err) {
			console.error(err)
			commit(mutations.FETCH_END, 'folders')
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t('bookmarks', 'Failed to load folders')
			)
			throw err
		}
	},
	async [actions.DELETE_FOLDER](
		{ commit, dispatch, state },
		{ id, avoidReload }
	) {
		try {
			const response = await axios.delete(url(state, `/folder/${id}`))
			const {
				data: { status },
			} = response
			if (status !== 'success') {
				throw new Error(response.data)
			}
			const parentFolder = this.getters.getFolder(id)[0].parent_folder
			if (!avoidReload) {
				await dispatch(
					actions.LOAD_FOLDER_CHILDREN_ORDER,
					parentFolder
				)
				await dispatch(actions.LOAD_FOLDERS)
			}
		} catch (err) {
			console.error(err)
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t('bookmarks', 'Failed to delete folder')
			)
			throw err
		}
	},
	async [actions.CREATE_FOLDER](
		{ commit, dispatch, state },
		{ parentFolder, title }
	) {
		try {
			const response = await axios.post(url(state, '/folder'), {
				parent_folder: parentFolder,
				title,
			})
			const {
				data: { status },
			} = response
			if (status !== 'success') {
				throw new Error(response.data)
			}
			commit(mutations.DISPLAY_NEW_FOLDER, false)
			await dispatch(
				actions.LOAD_FOLDER_CHILDREN_ORDER,
				parentFolder || -1
			)
			await dispatch(actions.LOAD_FOLDERS)
		} catch (err) {
			console.error(err)
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t('bookmarks', 'Failed to create folder')
			)
			throw err
		}
	},
	async [actions.SAVE_FOLDER]({ commit, dispatch, state }, id) {
		const folder = this.getters.getFolder(id)[0]
		commit(mutations.FETCH_START, { type: 'saveFolder' })
		try {
			const response = await axios.put(url(state, `/folder/${id}`), {
				parent_folder: folder.parent_folder,
				title: folder.title,
			})

			const {
				data: { status },
			} = response
			if (status !== 'success') {
				throw new Error(response.data)
			}
			await dispatch(
				actions.LOAD_FOLDER_CHILDREN_ORDER,
				folder.parent_folder
			)
			commit(mutations.FETCH_END, 'saveFolder')
		} catch (err) {
			console.error(err)
			commit(mutations.FETCH_END, 'saveFolder')
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t('bookmarks', 'Failed to create folder')
			)
			throw err
		}
	},
	async [actions.LOAD_FOLDER_CHILDREN_ORDER](
		{ commit, dispatch, state },
		id
	) {
		commit(mutations.FETCH_START, { type: 'childrenOrder' })
		try {
			const response = await axios.get(
				url(state, `/folder/${id}/childorder`)
			)
			const {
				data: { status },
			} = response
			if (status !== 'success') {
				throw new Error(response.data)
			}
			await commit(mutations.FETCH_END, 'childrenOrder')
			await commit(mutations.SET_FOLDER_CHILDREN_ORDER, {
				folderId: id,
				children: response.data.data,
			})
		} catch (err) {
			console.error(err)
			commit(mutations.FETCH_END, 'childrenOrder')
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t(
					'bookmarks',
					'Failed to load children order'
				)
			)
			throw err
		}
	},
	[actions.OPEN_FOLDER_DETAILS]({ commit }, id) {
		commit(mutations.SET_SIDEBAR, { type: 'folder', id })
	},

	async [actions.MOVE_SELECTION]({ commit, dispatch, state }, folderId) {
		commit(mutations.FETCH_START, { type: 'moveSelection' })
		try {
			await Parallel.each(
				state.selection.folders,
				async folder => {
					if (folderId === folder.id) {
						throw new Error('Cannot move folder into itself')
					}
					const oldParent = folder.parent_folder
					folder.parent_folder = folderId
					await dispatch(actions.SAVE_FOLDER, folder.id) // reloads children order for new parent
					await dispatch(
						actions.LOAD_FOLDER_CHILDREN_ORDER,
						oldParent
					)
				},
				10
			)
			await dispatch(actions.LOAD_FOLDERS)

			await Parallel.each(
				state.selection.bookmarks,
				bookmark =>
					dispatch(actions.MOVE_BOOKMARK, {
						oldFolder:
							bookmark.folders[bookmark.folders.length - 1], // FIXME This is veeeery ugly and will cause issues. Inevitably.
						newFolder: folderId,
						bookmark: bookmark.id,
					}),
				10
			)

			// Because we're possibly moving across share boundaries we need to recount
			await dispatch(actions.COUNT_BOOKMARKS, -1)

			commit(mutations.FETCH_END, 'moveSelection')
		} catch (err) {
			console.error(err)
			commit(mutations.FETCH_END, 'moveSelection')
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t(
					'bookmarks',
					'Failed to move parts of selection'
				)
			)
			throw err
		}
	},
	async [actions.DELETE_SELECTION]({ commit, dispatch, state }, { folder }) {
		commit(mutations.FETCH_START, { type: 'deleteSelection' })
		try {
			await Parallel.each(
				state.selection.folders,
				folder =>
					dispatch(actions.DELETE_FOLDER, {
						id: folder.id,
						avoidReload: true,
					}),
				10
			)
			await Parallel.each(
				state.selection.bookmarks,
				bookmark =>
					dispatch(actions.DELETE_BOOKMARK, {
						id: bookmark.id,
						folder,
						avoidReload: true,
					}),
				10
			)
			dispatch(actions.RELOAD_VIEW)
			commit(mutations.FETCH_END, 'deleteSelection')
		} catch (err) {
			console.error(err)
			commit(mutations.FETCH_END, 'deleteSelection')
			commit(
				mutations.SET_ERROR,
				AppGlobal.methods.t(
					'bookmarks',
					'Failed to delete parts of selection'
				)
			)
			throw err
		}
	},

	[actions.RELOAD_VIEW]({ state, dispatch, commit }) {
		commit(mutations.SET_QUERY, state.fetchState.query)
		dispatch(actions.FETCH_PAGE)
		dispatch(actions.LOAD_FOLDERS)
		dispatch(actions.LOAD_TAGS)
		dispatch(actions.COUNT_BOOKMARKS, -1)
		dispatch(actions.COUNT_UNAVAILABLE)
		dispatch(actions.COUNT_ARCHIVED)
	},

	[actions.NO_FILTER]({ dispatch, commit }) {
		commit(mutations.SET_QUERY, {})
		return dispatch(actions.FETCH_PAGE)
	},
	[actions.FILTER_BY_RECENT]({ dispatch, commit }, search) {
		commit(mutations.SET_QUERY, { sortby: 'added' })
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
	[actions.FILTER_BY_UNAVAILABLE]({ dispatch, commit }) {
		commit(mutations.SET_QUERY, { unavailable: true })
		return dispatch(actions.FETCH_PAGE)
	},
	[actions.FILTER_BY_ARCHIVED]({ dispatch, commit }) {
		commit(mutations.SET_QUERY, { archived: true })
		return dispatch(actions.FETCH_PAGE)
	},
	[actions.FILTER_BY_FOLDER]({ dispatch, commit }, folder) {
		commit(mutations.SET_QUERY, { folder })
		dispatch(actions.LOAD_FOLDER_CHILDREN_ORDER, folder)
		return dispatch(actions.FETCH_PAGE)
	},

	[actions.FETCH_PAGE]({ dispatch, commit, state }) {
		if (state.fetchState.reachedEnd) return
		if (state.loading.bookmarks) return
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
				commit(mutations.FETCH_END, 'bookmarks')
				return dispatch(actions.ADD_ALL_BOOKMARKS, bookmarks)
			})
			.catch(err => {
				console.error(err)
				commit(mutations.FETCH_END, 'bookmarks')
				commit(
					mutations.SET_ERROR,
					AppGlobal.t('bookmarks', 'Failed to fetch bookmarks.')
				)
				throw err
			})
	},
	async [actions.FETCH_ALL]({ dispatch, commit, state }) {
		if (state.fetchState.reachedEnd) return
		if (state.loading.bookmarks) return
		let canceled = false
		commit(mutations.FETCH_START, {
			type: 'bookmarks',
			cancel() {
				canceled = true
			},
		})
		try {
			const response = await axios.get(url(state, '/bookmark'), {
				params: {
					page: -1,
					sortby: state.settings.sorting,
					...state.fetchState.query,
				},
			})
			if (canceled) return
			const {
				data: { data, status },
			} = response
			if (status !== 'success') throw new Error(data)
			const bookmarks = data
			commit(mutations.REACHED_END)
			commit(mutations.FETCH_END, 'bookmarks')
			return dispatch(actions.ADD_ALL_BOOKMARKS, bookmarks)
		} catch (err) {
			console.error(err)
			commit(mutations.FETCH_END, 'bookmarks')
			commit(
				mutations.SET_ERROR,
				AppGlobal.t('bookmarks', 'Failed to fetch bookmarks.')
			)
			throw err
		}
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
				switch (key) {
				case 'viewMode':
					await commit(mutations.SET_VIEW_MODE, value)
					break
				case 'sorting':
					await commit(mutations.RESET_PAGE)
					break
				}
			})
			.catch(err => {
				console.error(err)
				commit(
					mutations.SET_ERROR,
					AppGlobal.methods.t(
						'bookmarks',
						'Failed to load setting {key}',
						{ key }
					)
				)
				throw err
			})
	},
	[actions.LOAD_SETTINGS]({ commit, dispatch, state }) {
		return Promise.all(
			['sorting', 'viewMode', 'archivePath', 'limit'].map(key =>
				dispatch(actions.LOAD_SETTING, key)
			)
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
	[actions.CREATE_SHARE](
		{ commit, dispatch, state },
		{ folderId, type, participant }
	) {
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
					AppGlobal.methods.t(
						'bookmarks',
						'Failed to create share for folder {folderId}',
						{ folderId }
					)
				)
				throw err
			})
	},
	[actions.EDIT_SHARE](
		{ commit, dispatch, state },
		{ shareId, canWrite, canShare }
	) {
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
					AppGlobal.methods.t(
						'bookmarks',
						'Failed to update share {shareId}',
						{ shareId }
					)
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
					AppGlobal.methods.t(
						'bookmarks',
						'Failed to delete share {shareId}',
						{ shareId }
					)
				)
				throw err
			})
	},

	[actions.LOAD_PUBLIC_LINK]({ commit, dispatch, state }, folderId) {
		return axios
			.get(url(state, `/folder/${folderId}/publictoken`), {
				validateStatus: status => status === 404 || status === 200,
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
				// Not sending a notification because we might just not have enough permissions to see this
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
					AppGlobal.methods.t(
						'bookmarks',
						'Failed to create public link for folder {folderId}',
						{ folderId }
					)
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
					AppGlobal.methods.t(
						'bookmarks',
						'Failed to delete public link for folder {folderId}',
						{ folderId }
					)
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
