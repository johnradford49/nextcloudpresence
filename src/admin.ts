import { createApp } from 'vue'
import AdminSettings from './AdminSettings.vue'

const adminElement = document.getElementById('nextcloudpresence-admin')
if (adminElement) {
	const app = createApp(AdminSettings)
	app.mount('#nextcloudpresence-admin')
}
