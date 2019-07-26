
import AppGlobal from './mixins/AppGlobal'
import store from './store'
import axios from 'nextcloud-axios'

export default {
	t: AppGlobal.methods.t,

	url(url) {
		url = `/apps/bookmarks/public/rest/v2${url}`
		return OC.generateUrl(url)
	},

	handleSyncError(message) {
		OC.Notification.showTemporary(message + ' ' + this.t('bookmarks', 'See JavaScript console for details.'))
	},


}
