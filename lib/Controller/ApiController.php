<?php

declare(strict_types=1);

namespace OCA\NextcloudPresence\Controller;

use OCA\NextcloudPresence\AppInfo\Application;
use OCA\NextcloudPresence\Service\HomeAssistantService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

class ApiController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private HomeAssistantService $haService,
		private IAppConfig $appConfig,
		private IGroupManager $groupManager,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/presence')]
	public function getPresence(): DataResponse {
		$result = $this->haService->getPersonPresence();
		if (!$result['success']) {
			return new DataResponse(['error' => $result['error'] ?? 'Unknown error'], 503);
		}
		return new DataResponse($result['data'] ?? []);
	}

	#[ApiRoute(verb: 'GET', url: '/settings')]
	public function getSettings(): DataResponse {
		return new DataResponse([
			'url' => $this->appConfig->getValueString(Application::APP_ID, 'ha_url', '', lazy: true),
			'token' => $this->appConfig->getValueString(Application::APP_ID, 'ha_token', '', lazy: true),
			'polling_interval' => $this->appConfig->getValueString(Application::APP_ID, 'ha_polling_interval', '30', lazy: true),
			'connection_timeout' => $this->appConfig->getValueString(Application::APP_ID, 'ha_connection_timeout', '10', lazy: true),
			'verify_ssl' => $this->appConfig->getValueString(Application::APP_ID, 'ha_verify_ssl', '1', lazy: true) === '1',
			'tables_table_id' => $this->appConfig->getValueString(Application::APP_ID, 'tables_table_id', '0', lazy: true),
		]);
	}

	#[ApiRoute(verb: 'POST', url: '/settings')]
	public function saveSettings(
		string $url = '',
		string $token = '',
		int $polling_interval = 30,
		int $connection_timeout = 10,
		bool $verify_ssl = true,
		int $tables_table_id = 0,
	): DataResponse {
		$this->appConfig->setValueString(Application::APP_ID, 'ha_url', $url);
		$this->appConfig->setValueString(Application::APP_ID, 'ha_token', $token);
		$this->appConfig->setValueString(Application::APP_ID, 'ha_polling_interval', (string)max(10, $polling_interval));
		$this->appConfig->setValueString(Application::APP_ID, 'ha_connection_timeout', (string)max(1, $connection_timeout));
		$this->appConfig->setValueString(Application::APP_ID, 'ha_verify_ssl', $verify_ssl ? '1' : '0');
		$this->appConfig->setValueString(Application::APP_ID, 'tables_table_id', (string)max(0, $tables_table_id));
		return new DataResponse(['status' => 'ok']);
	}

	#[ApiRoute(verb: 'POST', url: '/test-connection')]
	public function testConnection(
		string $url = '',
		string $token = '',
		int $connection_timeout = 0,
		bool $verify_ssl = true,
	): DataResponse {
		$result = $this->haService->testConnection($url, $token, $connection_timeout, $verify_ssl);
		return new DataResponse($result);
	}
}
