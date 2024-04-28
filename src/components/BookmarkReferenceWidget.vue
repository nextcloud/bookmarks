<!--
- @author 2022 Julien Veyssier <julien-nc@posteo.net>
- @author 2022 Marcel Klehr <mklehr@gmx.net>
-
- @license GNU AGPL version 3 or any later version
-
- This program is free software: you can redistribute it and/or modify
- it under the terms of the GNU Affero General Public License as
- published by the Free Software Foundation, either version 3 of the
- License, or (at your option) any later version.
-
- This program is distributed in the hope that it will be useful,
- but WITHOUT ANY WARRANTY; without even the implied warranty of
- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
- GNU Affero General Public License for more details.
-
- You should have received a copy of the GNU Affero General Public License
- along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<div class="bookmarks-bookmark-reference">
		<div class="line">
			<a :href="bookmarkLink" target="_blank" class="link">
				<BookmarksIcon :size="14" class="title-icon" />
				<small>{{ addedDate }}</small>
			</a>
		</div>
		<div class="line title">
			<strong>
				<a :href="url"
					target="_blank"
					class="link">
					<figure class="icon" :style="{ backgroundImage: 'url(' + iconUrl + ')' }" />
					{{ bookmark.title }}
				</a>
			</strong>
		</div>
		<div class="line">
			<small>{{ content }}</small>
		</div>
	</div>
</template>

<script>
import { BookmarksIcon } from './Icons.js'
import { generateUrl } from '@nextcloud/router'
import humanizeDuration from 'humanize-duration'

const MAX_RELATIVE_DATE = 1000 * 60 * 60 * 24 * 7 // one week

export default {
	name: 'BookmarkReferenceWidget',
	components: {
		BookmarksIcon,
	},
	props: {
		richObjectType: {
			type: String,
			default: '',
		},
		richObject: {
			type: Object,
			default: null,
		},
		accessible: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			shortDescription: true,
		}
	},
	computed: {
		bookmark() {
			return this.richObject.bookmark
		},
		bookmarkLink() {
			return generateUrl('/apps/bookmarks/bookmarks/{bookmarkId}', { bookmarkId: this.bookmark.id })
		},
		apiUrl() {
			return generateUrl('/apps/bookmarks')
		},
		iconUrl() {
			return (
				this.apiUrl
					+ '/bookmark/'
					+ this.bookmark.id
					+ '/favicon'
			)
		},
		imageUrl() {
			return (
				this.apiUrl
					+ '/bookmark/'
					+ this.bookmark.id
					+ '/image'
			)
		},
		url() {
			return this.bookmark.url
		},
		addedDate() {
			const date = new Date(Number(this.bookmark.added) * 1000)
			const age = Date.now() - date
			if (age < MAX_RELATIVE_DATE) {
				const duration = humanizeDuration(age, {
					language: OC.getLanguage().split('-')[0],
					units: ['d', 'h', 'm', 's'],
					largest: 1,
					round: true,
				})
				return this.t('bookmarks', 'Bookmarked {time} ago', { time: duration })
			} else {
				return this.t('bookmarks', 'Bookmarked on {date}', { date: date.toLocaleDateString() })
			}
		},
		content() {
			const length = 250
			return this.bookmark?.textContent?.trim()?.slice(0, length) || this.bookmark.description?.trim()?.slice(0, length)
		},
	},
	methods: {
	},
}
</script>

<style scoped>
.bookmarks-bookmark-reference {
	width: 100%;
	white-space: normal;
	padding: 12px;
}

.bookmarks-bookmark-reference .editor__content {
	width: calc(100% - 24px);
}

.bookmarks-bookmark-reference .link {
	text-decoration: underline;
	color: var(--color-main-text) !important;
	padding: 0 !important;
	display: flex;
}

.bookmarks-bookmark-reference .line {
	display: flex;
	align-items: center;
}

.bookmarks-bookmark-reference .title {
	font-size: 2em;
	margin-top: 10px;
	margin-bottom: 5px;
	height: 31px;
	overflow-y: hidden;
	line-height: 1;
	align-items: flex-start;
}

.bookmarks-bookmark-reference .spacer {
	flex-grow: 1;
}

.bookmarks-bookmark-reference .icon {
	display: inline-block;
	height: 25px;
	width: 25px;
	background-size: cover;
	margin-right: 5px;
}
</style>
