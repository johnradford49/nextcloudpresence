<?php

declare(strict_types=1);

namespace OCA\NextcloudPresence\Settings;

use OCA\NextcloudPresence\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {
	public function __construct(
		private IConfig $config,
	) {
	}

	public function getForm(): TemplateResponse {
		$parameters = [
			'ha_url' => $this->config->getAppValue(Application::APP_ID, 'ha_url', ''),
			'ha_token' => $this->config->getAppValue(Application::APP_ID, 'ha_token', ''),
		];

		return new TemplateResponse(Application::APP_ID, 'admin', $parameters);
	}

	public function getSection(): string {
		return 'additional';
	}

	public function getPriority(): int {
		return 50;
	}
}
