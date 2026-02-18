<?php

declare(strict_types=1);

namespace OCA\NextcloudPresence\Config;

use OCP\Config\Lexicon\Entry;
use OCP\Config\Lexicon\ILexicon;
use OCP\Config\Lexicon\Strictness;
use OCP\Config\ValueType;

class ConfigLexicon implements ILexicon {
	public function getStrictness(): Strictness {
		return Strictness::IGNORE;
	}

	public function getAppConfigs(): array {
		return [
			new Entry(
				key: 'ha_url',
				type: ValueType::STRING,
				defaultRaw: '',
				definition: 'Home Assistant URL',
				lazy: true,
			),
			new Entry(
				key: 'ha_token',
				type: ValueType::STRING,
				defaultRaw: '',
				definition: 'Home Assistant long-lived access token',
				lazy: true,
				flags: \OCP\IAppConfig::FLAG_SENSITIVE,
			),
			new Entry(
				key: 'ha_polling_interval',
				type: ValueType::STRING,
				defaultRaw: '30',
				definition: 'Polling interval in seconds (minimum 10)',
				lazy: true,
			),
			new Entry(
				key: 'ha_connection_timeout',
				type: ValueType::STRING,
				defaultRaw: '10',
				definition: 'Connection timeout in seconds',
				lazy: true,
			),
			new Entry(
				key: 'ha_verify_ssl',
				type: ValueType::STRING,
				defaultRaw: '1',
				definition: 'Whether to verify SSL certificates (1 for true, 0 for false)',
				lazy: true,
			),
		];
	}

	public function getUserConfigs(): array {
		return [];
	}
}
