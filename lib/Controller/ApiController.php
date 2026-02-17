<?php

declare(strict_types=1);

namespace OCA\NextcloudPresence\Controller;

use OCA\NextcloudPresence\Service\HomeAssistantService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IConfig;

/**
 * @psalm-suppress UnusedClass
 */
class ApiController extends OCSController {
	public function __construct(
		string $appName,
		$request,
		private HomeAssistantService $haService,
		private IConfig $config,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get person presence data from Home Assistant
	 *
	 * @return DataResponse<Http::STATUS_OK, array, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 *
	 * 200: Presence data returned
	 * 500: Error occurred
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/presence')]
	public function getPresence(): DataResponse {
		$result = $this->haService->getPersonPresence();
		
		if (!$result['success']) {
			return new DataResponse(
				['error' => $result['error'] ?? 'Unknown error'],
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}

		return new DataResponse($result['data'] ?? []);
	}

	/**
	 * Test Home Assistant connection
	 *
	 * @return DataResponse<Http::STATUS_OK, array{success: bool, message: string}, array{}>
	 *
	 * 200: Test result
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/test-connection')]
	public function testConnection(): DataResponse {
		$result = $this->haService->testConnection();
		return new DataResponse($result);
	}

	/**
	 * Save Home Assistant settings
	 *
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>
	 *
	 * 200: Settings saved
	 */
	#[ApiRoute(verb: 'POST', url: '/settings')]
	public function saveSettings(string $url, string $token): DataResponse {
		// Remove trailing slashes from URL
		$url = rtrim($url, '/');
		
		$this->config->setAppValue('nextcloudpresence', 'ha_url', $url);
		$this->config->setAppValue('nextcloudpresence', 'ha_token', $token);

		return new DataResponse(['success' => true]);
	}

	/**
	 * Get Home Assistant settings
	 *
	 * @return DataResponse<Http::STATUS_OK, array{url: string, token: string}, array{}>
	 *
	 * 200: Settings returned
	 */
	#[ApiRoute(verb: 'GET', url: '/settings')]
	public function getSettings(): DataResponse {
		return new DataResponse([
			'url' => $this->config->getAppValue('nextcloudpresence', 'ha_url', ''),
			'token' => $this->config->getAppValue('nextcloudpresence', 'ha_token', ''),
		]);
	}
}
