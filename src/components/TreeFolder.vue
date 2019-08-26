<template>
  <div class="treefolder">
    <div class="treefolder__title">
      <h4 @click="showChildren = !showChildren">
        <figure class="icon-folder" />
        {{ folder.title }}
      </h4>
      <Actions>
        <ActionButton icon="icon-checkmark" @click="$emit('select', folder.id)">
          {{ t('bookmarks', 'Select') }}
        </ActionButton>
      </Actions>
    </div>
    <div v-if="showChildren" class="treefolder__children">
      <TreeFolder
        v-for="child in folder.children"
        :key="child.id"
        :folder="child"
        @select="$emit('select', $event)"
      />
    </div>
  </div>
</template>
<script>
import { Actions, ActionButton } from 'nextcloud-vue';

export default {
	name: 'TreeFolder',
	components: { Actions, ActionButton },
	props: {
		folder: {
			type: Object,
			required: true
		},
		showChildren: {
			type: Boolean,
			default: false
		}
	}
};
</script>
<style>
.treefolder__title {
	display: flex;
	align-items: center;
}

.treefolder__title:hover {
	background: var(--color-background-dark);
}

.treefolder__title > h4 {
	flex: 1;
	display: flex;
	cursor: pointer;
}

.treefolder__title > h4 > figure {
	margin: 0 5px;
}

.treefolder__children {
	padding-left: 20px;
}
</style>
