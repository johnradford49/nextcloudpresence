# Nextcloud Presence

Show person presence in Nextcloud from Home Assistant person location trackers.

## Features

- Display person presence status from Home Assistant in Nextcloud
- Configure Home Assistant connection through admin settings
- Test connection to verify configuration
- Configurable polling interval and connection timeout
- SSL verification options

## Installation

1. Download or clone this repository to your Nextcloud `apps/` directory
2. Enable the app in Nextcloud's app management interface
3. Configure the Home Assistant connection in admin settings

## Configuration

After installation, configure the app by navigating to:

**Settings → Administration → Additional settings → Nextcloud Presence**

You will need:
- **Home Assistant URL**: The full URL to your Home Assistant instance (e.g., `http://homeassistant.local:8123`)
- **Long-Lived Access Token**: Create one in Home Assistant:
  1. Click your username in the bottom left of Home Assistant
  2. Scroll down to "Long-Lived Access Tokens"
  3. Click "Create Token"
  4. Give it a name (e.g., "Nextcloud Presence")
  5. Copy the token and paste it in the Nextcloud settings
If getting access violation error, add 'allow_local_remote_servers' => true, to nextcloud config.php

### Advanced Options

- **Polling Interval**: How often to refresh presence data (minimum: 10 seconds)
- **Connection Timeout**: Maximum time to wait for Home Assistant to respond
- **Verify SSL Certificate**: Disable only if using self-signed certificates

## Development

### Building the Frontend

```bash
npm install
npm run build
```

### Running Tests

```bash
composer test:unit
```

## Resources

### Documentation for developers:

- General documentation and tutorials: https://nextcloud.com/developer
- Technical documentation: https://docs.nextcloud.com/server/latest/developer_manual

### Help for developers:

- Official community chat: https://cloud.nextcloud.com/call/xs25tz5y
- Official community forum: https://help.nextcloud.com/c/dev/11
