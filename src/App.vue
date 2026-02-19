<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcButton from '@nextcloud/vue/components/NcButton'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'

interface Person {
	entity_id: string
	name: string
	state: string
	last_changed: string | null
}

interface TablesColumn {
	id: number
	title: string
}

interface TablesRow {
	id: number
}

const persons = ref<Person[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const configured = ref(true)
const tablesAvailable = ref(false)
const syncingToTables = ref(false)

const fetchPresence = async () => {
	loading.value = true
	error.value = null

	try {
		const response = await axios.get(generateUrl('/ocs/v2.php/apps/nextcloudpresence/presence'))
		const data = response.data.ocs?.data || response.data
		persons.value = data
		configured.value = true
	} catch (e: any) {
		const errorData = e.response?.data?.ocs?.data || e.response?.data
		if (errorData?.error) {
			error.value = errorData.error
			if (error.value.includes('not configured')) {
				configured.value = false
			}
		} else {
			error.value = 'Failed to load presence data'
		}
	} finally {
		loading.value = false
	}
}

const checkTablesAvailability = async () => {
	try {
		const response = await axios.get(generateUrl('/ocs/v2.php/apps/nextcloudpresence/tables/status'))
		tablesAvailable.value = response.data.ocs?.data?.available ?? false
	} catch {
		tablesAvailable.value = false
	}
}

const exportCsv = () => {
	window.location.href = generateUrl('/ocs/v2.php/apps/nextcloudpresence/presence/export')
}

const syncToTables = async () => {
	if (syncingToTables.value) return
	syncingToTables.value = true

	try {
		const tableTitle = 'Person Presence'
		const tablesBase = generateUrl('/ocs/v2.php/apps/tables/api/1')

		// Find or create the table
		const tablesResp = await axios.get(`${tablesBase}/tables`)
		const allTables: Array<{ id: number; title: string }> = tablesResp.data.ocs?.data ?? []
		let tableId: number | null = null
		for (const t of allTables) {
			if (t.title === tableTitle) {
				tableId = t.id
				break
			}
		}

		if (tableId === null) {
			const createResp = await axios.post(`${tablesBase}/tables`, { title: tableTitle, emoji: '👤' })
			tableId = createResp.data.ocs?.data?.id
		}

		if (!tableId) {
			showError('Failed to create or find the Person Presence table in Nextcloud Tables.')
			return
		}

		// Get or create the required columns: Name, State, Entity ID, Last Changed
		const columnsResp = await axios.get(`${tablesBase}/tables/${tableId}/columns`)
		const existingColumns: TablesColumn[] = columnsResp.data.ocs?.data ?? []

		const requiredColumns = [
			{ key: 'name', title: 'Name' },
			{ key: 'state', title: 'State' },
			{ key: 'entity_id', title: 'Entity ID' },
			{ key: 'last_changed', title: 'Last Changed' },
		]

		const columnMap: Record<string, number> = {}
		for (const col of existingColumns) {
			for (const req of requiredColumns) {
				if (col.title === req.title) {
					columnMap[req.key] = col.id
				}
			}
		}

		for (const req of requiredColumns) {
			if (!(req.key in columnMap)) {
				const colResp = await axios.post(`${tablesBase}/tables/${tableId}/columns`, {
					type: 'text',
					subtype: 'line',
					title: req.title,
					mandatory: false,
				})
				columnMap[req.key] = colResp.data.ocs?.data?.id
			}
		}

		// Delete all existing rows
		const rowsResp = await axios.get(`${tablesBase}/tables/${tableId}/rows`)
		const existingRows: TablesRow[] = rowsResp.data.ocs?.data ?? []
		for (const row of existingRows) {
			await axios.delete(`${tablesBase}/rows/${row.id}`)
		}

		// Insert current presence data
		for (const person of persons.value) {
			const rowData = JSON.stringify([
				{ columnId: columnMap.name, value: person.name },
				{ columnId: columnMap.state, value: person.state },
				{ columnId: columnMap.entity_id, value: person.entity_id },
				{ columnId: columnMap.last_changed, value: person.last_changed ?? '' },
			])
			await axios.post(`${tablesBase}/rows`, { tableId, data: rowData })
		}

		showSuccess(`Synced ${persons.value.length} person(s) to the "${tableTitle}" table in Nextcloud Tables.`)
	} catch (e: any) {
		showError('Failed to sync to Nextcloud Tables. Please ensure the Tables app is installed and you have permission to create tables.')
	} finally {
		syncingToTables.value = false
	}
}

const formatDate = (dateString: string | null): string => {
	if (!dateString) return 'Unknown'
	const date = new Date(dateString)
	return date.toLocaleString()
}

const getStateClass = (state: string): string => {
	switch (state.toLowerCase()) {
	case 'home':
		return 'state-home'
	case 'away':
		return 'state-away'
	default:
		return 'state-other'
	}
}

const refreshInterval = ref<ReturnType<typeof setInterval> | null>(null)

onMounted(() => {
	fetchPresence()
	checkTablesAvailability()
	refreshInterval.value = setInterval(fetchPresence, 30000)
})

onUnmounted(() => {
	if (refreshInterval.value !== null) {
		clearInterval(refreshInterval.value)
	}
})
</script>

<template>
	<NcContent app-name="nextcloudpresence">
		<NcAppContent :class="$style.content">
			<div :class="$style.header">
				<h2>Person Presence</h2>
				<div :class="$style.actions">
					<NcButton v-if="persons.length >= 0"
						type="secondary"
						:class="$style.actionButton"
						@click="exportCsv">
						Export CSV
					</NcButton>
					<NcButton v-if="persons.length >= 0 && tablesAvailable"
						type="secondary"
						:disabled="syncingToTables"
						:class="$style.actionButton"
						@click="syncToTables">
						{{ syncingToTables ? 'Syncing…' : 'Sync to Tables' }}
					</NcButton>
				</div>
			</div>

			<NcLoadingIcon v-if="loading" :class="$style.loading" />

			<NcEmptyContent v-else-if="!configured"
				:class="$style.empty"
				title="Home Assistant Not Configured"
				description="Please configure your Home Assistant connection in the admin settings.">
				<template #icon>
					<span>⚙️</span>
				</template>
			</NcEmptyContent>

			<NcEmptyContent v-else-if="error"
				:class="$style.empty"
				:title="error"
				description="Please check your Home Assistant connection settings.">
				<template #icon>
					<span>⚠️</span>
				</template>
			</NcEmptyContent>

			<div v-else-if="persons.length === 0" :class="$style.empty">
				<NcEmptyContent
					title="No People Found"
					description="No person entities found in Home Assistant.">
					<template #icon>
						<span>👤</span>
					</template>
				</NcEmptyContent>
			</div>

			<div v-else :class="$style.personList">
				<div v-for="person in persons" :key="person.entity_id" :class="$style.personCard">
					<div :class="[$style.personState, $style[getStateClass(person.state)]]">
						{{ person.state }}
					</div>
					<div :class="$style.personName">
						{{ person.name }}
					</div>
					<div :class="$style.personInfo">
						<span :class="$style.label">Entity ID:</span> {{ person.entity_id }}
					</div>
					<div :class="$style.personInfo">
						<span :class="$style.label">Last Changed:</span> {{ formatDate(person.last_changed) }}
					</div>
				</div>
			</div>
		</NcAppContent>
	</NcContent>
</template>

<style module>
.content {
	display: flex;
	flex-direction: column;
	padding: 20px;
}

.header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 12px;
	margin-bottom: 8px;
}

.actions {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
}

.actionButton {
	white-space: nowrap;
}

.loading {
	margin: 50px auto;
}

.empty {
	margin: 50px auto;
}

.personList {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.personCard {
	border: 1px solid var(--color-border);
	border-radius: 8px;
	padding: 16px;
	background-color: var(--color-main-background);
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.personState {
	display: inline-block;
	padding: 4px 12px;
	border-radius: 12px;
	font-size: 12px;
	font-weight: 600;
	text-transform: uppercase;
	margin-bottom: 12px;
}

.stateHome {
	background-color: #4caf50;
	color: white;
}

.stateAway {
	background-color: #ff9800;
	color: white;
}

.stateOther {
	background-color: #9e9e9e;
	color: white;
}

.personName {
	font-size: 18px;
	font-weight: 600;
	margin-bottom: 8px;
}

.personInfo {
	font-size: 14px;
	color: var(--color-text-lighter);
	margin-top: 4px;
}

.label {
	font-weight: 500;
	color: var(--color-text-maxcontrast);
}
</style>
