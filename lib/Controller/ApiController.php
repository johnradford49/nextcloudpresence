<?php

declare(strict_types=1);

namespace OCA\NextcloudPresence\Controller;

use OCA\NextcloudPresence\Service\HomeAssistantService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-suppress UnusedClass
 */
class ApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private HomeAssistantService $haService,
		private IConfig $config,
		private IGroupManager $groupManager,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Check if the current user is an administrator
	 *
	 * @return bool True if the user is an admin, false otherwise (including when no user is logged in)
	 */
	private function isUserAdmin(): bool {
		$user = $this->userSession->getUser();
		return $user !== null && $this->groupManager->isAdmin($user->getUID());
	}

	/**
	 * Get person presence data from Home Assistant
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array{entity_id: string, name: string, state: string, last_changed: string|null}>, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
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
	 * @param string $url Home Assistant URL to test
	 * @param string $token Home Assistant token to test
	 * @param int $connection_timeout Connection timeout in seconds (optional, defaults to 10)
	 * @param bool $verify_ssl Whether to verify SSL certificates (optional, defaults to true)
	 * @return DataResponse<Http::STATUS_OK, array{success: bool, message: string}, array{}>
	 *
	 * 200: Test result
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/test-connection')]
	public function testConnection(
		string $url = '',
		string $token = '',
		int $connection_timeout = 10,
		bool $verify_ssl = true,
	): DataResponse {
		// Validate connection timeout (minimum 5 seconds, maximum 60 seconds)
		if ($connection_timeout < 5) {
			$connection_timeout = 5;
		} elseif ($connection_timeout > 60) {
			$connection_timeout = 60;
		}

		$result = $this->haService->testConnection($url, $token, $connection_timeout, $verify_ssl);
		return new DataResponse($result);
	}

	/**
	 * Save Home Assistant settings
	 *
	 * @param string $url Home Assistant URL
	 * @param string $token Home Assistant long-lived access token
	 * @param int $polling_interval Polling interval in seconds
	 * @param int $connection_timeout Connection timeout in seconds
	 * @param bool $verify_ssl Whether to verify SSL certificates
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{error: string}, array{}>
	 *
	 * 200: Settings saved
	 * 403: User is not an admin
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/settings')]
	public function saveSettings(
		string $url,
		string $token,
		int $polling_interval = 30,
		int $connection_timeout = 10,
		bool $verify_ssl = true,
	): DataResponse {
		// Check if the user is an admin
		if (!$this->isUserAdmin()) {
			return new DataResponse(
				['error' => 'Only administrators can modify Home Assistant settings'],
				Http::STATUS_FORBIDDEN
			);
		}

		// Remove trailing slashes from URL
		$url = rtrim($url, '/');

		// Validate polling interval (minimum 10 seconds)
		if ($polling_interval < 10) {
			$polling_interval = 10;
		}

		// Validate connection timeout (minimum 5 seconds, maximum 60 seconds)
		if ($connection_timeout < 5) {
			$connection_timeout = 5;
		} elseif ($connection_timeout > 60) {
			$connection_timeout = 60;
		}

		$this->config->setAppValue('nextcloudpresence', 'ha_url', $url);
		$this->config->setAppValue('nextcloudpresence', 'ha_token', $token);
		$this->config->setAppValue('nextcloudpresence', 'ha_polling_interval', (string)$polling_interval);
		$this->config->setAppValue('nextcloudpresence', 'ha_connection_timeout', (string)$connection_timeout);
		$this->config->setAppValue('nextcloudpresence', 'ha_verify_ssl', $verify_ssl ? '1' : '0');

		return new DataResponse(['success' => true]);
	}

	/**
	 * Get Home Assistant settings
	 *
	 * @return DataResponse<Http::STATUS_OK, array{url: string, token: string, polling_interval: string, connection_timeout: string, verify_ssl: bool}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{error: string}, array{}>
	 *
	 * 200: Settings returned
	 * 403: User is not an admin
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/settings')]
	public function getSettings(): DataResponse {
		// Check if the user is an admin
		if (!$this->isUserAdmin()) {
			return new DataResponse(
				['error' => 'Only administrators can access Home Assistant settings'],
				Http::STATUS_FORBIDDEN
			);
		}

		return new DataResponse([
			'url' => $this->config->getAppValue('nextcloudpresence', 'ha_url', ''),
			'token' => $this->config->getAppValue('nextcloudpresence', 'ha_token', ''),
			'polling_interval' => $this->config->getAppValue('nextcloudpresence', 'ha_polling_interval', '30'),
			'connection_timeout' => $this->config->getAppValue('nextcloudpresence', 'ha_connection_timeout', '10'),
			'verify_ssl' => $this->config->getAppValue('nextcloudpresence', 'ha_verify_ssl', '1') === '1',
		]);
	}
}
