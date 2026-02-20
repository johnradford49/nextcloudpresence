<?php

declare(strict_types=1);

namespace OCA\NextcloudPresence\Settings;

use OCA\NextcloudPresence\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {
	public function getForm(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'admin', []);
	}

	public function getSection(): string {
		return 'additional';
	}

	public function getPriority(): int {
		return 50;
	}
}
