/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

import Vue from 'vue'
import Vuex from 'vuex'
import Mutations from './mutations.js'
import Actions from './actions.js'
import { privateRoutes, publicRoutes } from '../router.js'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { findFolder } from './findFolder.js'

Vue.use(Vuex)

export { mutations } from './mutations.js'

export { actions } from './actions.js'

export default {
	mutations: Mutations,
	actions: Actions,
	state: {
		public: false,
		authToken: null,
		fetchState: {
			page: 0,
			query: {
				// Set home filter to avoid trying to load *all* bookmark initially until onRoute is called
				folder: -1,
			},
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
			moveSelection: false,
			copySelection: false,
		},
		error: null,
		notification: null,
		settings: {
			viewMode: 'list',
			sorting: 'lastmodified',
			limit: 0,
			backupPath: '',
			backupEnabled: '1',
			hasSeenWhatsnew: '',
		},
		bookmarks: [],
		bookmarksById: {},
		sharesById: {},
		sharedFoldersById: {},
		tags: null,
		folders: [],
		deletedFolders: null,
		childrenByFolder: {},
		tokensByFolder: {},
		countsByFolder: {},
		unavailableCount: null,
		archivedCount: null,
		duplicatedCount: null,
		allClicksCount: 0,
		withClicksCount: 0,
		selection: {
			folders: [],
			bookmarks: [],
		},
		displayNewBookmark: false,
		displayNewFolder: false,
		displayMoveDialog: false,
		displayCopyDialog: false,
		sidebar: null,
		viewMode: 'list',
	},

	getters: {
		getBookmark: state => id => {
			return state.bookmarksById[id]
		},
		getBookmarksForDashboard: state => () => state.bookmarks.map(bookmark => ({
			id: bookmark.id,
			targetUrl: bookmark.url,
			avatarUrl: generateUrl(`/apps/bookmarks/bookmark/${bookmark.id}/favicon`),
			mainText: bookmark.title,
			subText: bookmark.url,
		})),
		getFolder: state => id => {
			if (Number(id) === -1) {
				return [{ id: -1, children: state.folders }]
			}
			const folders = findFolder(id, state.folders)
			if (folders.length) {
				return folders
			}
			return findFolder(id, state.deletedFolders, true)
		},
		getFolderChildren: state => id => {
			return state.childrenByFolder[id] || []
		},
		getSharesOfFolder: state => folderId => {
			return Object.values(state.sharesById).filter(
				share => share.folderId === folderId,
			)
		},
		getTokenOfFolder: state => folderId => {
			return state.tokensByFolder[folderId]
		},
		getRoutes: state => () => {
			if (state.public) {
				return publicRoutes
			}
			return privateRoutes
		},
		getPermissionsForFolder: (state, getters) => folderId => {
			const path = getters.getFolder(folderId)
			const user = getCurrentUser()
			if (!path.length) {
				return {}
			}
			if (user && path[0].userId === user.uid) {
				return { canRead: true, canWrite: true, canShare: true }
			}
			for (let i = 0; i < path.length; i++) {
				const shares = getters.getSharesOfFolder(path[i].id)
				if (shares.length) {
					const userShares = shares.filter(share => share.type === 0 && share.participant === user.uid)
					if (userShares.length) {
						return userShares[0]
					}
					const groupShares = shares.filter(share => share.type === 1)
					return groupShares[0]
				}
			}
			return {}
		},
		getPermissionsForBookmark: (state, getters) => bookmarkId => {
			const bookmark = getters.getBookmark(bookmarkId)
			if (!bookmark) {
				return {}
			}
			return bookmark.folders.reduce((perms, folder) => ({ ...perms, ...getters.getPermissionsForFolder(folder) }), {})
		},
	},
}
