<script setup lang="ts">
import { ref, onMounted } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'

const haUrl = ref('')
const haToken = ref('')
const testing = ref(false)
const saving = ref(false)
const testResult = ref<{ success: boolean; message: string } | null>(null)

const loadSettings = async () => {
	try {
		const response = await axios.get(generateUrl('/apps/nextcloudpresence/api/settings'))
		haUrl.value = response.data.url || ''
		haToken.value = response.data.token || ''
	} catch (e) {
		showError('Failed to load settings')
	}
}

const saveSettings = async () => {
	saving.value = true
	testResult.value = null
	
	try {
		await axios.post(generateUrl('/apps/nextcloudpresence/api/settings'), {
			url: haUrl.value,
			token: haToken.value,
		})
		showSuccess('Settings saved successfully')
	} catch (e) {
		showError('Failed to save settings')
	} finally {
		saving.value = false
	}
}

const testConnection = async () => {
	testing.value = true
	testResult.value = null
	
	try {
		const response = await axios.get(generateUrl('/apps/nextcloudpresence/api/test-connection'))
		testResult.value = response.data
		
		if (response.data.success) {
			showSuccess('Connection successful!')
		} else {
			showError(response.data.message)
		}
	} catch (e: any) {
		testResult.value = {
			success: false,
			message: e.response?.data?.message || 'Failed to test connection'
		}
		showError('Connection test failed')
	} finally {
		testing.value = false
	}
}

onMounted(() => {
	loadSettings()
})
</script>

<template>
	<div class="section">
		<h2>Home Assistant Integration</h2>
		<p class="settings-hint">
			Configure your Home Assistant connection to fetch person presence data.
		</p>
		
		<NcNoteCard type="info">
			<p>
				To get a Long-Lived Access Token from Home Assistant:
				<ol>
					<li>Go to your Home Assistant profile (click your username in the bottom left)</li>
					<li>Scroll down to "Long-Lived Access Tokens"</li>
					<li>Click "Create Token"</li>
					<li>Give it a name (e.g., "Nextcloud Presence")</li>
					<li>Copy the token and paste it below</li>
				</ol>
			</p>
		</NcNoteCard>
		
		<div class="settings-form">
			<NcTextField
				:value.sync="haUrl"
				label="Home Assistant URL"
				placeholder="http://homeassistant.local:8123"
				:helper-text="'The full URL to your Home Assistant instance'"
			/>
			
			<NcTextField
				:value.sync="haToken"
				label="Long-Lived Access Token"
				type="password"
				placeholder="Enter your Home Assistant token"
				:helper-text="'Your Home Assistant access token'"
			/>
			
			<div class="button-group">
				<NcButton
					type="primary"
					:disabled="saving || !haUrl || !haToken"
					@click="saveSettings">
					{{ saving ? 'Saving...' : 'Save Settings' }}
				</NcButton>
				
				<NcButton
					:disabled="testing || !haUrl || !haToken"
					@click="testConnection">
					{{ testing ? 'Testing...' : 'Test Connection' }}
				</NcButton>
			</div>
			
			<NcNoteCard v-if="testResult" :type="testResult.success ? 'success' : 'error'">
				{{ testResult.message }}
			</NcNoteCard>
		</div>
	</div>
</template>

<style scoped>
.section {
	padding: 20px;
	max-width: 900px;
}

.settings-hint {
	color: var(--color-text-lighter);
	margin-bottom: 20px;
}

.settings-form {
	display: flex;
	flex-direction: column;
	gap: 20px;
	margin-top: 20px;
}

.button-group {
	display: flex;
	gap: 10px;
	margin-top: 10px;
}
</style>
