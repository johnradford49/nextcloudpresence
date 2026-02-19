<?php

declare(strict_types=1);

namespace OCA\NextcloudPresence\Controller;

use OCA\NextcloudPresence\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;

/**
 * @psalm-suppress UnusedClass
 */
class SettingsController extends Controller {
	#[NoCSRFRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	public function index(): TemplateResponse {
		return new TemplateResponse(
			Application::APP_ID,
			'admin',
			[],
			TemplateResponse::RENDER_AS_USER
		);
	}
}
