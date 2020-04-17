<template>
	<div class="settings">
		<input type="file"
			class="import"
			size="5"
			@change="onImportSubmit">
		<button @click="onImportOpen">
			<span :class="{'icon-upload': !importing, 'icon-loading-small': importing}" />{{ t('bookmarks', 'Import') }}
		</button>
		<button @click="onExport">
			<span class="icon-download" /> {{ t('bookmarks', 'Export') }}
		</button>

		<label>{{ t('bookmarks', 'Sorting') }}
			<select :value="sorting" @change="onChangeSorting">
				<option id="added" value="added">
					{{ t('bookmarks', 'Recently added') }}
				</option>
				<option id="title" value="title">
					{{ t('bookmarks', 'Alphabetically') }}
				</option>
				<option id="clickcount" value="clickcount">
					{{ t('bookmarks', 'Most visited') }}
				</option>
				<option id="lastmodified" value="lastmodified">
					{{ t('bookmarks', 'Last modified') }}
				</option>
			</select></label>

		<label>{{ t('bookmarks', 'RSS Feed') }}
			<input
				v-tooltip="
					t('bookmarks',
						'This is an RSS feed of the current result set with access restricted to you.'
					)
				"
				type="text"
				readonly
				:value="rssURL"
				@click="onRssClick"></label>

		<label>{{ t('bookmarks', 'Clear data') }}
			<button
				v-tooltip="
					t('bookmarks',
						'Permanently remove all bookmarks from your account. There is no going back!'
					)
				"
				class="clear-data"
				@click="onClearData">
				<span class="icon-delete" />
				{{ t('bookmarks', 'Delete all bookmarks') }}
			</button>
		</label>

		<label>{{ t('bookmarks', 'Bookmarklet') }}
			<a
				v-tooltip="
					t('bookmarks',
						'Drag this to your browser bookmarks and click it to quickly bookmark a webpage'
					)
				"
				class="button"
				:href="bookmarklet"
				@click.prevent="void 0">{{
					t('bookmarks', 'Add to {instanceName}', {
						instanceName: oc_defaults.name
					})
				}}</a>
		</label>

		<p>
			{{
				t('bookmarks',
					'Also check out the collection of client apps that integrate with this app: '
				)
			}}
			<a href="https://github.com/nextcloud/bookmarks#third-party-clients">{{
				t('bookmarks', 'Client apps')
			}}</a>
		</p>
	</div>
</template>
<script>
import { generateUrl } from '@nextcloud/router'
import { actions } from '../store/'
import { getRequestToken } from '@nextcloud/auth'

export default {
	name: 'Settings',
	components: {},
	data() {
		return {
			importing: false,
		}
	},
	computed: {
		oc_defaults() {
			return window.oc_defaults
		},
		bookmarklet() {
			const bookmarkletUrl
						= window.location.origin + generateUrl('/apps/bookmarks/bookmarklet')
			return `javascript:(function(){var a=window,b=document,c=encodeURIComponent,e=c(document.title),d=a.open('${bookmarkletUrl}?url='+c(b.location)+'&title='+e,'bkmk_popup','left='+((a.screenX||a.screenLeft)+10)+',top='+((a.screenY||a.screenTop)+10)+',height=500px,width=550px,resizable=1,alwaysRaised=1');a.setTimeout(function(){d.focus()},300);})();`
		},
		rssURL() {
			return (
				window.location.origin
				+ generateUrl(
					'/apps/bookmarks/public/rest/v2/bookmark?'
						+ new URLSearchParams(
							Object.assign({}, this.$store.state.fetchState.query, {
								format: 'rss',
								page: -1,
							})
						).toString()
				)
			)
		},
		viewMode() {
			return this.$store.state.settings.viewMode
		},
		sorting() {
			return this.$store.state.settings.sorting
		},
	},
	methods: {
		onImportOpen(e) {
			e.target.previousElementSibling.click()
		},
		async onImportSubmit(e) {
			this.importing = true
			try {
				await this.$store.dispatch(actions.IMPORT_BOOKMARKS, e.target.files[0])
				this.$router.push({ name: this.routes.HOME })
			} finally {
				this.importing = false
			}
		},
		onExport() {
			window.location
				= 'bookmark/export?requesttoken='
					+ encodeURIComponent(getRequestToken())
		},
		async onChangeSorting(e) {
			await this.$store.dispatch(actions.SET_SETTING, {
				key: 'sorting',
				value: e.target.value,
			})
			await this.$store.dispatch(actions.FETCH_PAGE)
		},
		onChangeViewMode(e) {},
		onRssClick(e) {
			setTimeout(() => {
				e.target.select()
			}, 100)
		},
		async onClearData() {
			if (
				!confirm(
					t('bookmarks', 'Do you really want to delete all your bookmarks?')
				)
			) {
				return
			}
			await this.$store.dispatch(actions.DELETE_BOOKMARKS)
			await this.$router.push({ name: this.routes.HOME })
		},
	},
}
</script>
<style>
.import {
	opacity: 0;
	position: absolute;
	top: 0;
	left: -1000px;
}

.settings label,
.settings input,
.settings select,
.settings label button,
.settings label a.button {
	display: block;
}

.settings label {
	margin-top: 10px;
}

.settings a:link:not(.button) {
	text-decoration: underline;
}
</style>
