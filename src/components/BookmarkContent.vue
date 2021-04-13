<!--
  - Copyright (c) 2021. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div v-if="isActive && hasMinLength" class="bookmark-content">
		<template v-if="archivedFile">
			<div class="content iframe">
				<iframe :src="archivedFileUrl" />
			</div>
		</template>
		<div v-else-if="bookmark.textContent" class="content" v-html="content" />
		<div v-else>
			<EmptyContent icon="icon-download">
				{{ t('bookmarks', 'Content pending') }}
				<template #desc>
					{{ t('bookmarks', ' This content is being downloaded for offline use. Please check back later.') }}
				</template>
			</EmptyContent>
		</div>
	</div>
</template>

<script>
import sanitizeHtml from 'sanitize-html'
import { generateUrl, generateRemoteUrl } from '@nextcloud/router'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'

const MIN_TEXT_LENGTH = 350

export default {
	name: 'BookmarkContent',
	components: { EmptyContent },
	computed: {
		isActive() {
			if (!this.$store.state.sidebar) return false
			return this.$store.state.sidebar.type === 'bookmark'
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
	top: 50px;/* nc header bar */
	left: 0;
	right: max( min(27vw, 500px), 300px); /* side bar */
	bottom: 0;
	background: var(--color-main-background);
	z-index: 3000;
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
