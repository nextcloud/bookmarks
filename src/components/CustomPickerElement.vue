<!--
  - Copyright (c) 2023. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div style="width: 100%">
		<h2>
			{{ t('bookmarks', 'Bookmarks') }}
		</h2>
		<NcSearch :provider="{search_providers_ids: ['bookmarks'], title: t('bookmarks', 'Bookmarks')}"
			@cancel="cancelSearch"
			@submit="submitLink" />
	</div>
</template>

<script>
import { NcSearch } from '@nextcloud/vue/dist/Components/NcRichText.js'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'CustomPickerElement',
	components: {
		NcSearch,
	},
	methods: {
		cancelSearch() {
			this.$emit('cancel')
		},
		async submitLink(internalLink) {
			const res = await axios.get(generateUrl('/apps/bookmarks/bookmark/' + internalLink.slice(internalLink.lastIndexOf('/') + 1)))
			this.$emit('submit', res.data.item.url)
		},
	},
}
</script>
