<script setup lang="ts">
import { ref, onMounted } from 'vue'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

interface Person {
	entity_id: string
	name: string
	state: string
	last_changed: string | null
}

const persons = ref<Person[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const configured = ref(true)

const fetchPresence = async () => {
	loading.value = true
	error.value = null

	try {
		const response = await axios.get(generateUrl('/ocs/v2.php/apps/nextcloudpresence/api/v1/presence'))
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

onMounted(() => {
	fetchPresence()
})
</script>

<template>
	<NcContent app-name="nextcloudpresence">
		<NcAppContent :class="$style.content">
			<h2>Person Presence</h2>

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
