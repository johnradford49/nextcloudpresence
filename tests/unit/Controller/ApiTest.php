<?php

declare(strict_types=1);

namespace Controller;

use OCA\NextcloudPresence\AppInfo\Application;
use OCA\NextcloudPresence\Controller\ApiController;
use OCA\NextcloudPresence\Service\HomeAssistantService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase {
	public function testTestConnectionSuccess(): void {
		$request = $this->createMock(IRequest::class);
		$haService = $this->createMock(HomeAssistantService::class);
		$appConfig = $this->createMock(IAppConfig::class);
		$groupManager = $this->createMock(IGroupManager::class);
		$userSession = $this->createMock(IUserSession::class);
		$appManager = $this->createMock(IAppManager::class);

		$haService->expects($this->once())
			->method('testConnection')
			->with('http://homeassistant.local:8123', 'token123', 10, true)
			->willReturn(['success' => true, 'message' => 'Successfully connected to Home Assistant']);

		$controller = new ApiController(
			Application::APP_ID,
			$request,
			$haService,
			$appConfig,
			$groupManager,
			$userSession,
			$appManager,
		);

		$response = $controller->testConnection('http://homeassistant.local:8123', 'token123', 10, true);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertTrue($response->getData()['success']);
		$this->assertEquals('Successfully connected to Home Assistant', $response->getData()['message']);
	}

	public function testTestConnectionFailure(): void {
		$request = $this->createMock(IRequest::class);
		$haService = $this->createMock(HomeAssistantService::class);
		$appConfig = $this->createMock(IAppConfig::class);
		$groupManager = $this->createMock(IGroupManager::class);
		$userSession = $this->createMock(IUserSession::class);
		$appManager = $this->createMock(IAppManager::class);

		$haService->expects($this->once())
			->method('testConnection')
			->willReturn([
				'success' => false,
				'message' => 'Could not connect to Home Assistant. Please verify the URL is correct and the server is running and accessible.',
			]);

		$controller = new ApiController(
			Application::APP_ID,
			$request,
			$haService,
			$appConfig,
			$groupManager,
			$userSession,
			$appManager,
		);

		$response = $controller->testConnection('http://192.168.107.168:8081', 'token123', 10, true);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertFalse($response->getData()['success']);
		$this->assertStringNotContainsString('ConnectException', $response->getData()['message']);
		$this->assertStringNotContainsString('cURL error', $response->getData()['message']);
	}
}
