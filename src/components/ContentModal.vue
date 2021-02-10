<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<Modal :title="title" @close="$emit('close', $event)">
		<div class="content-modal">
			<h1 class="title">
				{{ bookmark.title }}
			</h1>
			<div v-html="content" />
		</div>
	</Modal>
</template>
<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import sanitizeHtml from 'sanitize-html'

export default {
	name: 'ContentModal',
	components: {
		Modal,
	},
	props: {
		bookmark: {
			type: Object,
			required: true,
		},
	},
	computed: {
		title() {
		  return this.bookmark.title
		},
		content() {
			return sanitizeHtml(this.bookmark.htmlContent, {
				allowProtocolRelative: false,
			})
		},
	},
}
</script>
<style>
.content-modal {
	overflow-y: scroll;
	padding: 44px;
	text-wrap: normal;
	height: 700px;
	max-width: 700px;
}

.content-modal .title {
	font-size: 2em;
	margin: 22px 0;
}

.content-modal h1, .content-modal h2, .content-modal h3, .content-modal h4, .content-modal h5, .content-modal p {
	margin-top: 10px !important;
}

.content-modal a:link,
.content-modal a[href] {
	text-decoration: underline !important;
}
</style>
