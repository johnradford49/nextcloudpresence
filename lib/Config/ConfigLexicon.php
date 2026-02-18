<?php

declare(strict_types=1);

namespace OCA\NextcloudPresence\Config;

use NCU\Config\Lexicon\ConfigLexiconEntry;
use NCU\Config\Lexicon\ConfigLexiconStrictness;
use NCU\Config\Lexicon\IConfigLexicon;
use NCU\Config\ValueType;

class ConfigLexicon implements IConfigLexicon {
	public function getStrictness(): ConfigLexiconStrictness {
		return ConfigLexiconStrictness::NOTICE;
	}

	public function getAppConfigs(): array {
		return [
			new ConfigLexiconEntry(
				key: 'ha_url',
				type: ValueType::STRING,
				defaultRaw: '',
				definition: 'Home Assistant URL',
				lazy: true,
			),
			new ConfigLexiconEntry(
				key: 'ha_token',
				type: ValueType::STRING,
				defaultRaw: '',
				definition: 'Home Assistant long-lived access token',
				lazy: true,
				flags: \OCP\IAppConfig::FLAG_SENSITIVE,
			),
			new ConfigLexiconEntry(
				key: 'ha_polling_interval',
				type: ValueType::STRING,
				defaultRaw: '30',
				definition: 'Polling interval in seconds (minimum 10)',
				lazy: true,
			),
			new ConfigLexiconEntry(
				key: 'ha_connection_timeout',
				type: ValueType::STRING,
				defaultRaw: '10',
				definition: 'Connection timeout in seconds',
				lazy: true,
			),
			new ConfigLexiconEntry(
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
