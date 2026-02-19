<script setup>
import { ref, onMounted } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { syncPresenceToTables } from './syncTables.js'

const persons = ref([])
const syncing = ref(false)
const syncError = ref('')
const syncOk = ref('')

const tableId = ref(0)

async function loadPresence() {
	const response = await axios.get(generateUrl('/ocs/v2.php/apps/nextcloudpresence/api/v1/presence'))
	const data = response.data.ocs?.data || response.data
	persons.value = data
}

async function loadSettings() {
	try {
		const response = await axios.get(generateUrl('/ocs/v2.php/apps/nextcloudpresence/settings'))
		const data = response.data.ocs?.data || response.data
		tableId.value = parseInt(data.tables_table_id, 10) || 0
	} catch (e) {
		tableId.value = 0
	}
}

async function syncToTables() {
	syncing.value = true
	syncError.value = ''
	syncOk.value = ''
	try {
		await syncPresenceToTables({ tableId: tableId.value, persons: persons.value })
		syncOk.value = 'Synced presence to Tables.'
	} catch (e) {
		syncError.value = e?.response?.data ? JSON.stringify(e.response.data) : (e?.message || String(e))
	} finally {
		syncing.value = false
	}
}

onMounted(async () => {
	await Promise.all([loadPresence(), loadSettings()])
})
</script>

<template>
	<div>
		<button v-if="tableId > 0" :disabled="syncing" @click="syncToTables">
			{{ syncing ? 'Syncing…' : 'Sync to Nextcloud Tables' }}
		</button>
		<p v-if="syncOk">
			{{ syncOk }}
		</p>
		<p v-if="syncError" style="color:red">
			{{ syncError }}
		</p>
	</div>
</template>
