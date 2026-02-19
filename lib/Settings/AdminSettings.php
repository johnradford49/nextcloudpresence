<?php

declare(strict_types=1);

namespace OCA\NextcloudPresence\Settings;

use OCA\NextcloudPresence\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {
	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	public function getForm(): TemplateResponse {
		$parameters = [
			'ha_url' => $this->appConfig->getValueString(Application::APP_ID, 'ha_url', '', lazy: true),
			'ha_token' => $this->appConfig->getValueString(Application::APP_ID, 'ha_token', '', lazy: true),
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
