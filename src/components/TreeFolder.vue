<template>
  <div class="treefolder">
    <div class="treefolder__title">
      <h4 @click="showChildren = !showChildren">
        <figure class="icon-folder" />
        {{ folder.title }}
      </h4>
      <button @click="$emit('select', folder.id)">
        {{ t('bookmarks', 'Select') }}
      </button>
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

export default {
	name: 'TreeFolder',
	props: {
		folder: {
			type: Object,
			required: true
		}
	},
	data() {
		return { showChildren: false };
	}
};
</script>
<style>
.treefolder__title {
	display: flex;
	align-items: center;
}
.treefolder__title > h4 {
	flex: 1;
	display: flex;
	cursor: pointer;
}
.treefolder__title > h4 > figure {
	margin: 0 5px;
}
.treefolder__title > button {
	flex: 0;
}
.treefolder__children {
	padding-left: 20px;
}
</style>
