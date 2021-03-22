<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

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

		<label><h3>{{ t('bookmarks', 'Archive path') }}</h3>
			<p>{{ t('bookmarks',
				'Enter the path of a folder where bookmarked files should be stored'
			) }}</p>
			<input
				:value="archivePath"
				:readonly="true"
				@click="onChangeArchivePath">
		</label>

		<h3>{{ t('bookmarks', 'Client apps') }}</h3>
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

		<label>
			<h3>{{ t('bookmarks', 'Install web app') }}</h3>
			<a class="button" href="#" @click.prevent="clickAddToHomeScreen">{{ t('bookmarks', 'Install on home screen') }}</a>
		</label>

		<label><h3>{{ t('bookmarks', 'Bookmarklet') }}</h3>
			<p>{{ t('bookmarks',
				'Drag this to your browser bookmarks and click it to quickly bookmark a webpage'
			) }}</p>
			<a
				class="button"
				:href="bookmarklet"
				@click.prevent="void 0">{{
					t('bookmarks', 'Add to {instanceName}', {
						instanceName: oc_defaults.name
					})
				}}</a>
		</label>

		<label><h3>{{ t('bookmarks', 'Clear data') }}</h3>
			<p>{{
				t('bookmarks',
					'Permanently remove all bookmarks from your account.'
				)
			}}</p>
			<button
				class="clear-data"
				@click="onClearData">
				<span :class="{'icon-delete': !deleting, 'icon-loading-small': deleting}" />
				{{ t('bookmarks', 'Delete all bookmarks') }}
			</button>
		</label>
	</div>
</template>
<script>
import { generateUrl } from '@nextcloud/router'
import { actions } from '../store/'
import { getRequestToken } from '@nextcloud/auth'
import { getFilePickerBuilder } from '@nextcloud/dialogs'

export default {
	name: 'Settings',
	components: {},
	data() {
		return {
			importing: false,
			deleting: false,
			addToHomeScreen: null,
			filePicker: getFilePickerBuilder(this.t('bookmarks', 'Archive path'))
				.allowDirectories(true)
				.setModal(true)
				.setType(1)// CHOOSE
				.setMultiSelect(false)
				.build(),
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
		archivePath() {
			return this.$store.state.settings.archivePath
		},
	},
	mounted() {
		window.addEventListener('beforeinstallprompt', (e) => {
			// Prevent Chrome 67 and earlier from automatically showing the prompt
			e.preventDefault()
			// Stash the event so it can be triggered later.
			this.addToHomeScreen = e
			this.showAddToHomeScreen = true
		})
	},
	methods: {
		onImportOpen(e) {
			e.target.previousElementSibling.click()
		},
		async onImportSubmit(e) {
			this.importing = true
			try {
				await this.$store.dispatch(actions.IMPORT_BOOKMARKS, { file: e.target.files[0], folder: this.$route.params.folder || -1 })
			} catch (e) {
				console.warn(e)
			}
			this.importing = false
		},
		onExport() {
			window.location
				= 'bookmark/export?requesttoken='
					+ encodeURIComponent(getRequestToken())
		},
		async onChangeArchivePath(e) {
			const path = await this.filePicker.pick()
			await this.$store.dispatch(actions.SET_SETTING, {
				key: 'archivePath',
				value: path,
			})
		},
		async onClearData() {
			if (
				!confirm(
					t('bookmarks', 'Do you really want to delete all your bookmarks?')
				)
			) {
				return
			}
			this.deleting = true
			await this.$store.dispatch(actions.DELETE_BOOKMARKS)
			await this.$router.push({ name: this.routes.HOME })
			this.deleting = false
		},
		clickAddToHomeScreen() {
			if (!this.addToHomeScreen) {
				alert(this.t('bookmarks', 'Please select "Add to home screen" in your browser menu'))
				return
			}
			// Show the prompt
			this.addToHomeScreen.prompt()
			// Wait for the user to respond to the prompt
			this.addToHomeScreen.userChoice.then((choiceResult) => {
				if (choiceResult.outcome === 'accepted') {
					console.warn('User accepted the A2HS prompt')
				} else {
					console.warn('User dismissed the A2HS prompt')
				}
				this.addToHomeScreen = null
			})
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
.settings button,
.settings label a.button {
	display: block;
	width: 100%;
}

.settings label {
	margin-top: 10px;
}

.settings a:link:not(.button) {
	text-decoration: underline;
}
</style>
