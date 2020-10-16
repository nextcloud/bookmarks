<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div
		:class="{
			item: true,
			active,
			'item--gridview': viewMode === 'grid'
		}"
		:style="{ background }">
		<template v-if="!renaming">
			<a
				:href="url"
				class="item__clickLink"
				tabindex="0"
				target="_blank"
				@click="onClick">
				<div v-if="editable && selectable" ref="checkbox" class="item__checkbox">
					<input :checked="selected" class="checkbox" type="checkbox"><label
						v-tooltip="selectLabel"
						:aria-label="selectLabel"
						@click="$event.preventDefault(); $event.stopImmediatePropagation(); $emit('select');" />
				</div>
				<slot name="icon" />
				<div class="item__labels">
					<slot name="title" />
				</div>
				<div ref="tags">
					<slot name="tags">
						<TagLine :tags="tags" />
					</slot>
				</div>
				<div v-if="editable"
					ref="actions"
					class="item__actions"
					@click="$event.preventDefault(); $event.stopPropagation()">
					<Actions>
						<slot name="actions" />
					</Actions>
				</div>
			</a>
		</template>
		<div v-else class="item__rename">
			<slot name="icon" />
			<input
				ref="input"
				v-model="newTitle"
				type="text"
				:placeholder="renamePlaceholder"
				@keyup.enter="onRenameSubmit">
			<Actions>
				<ActionButton icon="icon-checkmark" @click="onRenameSubmit">
					{{ t('bookmarks', 'Submit') }}
				</ActionButton>
			</Actions>
			<Actions>
				<ActionButton icon="icon-close" @click="$emit('rename-cancel')">
					{{ t('bookmarks', 'Cancel') }}
				</ActionButton>
			</Actions>
		</div>
	</div>
</template>
<script>
import Vue from 'vue'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import TagLine from './TagLine'

export default {
	name: 'Item',
	components: {
		Actions,
		ActionButton,
		TagLine,
	},
	props: {
		title: {
			type: String,
			required: true,
		},
		url: {
			type: String,
			default: undefined,
		},
		active: {
			type: Boolean,
			default: false,
		},
		editable: {
			type: Boolean,
			default: false,
		},
		selected: {
			type: Boolean,
			default: false,
		},
		selectable: {
			type: Boolean,
			default: false,
		},
		renaming: {
			type: Boolean,
			default: false,
		},
		selectLabel: {
			type: String,
			required: true,
		},
		renamePlaceholder: {
			type: String,
			required: true,
		},
		background: {
			type: String,
			default: undefined,
		},
		tags: {
			type: Array,
			default: undefined,
		},
	},
	data() {
		return {
			newTitle: this.title,
		}
	},
	computed: {
		viewMode() {
			return this.$store.state.viewMode
		},
	},
	watch: {
		async renaming() {
			if (this.renaming) {
				await Vue.nextTick()
				this.$refs.input.focus()
			}
		},
	},
	methods: {
		async onRenameSubmit(event) {
			this.$emit('rename', this.newTitle)
		},
		onClick(e) {
			if (this.$refs.actions === e.target
					|| (this.$refs.actions && this.$refs.actions.contains(e.target))
					|| (this.$refs.checkbox
							&& (this.$refs.checkbox.contains(e.target) || this.$refs.checkbox === e.target)
					)
					|| (this.$refs.tags && this.$refs.tags.contains(e.target))
					|| this.$refs.tags === e.target) {
				e.stopImmediatePropagation()
				return
			}
			this.$emit('click', e)
		},
	},
}
</script>
<style>

.item {
	border-bottom: 1px solid var(--color-border);
	background-position: center !important;
	background-size: cover !important;
	background-color: var(--color-main-background);
	position: relative;
}

.item--gridview {
	width: 250px;
	height: 200px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	box-shadow: #efefef7d 0px 0 13px 0px inset;
	border-radius: var(--border-radius-large);
}

.item__clickLink,
.item__rename {
	display: flex;
	align-items: center;
	padding: 0 8px 0 10px;
}

.item--gridview .item__clickLink,
.item--gridview  .item__rename {
	position: absolute;
	bottom: 0;
	left: 0;
	right: 0;
	top: 0;
	display: flex;
	align-items: flex-end;
	padding: 0 8px 5px 10px;
}

.item.active,
.item:hover,
.item:focus {
	background: var(--color-background-dark);
}

.item.item--gridview.active {
	border-color: var(--color-primary-element);
}

.item__checkbox {
	display: inline-block;
}

.item__labels {
	display: flex;
	flex: 1;
	text-overflow: ellipsis;
	overflow: hidden;
	margin: 10px 0;
}

.item:not(.item--gridview) .item__rename input {
	width: 100%;
}

.item--gridview .item__checkbox {
	position: absolute;
	top: 10px;
	left: 10px;
	background: white;
	border-radius: var(--border-radius);
}

.item__actions {
	flex: 0;
}

.item--gridview .tagline {
	position: absolute;
	bottom: 47px;
	left: 10px;
	margin: 0;
}

.item--gridview .item__checkbox input[type='checkbox'].checkbox + label::before {
	margin: 0 3px 3px 3px;
}

</style>
