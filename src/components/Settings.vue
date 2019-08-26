<template>
  <div class="settings">
    <h3>{{ t('bookmarks', 'Bookmarklet') }}</h3>
    <p>
      {{
        t(
          'bookmarks',
          'Drag this to your browser bookmarks and click it, when ' +
            'you want to bookmark a webpage quickly:'
        )
      }}
    </p>
    <p>&nbsp;</p>
    <p>
      <a class="button" :href="bookmarklet" @click.prevent="void 0">{{
        t('bookmarks', 'Add to {instanceName} ', {
          instanceName: oc_defaults.name
        })
      }}</a>
    </p>

    <h3>{{ t('bookmarks', 'Client apps') }}</h3>
    <p>
      Check out the collection of
      <a href="https://github.com/nextcloud/bookmarks#third-party-clients">client apps</a>
      that integrate with this app.
    </p>

    <h3>{{ t('bookmarks', 'Import & Export') }}</h3>
    <input type="file" class="import" size="5"
           @change="onImportSubmit"
    >
    <button @click="onImportOpen">
      <span class="icon-upload" />{{ t('bookmarks', 'Import') }}
    </button>
    <button @click="onExport">
      <span class="icon-download" /> {{ t('bookmarks', 'Export') }}
    </button>

    <h3>{{ t('bookmarks', 'Sorting') }}</h3>
    <select :value="sorting" @change="onChangeSorting">
      <option id="added" value="added">
        {{
          t('bookmarks', 'Recently added')
        }}
      </option>
      <option id="title" value="title">
        {{
          t('bookmarks', 'Alphabetically')
        }}
      </option>
      <option id="clickcount" value="clickcount">
        {{
          t('bookmarks', 'Most visited')
        }}
      </option>
      <option id="lastmodified" value="lastmodified">
        {{
          t('bookmarks', 'Last modified')
        }}
      </option>
    </select>

    <h3>{{ t('bookmarks', 'View mode') }}</h3>
    <select :value="viewMode" @change="onChangeViewMode">
      <option id="grid" value="grid">
        {{ t('bookmarks', 'Grid view') }}
      </option>
      <option id="list" value="list">
        {{ t('bookmarks', 'List view') }}
      </option>
    </select>

    <h3>{{ t('bookmarks', 'RSS Feed') }}</h3>
    <p>
      {{
        t(
          'bookmarks',
          'This is an RSS feed of the current result set with ' +
            'access restricted to you.'
        )
      }}
    </p>
    <input type="text" readonly :value="rssURL"
           @click="onRssClick"
    >

    <h3>{{ t('bookmarks', 'Clear data') }}</h3>
    <p>
      {{
        t(
          'bookmarks',
          'Permanently remove all bookmarks from your account. ' +
            'There is no going back!'
        )
      }}
    </p>
    <button class="clear-data" @click="onClearData">
      <span class="icon-delete" />
      {{ t('bookmarks', 'Delete all bookmarks') }}
    </button>
  </div>
</template>
<script>
import { generateUrl } from 'nextcloud-router';
import { actions } from '../store';
export default {
	name: 'Settings',
	components: {},
	computed: {
		oc_defaults() {
			return window.oc_defaults;
		},
		bookmarklet() {
			const bookmarkletUrl
				= window.location.origin + generateUrl('/apps/bookmarks/bookmarklet');
			return `javascript:(function(){var a=window,b=document,c=encodeURIComponent,e=c(document.title),d=a.open('${bookmarkletUrl}?output=popup&url='+c(b.location)+'&title='+e,'bkmk_popup','left='+((a.screenX||a.screenLeft)+10)+',top='+((a.screenY||a.screenTop)+10)+',height=500px,width=550px,resizable=1,alwaysRaised=1');a.setTimeout(function(){d.focus()},300);})();`;
		},
		rssURL() {
			return (
				window.location.origin
				+ generateUrl(
					'/apps/bookmarks/public/rest/v2/bookmark?'
						+ $.param(
							Object.assign({}, this.$store.state.fetchState.query, {
								format: 'rss',
								page: -1
							})
						)
				)
			);
		},
		viewMode() {
			return this.$store.state.settings.viewMode;
		},
		sorting() {
			return this.$store.state.settings.sorting;
		}
	},
	methods: {
		onImportOpen(e) {
			e.target.previousElementSibling.click();
		},
		onImportSubmit(e) {
			this.$store.dispatch(actions.IMPORT_BOOKMARKS, e.target.files[0]);
		},
		onExport() {
			window.location
				= 'bookmark/export?requesttoken='
				+ encodeURIComponent(window.oc_requesttoken);
		},
		async onChangeSorting(e) {
			await this.$store.dispatch(actions.SET_SETTING, {
				key: 'sorting',
				value: e.target.value
			});
			this.$router.push({ name: 'home' });
		},
		onChangeViewMode(e) {
			this.$store.dispatch(actions.SET_SETTING, {
				key: 'viewMode',
				value: e.target.value
			});
		},
		onRssClick(e) {
			setTimeout(() => {
				e.target.select();
			}, 100);
		},
		async onClearData() {
			if (
				!confirm(
					t('bookmarks', 'Do you really want to delete all your bookmarks?')
				)
			) {
				return;
			}
			await this.$store.dispatch(actions.DELETE_BOOKMARKS);
			this.$router.push({ name: 'home' });
		}
	}
};
</script>
<style>
.import {
	opacity: 0;
	position: absolute;
}
.settings a:link:not(.button) {
	text-decoration: underline;
}
</style>
