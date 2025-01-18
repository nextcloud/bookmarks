<!--
  - Copyright (c) 2021. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div v-if="isActive && scrapingEnabled && archiveEnabled && hasMinLength && !archivedFile && isWebLink" class="bookmark-content">
		<div v-if="bookmark.textContent" class="content" v-html="content" />
		<div v-else>
			<NcEmptyContent :name="t('bookmarks', 'Content pending')"
				:description="t('bookmarks', 'This content is being downloaded for offline use. Please check back later.')">
				<template #icon>
					<DownloadIcon :size="20" />
				</template>
			</NcEmptyContent>
		</div>
	</div>
</template>

<script>
import { DownloadIcon } from './Icons.js'
import sanitizeHtml from 'sanitize-html'
import { generateUrl, generateRemoteUrl } from '@nextcloud/router'
import { NcEmptyContent } from '@nextcloud/vue'

const MIN_TEXT_LENGTH = 350

export default {
	name: 'BookmarkContent',
	components: { NcEmptyContent, DownloadIcon },
	computed: {
		isActive() {
			if (!this.$store.state.sidebar) return false
			return this.$store.state.sidebar.type === 'bookmark'
		},
		isWebLink() {
			return this.bookmark.url.startsWith('http')
		},
		scrapingEnabled() {
			return this.$store.state.settings['privacy.enableScraping'] === 'true'
		},
		archiveEnabled() {
			return this.$store.state.settings['archive.enabled']
		},
		bookmark() {
			if (!this.isActive) return
			return this.$store.getters.getBookmark(this.$store.state.sidebar.id)
		},
		hasMinLength() {
			return !this.bookmark.textContent || this.bookmark.textContent.length >= MIN_TEXT_LENGTH
		},
		content() {
			return sanitizeHtml(this.bookmark.htmlContent, {
				allowProtocolRelative: false,
			})
		},
		archivedFileUrl() {
			// remove `/username/files/`
			const barePath = this.bookmark.archivedFilePath.split('/').slice(3).join('/')
			return generateRemoteUrl(`webdav/${barePath}`)
		},
		archivedFile() {
			if (this.bookmark.archivedFile) {
				return generateUrl(`/apps/files/?fileid=${this.bookmark.archivedFile}`)
			}
			return null
		},
	},
}
</script>

<style>
.bookmark-content {
	position: absolute;
	top: 0;
	left: 0;
	right: max( min(27vw, 500px), 300px); /* side bar */
	bottom: 0;
	background: var(--color-main-background);
	z-index: 1801;
	display: flex;
	overflow: scroll;
	flex-direction: column;
}

.bookmark-content .content {
	margin: 30px auto;
	width: 600px;
	font-size: 15px;
	text-align: justify;
	position: relative;
	flex-grow: 1;
}

.bookmark-content .content.iframe {
	margin: 0;
	position: relative;
	overflow: hidden;
	width: auto;
}

.bookmark-content .empty-content {
	margin: 150px auto;
	width: 600px;
}

.bookmark-content .content iframe {
	height: 100%;
	width: 100%;
}

.bookmark-content h1, .bookmark-content h2, .bookmark-content h3, .bookmark-content h4, .bookmark-content h5, .bookmark-content p {
	margin-top: 10px !important;
}

.bookmark-content a:link,
.bookmark-content a[href] {
	text-decoration: underline !important;
}
</style>
