<script setup lang="ts">
import { ref, onMounted } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'

const haUrl = ref('')
const haToken = ref('')
const pollingInterval = ref(30)
const connectionTimeout = ref(10)
const verifySSL = ref(true)
const testing = ref(false)
const saving = ref(false)
const testResult = ref<{ success: boolean; message: string } | null>(null)

const loadSettings = async () => {
	try {
		const response = await axios.get(generateUrl('/ocs/v2.php/apps/nextcloudpresence/settings'))
		const data = response.data.ocs?.data || response.data
		haUrl.value = data.url || ''
		haToken.value = data.token || ''
		pollingInterval.value = parseInt(data.polling_interval) || 30
		connectionTimeout.value = parseInt(data.connection_timeout) || 10
		verifySSL.value = data.verify_ssl !== false
	} catch (e) {
		showError('Failed to load settings')
	}
}

const saveSettings = async () => {
	saving.value = true
	testResult.value = null

	try {
		await axios.post(generateUrl('/ocs/v2.php/apps/nextcloudpresence/settings'), {
			url: haUrl.value,
			token: haToken.value,
			polling_interval: pollingInterval.value,
			connection_timeout: connectionTimeout.value,
			verify_ssl: verifySSL.value,
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
		const response = await axios.get(generateUrl('/ocs/v2.php/apps/nextcloudpresence/test-connection'))
		const data = response.data.ocs?.data || response.data
		testResult.value = data

		if (data.success) {
			showSuccess('Connection successful!')
		} else {
			showError(data.message)
		}
	} catch (e: any) {
		testResult.value = {
			success: false,
			message: e.response?.data?.ocs?.data?.message || e.response?.data?.message || 'Failed to test connection',
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
				v-model="haUrl"
				label="Home Assistant URL"
				placeholder="http://homeassistant.local:8123"
				:helper-text="'The full URL to your Home Assistant instance'" />

			<NcTextField
				v-model="haToken"
				label="Long-Lived Access Token"
				type="password"
				placeholder="Enter your Home Assistant token"
				:helper-text="'Your Home Assistant access token'" />

			<div class="advanced-settings">
				<h3>Connection Options</h3>

				<NcTextField
					v-model="pollingInterval"
					label="Polling Interval (seconds)"
					type="number"
					placeholder="30"
					:helper-text="'How often to refresh presence data (minimum: 10 seconds)'" />

				<NcTextField
					v-model="connectionTimeout"
					label="Connection Timeout (seconds)"
					type="number"
					placeholder="10"
					:helper-text="'Maximum time to wait for Home Assistant to respond'" />

				<NcCheckboxRadioSwitch
					v-model="verifySSL"
					aria-describedby="ssl-hint"
					type="switch">
					Verify SSL Certificate
				</NcCheckboxRadioSwitch>
				<p id="ssl-hint" class="settings-hint ssl-hint">
					Disable only if using self-signed certificates
				</p>
			</div>

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

.advanced-settings {
	margin-top: 30px;
	padding-top: 20px;
	border-top: 1px solid var(--color-border);
}

.advanced-settings h3 {
	margin-bottom: 15px;
	font-size: 16px;
	font-weight: 600;
}

.ssl-hint {
	margin-top: -10px;
	margin-left: 40px;
	font-size: 13px;
}

.button-group {
	display: flex;
	gap: 10px;
	margin-top: 10px;
}
</style>
