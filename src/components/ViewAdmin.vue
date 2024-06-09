<!--
  - Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div id="bookmarks">
		<figure v-if="loading" class="icon-loading loading" />
		<figure v-if="!loading && success" class="icon-checkmark success" />
		<NcSettingsSection :name="t('bookmarks', 'Privacy')"
			:description="t('bookmarks',
				'The app will try to access web pages that you add to automatically add information about them.'
			)">
			<p>
				<input id="enableScraping"
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
		</NcSettingsSection>
		<NcSettingsSection :name="t('bookmarks', 'Performance') "
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
		</NcSettingsSection>
		<NcSettingsSection :name="t('bookmarks', 'Previews')"
			:description="t('bookmarks',
				'In order to display real screenshots of your bookmarked websites, the app can use third-party services to generate previews.'
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
					<input v-model="settings['previews.screenly.url']"
						type="text"
						placeholder="https://screeenly.example.com/api/v1/fullsize"
						@input="onChange"></label>
			</p>
			<p>
				<label>{{ t('bookmarks', 'Screeenly API key') }}
					<input v-model="settings['previews.screenly.token']"
						type="text"
						@input="onChange"></label>
			</p>
			<figure class="test-screenshot">
				<img :src="generateUrl(`/apps/bookmarks/admin/previewers/screeenly?date=${testDate}`)" alt="Screeenly test screenshot">
				<figcaption>Test screenshot of nextcloud.com with current Screeenly configuration.</figcaption>
			</figure>
			<h3>{{ t('bookmarks', 'ScreenshotMachine') }}</h3>
			<p>
				<label>{{ t('bookmarks', 'ScreenshotMachine API key') }}
					<input v-model="settings['previews.screenshotmachine.key']"
						type="text"
						@input="onChange"></label>
			</p>
			<figure class="test-screenshot">
				<img :src="generateUrl(`/apps/bookmarks/admin/previewers/screenshotmachine?date=${testDate}`)" alt="ScreenshotMachine test screenshot">
				<figcaption>Test screenshot of nextcloud.com with current ScreenshotMachine configuration.</figcaption>
			</figure>
			<h3>{{ t('bookmarks', 'Webshot') }}</h3>
			<p>
				<label>{{ t('bookmarks', 'Webshot API URL') }}
					<input v-model="settings['previews.webshot.url']"
						type="text"
						@input="onChange"></label>
			</p>
			<figure class="test-screenshot">
				<img :src="generateUrl(`/apps/bookmarks/admin/previewers/webshot?date=${testDate}`)" alt="Webshot test screenshot">
				<figcaption>Test screenshot of nextcloud.com with current Webshot configuration.</figcaption>
			</figure>
			<h3>{{ t('bookmarks', 'Generic screenshot API') }}</h3>
			<p>
				{{
					t('bookmarks', 'Sign up with any of the screenshot API providers and put the API URL below. Use the variable {url} as a placeholder for the URL to screenshot. The API must return only the image directly.')
				}}
			</p>
			<p>
				<label>{{ t('bookmarks', 'Generic API URL') }}
					<input v-model="settings['previews.generic.url']"
						type="text"
						@input="onChange"></label>
			</p>
			<figure class="test-screenshot">
				<img :src="generateUrl(`/apps/bookmarks/admin/previewers/url?date=${testDate}`)" alt="Generic API test screenshot">
				<figcaption>Test screenshot of nextcloud.com with current generic API configuration.</figcaption>
			</figure>
			<h3>{{ t('bookmarks', 'Pageres CLI') }}</h3>
			<p>
				<a href="https://github.com/sindresorhus/pageres-cli" target="_blank">{{
					t('bookmarks', 'Simply install the Pageres CLI by Sindre Sorhus on your server and Bookmarks will find it. You can still add additional ENV vars to be fed to pageres, e.g. as indicated in the placeholder:')
				}}</a>
			</p>
			<p>
				<label>{{ t('bookmarks', 'Pageres ENV variables') }}
					<input v-model="settings['previews.pageres.env']"
						placeholder="CHROMIUM_PATH=/usr/bin/chromium-browser PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=false"
						type="text"
						@input="onChange"></label>
			</p>
			<figure class="test-screenshot">
				<img :src="generateUrl(`/apps/bookmarks/admin/previewers/pageres?date=${testDate}`)" alt="Pageres test screenshot">
				<figcaption>Test screenshot of nextcloud.com with current Pageres CLI configuration.</figcaption>
			</figure>
		</NcSettingsSection>
	</div>
</template>

<script>
import { NcSettingsSection } from '@nextcloud/vue'
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'

const SETTINGS = [
	'previews.screenly.url',
	'previews.screenly.token',
	'previews.webshot.url',
	'previews.screenshotmachine.key',
	'previews.generic.url',
	'previews.pageres.env',
	'privacy.enableScraping',
	'performance.maxBookmarksperAccount',
]

const BOOLEAN_SETTINGS = ['privacy.enableScraping']

export default {
	name: 'ViewAdmin',
	components: { NcSettingsSection },
	data() {
		const settings = loadState('bookmarks', 'adminSettings')
		for (const setting of SETTINGS) {
			if (['true', 'false'].includes(settings[setting])) {
				settings[setting] = (settings[setting] === 'true')
			}
		}
		return {
			settings,
			loading: false,
			success: false,
			error: '',
			timeout: null,
			testDate: Date.now(),
			generateUrl,
		}
	},

	watch: {
		settings: 'submit',
		error(error) {
			if (!error) return
			OC.Notification.showTemporary(error)
		},
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
				await this.setValue(setting, this.settings[setting])
			}
			this.loading = false
			this.success = true
			this.testDate = Date.now()
			setTimeout(() => {
				this.success = false
			}, 3000)
		},

		async loadValue(setting) {
			this.settings[setting] = await this.getValue(setting)
			if (BOOLEAN_SETTINGS.includes(setting)) {
				this.settings[setting] = JSON.parse(this.settings[setting])
			}
		},
		async setValue(setting, value) {
			try {
				if (BOOLEAN_SETTINGS.includes(setting)) {
					value = JSON.stringify(value)
				}
				await axios.put(generateUrl(`/apps/bookmarks/admin/settings/${setting}`), {
					value,
				})
			} catch (e) {
				this.error = this.t('recognize', 'Failed to save settings')
				throw e
			}
		},

		async getValue(setting) {
			try {
				const res = await axios.get(generateUrl(`/apps/bookmarks/admin/settings/${setting}`))
				if (res.status !== 200) {
					this.error = this.t('bookmarks', 'Failed to load settings')
					console.error('Failed request', res)
					return
				}
				return res.data.value
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

h3 {
	font-weight: bold;
}

.test-screenshot {
	border: 1px solid black;
	width: 200px;
	margin: 10px 0;
}

.test-screenshot figcaption {
	padding: 5px;
}

.test-screenshot img {
	width: 200px;
	color: #ebebeb;
}
</style>
