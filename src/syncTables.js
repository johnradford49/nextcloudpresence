import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const ocsHeaders = {
	'OCS-APIRequest': 'true',
	Accept: 'application/json',
}

const NAME_COLUMN_ID = 21
const STATE_COLUMN_ID = 22
const LAST_CHANGED_COLUMN_ID = 23

function ocsData(resp) {
	return resp?.data?.ocs?.data
}

function rowDataArray(row) {
	return row?.data ?? [] // [{ columnId, value }, ...]
}

function getValueByColumnId(row, columnId) {
	const entry = rowDataArray(row).find(x => x.columnId === columnId)
	return entry?.value ?? ''
}

function buildRowPayloadFromPerson(p) {
	return {
		data: [
			{ columnId: NAME_COLUMN_ID, value: String(p.name ?? '') },
			{ columnId: STATE_COLUMN_ID, value: p.home ? 'home' : 'away' },
			{ columnId: LAST_CHANGED_COLUMN_ID, value: String(p.last_changed ?? '') },
		],
	}
}

export async function syncPresenceToTables({ tableId, persons }) {
	// 1) list existing rows in the table
	const listUrl = generateUrl(`/ocs/v2.php/apps/tables/api/2/tables/${tableId}/rows`)
	const listResp = await axios.get(listUrl, { headers: ocsHeaders })
	const rows = ocsData(listResp) ?? []

	// 2) map rows by "name"
	const existingByName = new Map()
	for (const row of rows) {
		const name = getValueByColumnId(row, NAME_COLUMN_ID)
		if (name) existingByName.set(name, row)
	}

	// 3) upsert each person
	for (const p of persons) {
		const payload = buildRowPayloadFromPerson(p)
		const existing = existingByName.get(p.name)

		if (existing?.id) {
			// Update row (this is the first endpoint to try for Tables API v2)
			const updateUrl = generateUrl(`/ocs/v2.php/apps/tables/api/2/rows/${existing.id}`)
			await axios.put(updateUrl, payload, { headers: ocsHeaders })
		} else {
			// Create row
			const createUrl = generateUrl(`/ocs/v2.php/apps/tables/api/2/tables/${tableId}/rows`)
			await axios.post(createUrl, payload, { headers: ocsHeaders })
		}
	}
}
