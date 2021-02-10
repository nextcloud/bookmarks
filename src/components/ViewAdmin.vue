<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div id="bookmarks">
		<figure v-if="loading" class="icon-loading loading" />
		<figure v-if="!loading && success" class="icon-checkmark success" />
		<SettingsSection
			:title="t('bookmarks', 'Privacy')"
			:description="t('bookmarks',
				'Bookmarks will try to access web pages that you add to automatically add information about them.'
			)">
			<p>
				<input
					id="enableScraping"
					v-model="settings['privacy.enableScraping']"
					type="checkbox"
					class="checkbox"
					@input="onChange">
				<label for="enableScraping">{{
					t('bookmarks',
						'Enable accessing and collecting information from the web pages you add'
					)
				}}</label>
			</p>
		</SettingsSection>
		<SettingsSection
			:title="t('bookmarks', 'Performance') "
			:description="t('bookmarks',
				'In an installation with a lot of users it may be useful to restrict the number of bookmarks per account.'
			)">
			<p>
				<label for="maxBookmarksperAccount">{{
						t('bookmarks',
							'Maximum allowed number of bookmarks per account. (0 for no limit; default is no limit)'
						)
					}}
					<input id="maxBookmarksperAccount"
						v-model.number="settings['performance.maxBookmarksperAccount']"
						type="number"
						min="0"
						placeholder="0"
						step="1"
						@input="onChange"></label>
			</p>
		</SettingsSection>
		<SettingsSection
			:title="t('bookmarks', 'Previews')"
			:description="t('bookmarks',
				'In order to display real screenshots of your bookmarked websites, Bookmarks can use third-party services to generate previews.'
			)">
			<h3>{{ t('bookmarks', 'Screeenly') }}</h3>
			<p>
				{{
					t('bookmarks',
						'You can either sign up for free at screeenly.com or setup your own server.'
					)
				}}
			</p>
			<p>
				<label>{{ t('bookmarks', 'Screeenly API URL') }}
					<input
						v-model="settings['previews.screenly.url']"
						type="text"
						placeholder="https://screeenly.example.com/api/v1/fullsize"
						@input="onChange"></label>
			</p>
			<p>
				<label>{{ t('bookmarks', 'Screeenly API key') }}
					<input
						v-model="settings['previews.screenly.token']"
						type="text"
						@input="onChange"></label>
			</p>
			<h3>{{ t('bookmarks', 'Webshot') }}</h3>
			<p>
				<label>{{ t('bookmarks', 'Webshot API URL') }}
					<input
						v-model="settings['previews.webshot.url']"
						type="text"
						@input="onChange"></label>
			</p>
			<h3>{{ t('bookmarks', 'ScreenshotMachine') }}</h3>
			<p>
				<label>{{ t('bookmarks', 'ScreenshotMachine API key') }}
					<input
						v-model="settings['previews.screenshotmachine.key']"
						type="text"
						@input="onChange"></label>
			</p>
			<h3>{{ t('bookmarks', 'Pageres CLI') }}</h3>
			<p>
				<a href="https://github.com/sindresorhus/pageres-cli" target="_blank">{{
					t('bookmarks', 'Simply install the pageres cli on your server and bookmarks will find it.')
				}}</a>
			</p>
		</SettingsSection>
	</div>
</template>

<script>
import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'

const SETTINGS = [
	'previews.screenly.url',
	'previews.screenly.token',
	'previews.webshot.url',
	'previews.screenshotmachine.key',
	'privacy.enableScraping',
	'performance.maxBookmarksperAccount',
]

export default {
	name: 'ViewAdmin',
	components: { SettingsSection },
	data() {
		return {
			settings: SETTINGS.reduce((obj, key) => ({ ...obj, [key]: '' }), {}),
			loading: false,
			success: false,
			error: '',
			timeout: null,
		}
	},

	watch: {
		settings: 'submit',
		error(error) {
			if (!error) return
			OC.Notification.showTemporary(error)
		},
	},

	async created() {
		try {
			for (const setting of SETTINGS) {
				this.settings[setting] = await this.getValue(setting)
				if (['true', 'false'].includes(this.settings[setting])) {
					this.settings[setting] = (this.settings[setting] === 'true')
				}
			}
		} catch (e) {
			this.error = this.t('bookmarks', 'Failed to load settings')
			throw e
		}
	},

	methods: {
		onChange() {
			if (this.timeout) {
				clearTimeout(this.timeout)
			}
			setTimeout(() => {
				this.submit()
			}, 1000)
		},

		async submit() {
			this.loading = true
			for (const setting in this.settings) {
				this.setValue(setting, this.settings[setting])
			}
			this.loading = false
			this.success = true
			setTimeout(() => {
				this.success = false
			}, 3000)
		},

		async setValue(setting, value) {
			try {
				await new Promise((resolve, reject) =>
					OCP.AppConfig.setValue('bookmarks', setting, value, {
						success: resolve,
						error: reject,
					})
				)
			} catch (e) {
				this.error = this.t('bookmarks', 'Failed to save settings')
				throw e
			}
		},

		async getValue(setting) {
			try {
				const resDocument = await new Promise((resolve, reject) =>
					OCP.AppConfig.getValue('bookmarks', setting, null, {
						success: resolve,
						error: reject,
					})
				)
				if (resDocument.querySelector('status').textContent !== 'ok') {
					this.error = this.t('bookmarks', 'Failed to load settings')
					console.error('Failed request', resDocument)
					return
				}
				const dataEl = resDocument.querySelector('data')
				return dataEl.firstElementChild.textContent
			} catch (e) {
				this.error = this.t('bookmarks', 'Failed to load settings')
				throw e
			}
		},
	},
}
</script>
<style>
figure[class^='icon-'] {
	display: inline-block;
}

#bookmarks {
	position: relative;
}

#bookmarks .loading,
#bookmarks .success {
	position: absolute;
	top: 20px;
	right: 20px;
}

#bookmarks label {
	margin-top: 10px;
	display: block;
}

#bookmarks input {
	width: 50%;
	min-width: 300px;
	display: block;
}
</style>
