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

		<template v-if="scrapingEnabled">
			<label><h3>{{ t('bookmarks', 'Archive path') }}</h3>
				<p><label><input type="checkbox" :checked="archiveEnabled" @input="onChangeArchiveEnabled">{{ t('bookmarks', 'Enable bookmarks archiving to store the web contents of the links that you have bookmarked') }}</label></p>
				<p>{{ t('bookmarks',
					'Enter the path of a folder in your Files where bookmarked files should be stored.'
				) }}</p>
				<input :value="archivePath"
					:readonly="true"
					@click="onChangeArchivePath">
			</label>
		</template>

		<label><h3>{{ t('bookmarks', 'Backups') }}</h3>
			<p><label><input type="checkbox" :checked="backupEnabled" @input="onChangeBackupEnabled">{{ t('bookmarks', 'Enable bookmarks backups') }}</label></p>
			<p>{{ t('bookmarks',
				'Enter the path of a folder in your Files where backups will be stored.'
			) }}</p>
			<input :value="backupPath"
				:readonly="true"
				@click="onChangeBackupPath">
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
			<h3>{{ t('bookmarks', 'Install web app on device') }}</h3>
			<p>{{ t('bookmarks', 'You can install this app on your device home screen to quickly access your bookmarks on your phone. You can easily remove the app from your home screen again, if you don\'t like it.') }}</p>
			<a class="button center" href="#" @click.prevent="clickAddToHomeScreen">{{ t('bookmarks', 'Install on home screen') }}</a>
		</label>

		<label><h3>{{ t('bookmarks', 'Bookmarklet') }}</h3>
			<p>{{ t('bookmarks',
				'Drag this to your browser bookmarks and click it to quickly bookmark a webpage.'
			) }}</p>
			<a class="button center"
				:href="bookmarklet"
				@click.prevent="void 0">{{
					t('bookmarks', 'Add to {instanceName}', {
						instanceName: oc_defaults.name
					})
				}}</a>
		</label>
	</div>
</template>
<script>
import { generateUrl } from '@nextcloud/router'
import { actions } from '../store/index.js'
import { getRequestToken } from '@nextcloud/auth'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { privateRoutes } from '../router.js'

export default {
	name: 'Settings',
	components: {},
	data() {
		return {
			importing: false,
			deleting: false,
			addToHomeScreen: null,
			archivePathPicker: getFilePickerBuilder(this.t('bookmarks', 'Archive path'))
				.allowDirectories(true)
				.setModal(true)
				.setType(1)// CHOOSE
				.setMultiSelect(false)
				.build(),
			backupPathPicker: getFilePickerBuilder(this.t('bookmarks', 'Backup path'))
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
			let queryStringExtension = ''
			if (this.$route.name === privateRoutes.FOLDER) {
				queryStringExtension = `+'&folderId=${this.$route.params.folder}'`
			}
			return `javascript:(function(){var a=window,b=document,c=encodeURIComponent,e=c(document.title),d=a.open('${bookmarkletUrl}?url='+c(b.location)+'&title='+e${queryStringExtension},'bkmk_popup','left='+((a.screenX||a.screenLeft)+10)+',top='+((a.screenY||a.screenTop)+10)+',height=650px,width=550px,resizable=1,alwaysRaised=1');a.setTimeout(function(){d.focus()},300);})();`
		},
		scrapingEnabled() {
			return Boolean(this.$store.state.settings['privacy.enableScraping'])
		},
		archiveEnabled() {
			return Boolean(this.$store.state.settings['archive.enabled'])
		},
		archivePath() {
			return this.$store.state.settings['archive.filePath']
		},
		backupPath() {
			return this.$store.state.settings['backup.filePath']
		},
		backupEnabled() {
			return Boolean(this.$store.state.settings['backup.enabled'])
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
		async onChangeArchiveEnabled(e) {
			await this.$store.dispatch(actions.SET_SETTING, {
				key: 'archive.enabled',
				value: !this.archiveEnabled,
			})
		},
		async onChangeArchivePath(e) {
			const path = await this.archivePathPicker.pick()
			await this.$store.dispatch(actions.SET_SETTING, {
				key: 'archive.filePath',
				value: path,
			})
		},
		async onChangeBackupPath(e) {
			if (!this.backupEnabled) {
				return
			}
			const path = await this.backupPathPicker.pick()
			await this.$store.dispatch(actions.SET_SETTING, {
				key: 'backup.filePath',
				value: path,
			})
		},
		async onChangeBackupEnabled(e) {
			await this.$store.dispatch(actions.SET_SETTING, {
				key: 'backup.enabled',
				value: !this.backupEnabled,
			})
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

.settings input[type=checkbox] {
	display: inline-block;
	position: relative;
	top: 0.5em;
	width: 1.2em;
}

.settings label {
	margin-top: 10px;
}

.settings h3 {
	font-weight: bold;
}

.settings a:link:not(.button) {
	text-decoration: underline;
}
</style>
