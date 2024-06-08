<!--
  - Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<NcAppSettingsDialog :open="settingsOpen"
		:show-navigation="true"
		:name="t('bookmarks', 'Bookmarks settings')"
		class="settings"
		@update:open="$emit('update:settingsOpen', $event)">
		<NcAppSettingsSection id="importexport" :name="t('bookmarks', 'Import/Export')">
			<template #icon>
				<ImportIcon :size="20" />
			</template>
			<input type="file"
				class="import"
				size="5"
				@change="onImportSubmit">
			<button @click="onImportOpen">
				<span :class="{'icon-upload': !importing, 'icon-loading-small': importing}" />{{ t('bookmarks', 'Import bookmarks') }}
			</button>
			<button @click="onExport">
				<span class="icon-download" /> {{ t('bookmarks', 'Export bookmarks') }}
			</button>
		</NcAppSettingsSection>

		<NcAppSettingsSection v-if="scrapingEnabled" id="archive" :name="t('bookmarks', 'Auto-archiving')">
			<template #icon>
				<ArchiveIcon :size="20" />
			</template>
			<p>{{ t('bookmarks', 'The bookmarks app can automatically archive the web content of links you have bookmarked') }}</p>
			<NcCheckboxRadioSwitch :checked="archiveEnabled" @update:checked="onChangeArchiveEnabled">
				{{ t('bookmarks', 'Enable archiving') }}
			</NcCheckboxRadioSwitch>
			<NcTextField v-if="archiveEnabled"
				:label="t('bookmarks', 'Enter the path of a folder in your Files where bookmarked files should be stored.')"
				:value="archivePath"
				:readonly="true"
				@click="onChangeArchivePath" />
		</NcAppSettingsSection>

		<NcAppSettingsSection id="backup" :name="t('bookmarks', 'Auto-Backup')">
			<template #icon>
				<BackupIcon :size="20" />
			</template>
			<p>{{ t('bookmarks', 'The bookmarks app can automatically backup your bookmarks on a daily basis to prevent data loss when syncing bookmarks across devices.') }}</p>
			<NcCheckboxRadioSwitch :checked="backupEnabled" @update:checked="onChangeBackupEnabled">
				{{ t('bookmarks', 'Enable backups') }}
			</NcCheckboxRadioSwitch>
			<NcTextField v-if="backupEnabled"
				:label="t('bookmarks', 'Enter the path of a folder in your Files where backups will be stored.')"
				:value="backupPath"
				:readonly="true"
				@click="onChangeBackupPath" />
		</NcAppSettingsSection>

		<NcAppSettingsSection id="client-apps" :name="t('bookmarks', 'Client apps')">
			<template #icon>
				<ApplicationIcon :size="20" />
			</template>
			<p>
				{{
					t('bookmarks',
						'Also check out the collection of client apps that integrate with this app: '
					)
				}}
				<a href="https://github.com/nextcloud/bookmarks#third-party-clients" style="text-decoration: underline;">{{
					t('bookmarks', 'Client apps')
				}}</a>
			</p>
		</NcAppSettingsSection>

		<NcAppSettingsSection id="install" :name="t('bookmarks', 'Install web app')">
			<template #icon>
				<ApplicationImportIcon :size="20" />
			</template>
			<p>{{ t('bookmarks', 'You can install this app on your device home screen to quickly access your bookmarks on your phone. You can easily remove the app from your home screen again, if you don\'t like it.') }}</p>
			<a class="button center" href="#" @click.prevent="clickAddToHomeScreen">{{ t('bookmarks', 'Install on home screen') }}</a>
		</NcAppSettingsSection>

		<NcAppSettingsSection id="bookmarklet" :name="t('bookmarks', 'Bookmarklet')">
			<template #icon>
				<LinkIcon :size="20" />
			</template>
			<p>
				{{ t('bookmarks',
					'Drag this to your browser bookmarks and click it to quickly bookmark a webpage.'
				) }}
			</p>
			<a class="button center"
				:href="bookmarklet"
				@click.prevent="void 0">{{
					t('bookmarks', 'Add to {instanceName}', {
						instanceName: oc_defaults.name
					})
				}}</a>
		</NcAppSettingsSection>

		<NcAppSettingsSection id="need-help" :name="t('bookmarks', 'Need help?')">
			<template #icon>
				<LifebuoyIcon :size="20" />
			</template>
			<p><a href="https://github.com/nextcloud/bookmarks/issues/">{{ t('bookmarks', 'If you have problems with this Bookmarks app or have an idea about what could be improved, don\'t hesitate to get in touch by clicking here.') }}</a></p>
		</NcAppSettingsSection>

		<NcAppSettingsSection id="support-project" :name="t('bookmarks', 'Support this project')">
			<template #icon>
				<HeartIcon :size="20" />
			</template>
			<p>{{ t('bookmarks', 'My work on this Bookmarks app is fuelled by a voluntary subscription model. If you think what I do is worthwhile, I would be happy if you could support my work. Also, please consider giving the app a review on the Nextcloud app store. Thank you 💙 ') }}</p>
			<p>&nbsp;</p>
			<p><a href="https://github.com/sponsors/marcelklehr">GitHub Sponsors</a>, <a href="https://www.patreon.com/marcelklehr">Patreon</a>, <a href="https://liberapay.com/marcelklehr/donate">Liberapay</a>, <a href="https://ko-fi.com/marcelklehr">Ko-Fi</a>, <a href="https://www.paypal.me/marcelklehr1">PayPal</a></p>
		</NcAppSettingsSection>
	</NcAppSettingsDialog>
</template>
<script>
import { generateUrl } from '@nextcloud/router'
import { actions } from '../store/index.js'
import { getRequestToken } from '@nextcloud/auth'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { privateRoutes } from '../router.js'
import { NcAppSettingsSection, NcAppSettingsDialog, NcCheckboxRadioSwitch, NcTextField } from '@nextcloud/vue'
import { ImportIcon, ArchiveIcon, BackupIcon, LinkIcon, ApplicationIcon, ApplicationImportIcon } from './Icons.js'
import HeartIcon from 'vue-material-design-icons/Heart.vue'
import LifebuoyIcon from 'vue-material-design-icons/Lifebuoy.vue'

export default {
	name: 'Settings',
	components: { LifebuoyIcon, HeartIcon, NcAppSettingsSection, NcAppSettingsDialog, NcCheckboxRadioSwitch, NcTextField, ImportIcon, ArchiveIcon, BackupIcon, LinkIcon, ApplicationIcon, ApplicationImportIcon },
	props: {
		settingsOpen: {
			type: Boolean,
			required: true,
		},
	},
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
</style>
