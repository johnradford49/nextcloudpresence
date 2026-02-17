<?php

declare(strict_types=1);

namespace OCA\NextcloudPresence\Service;

use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class HomeAssistantService {
	private const CACHE_TTL = 30; // 30 seconds cache
	private array $cache = [];

	public function __construct(
		private IClientService $clientService,
		private IConfig $config,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Test the connection to Home Assistant
	 *
	 * @return array{success: bool, message: string}
	 */
	public function testConnection(): array {
		$url = $this->config->getAppValue('nextcloudpresence', 'ha_url', '');
		$token = $this->config->getAppValue('nextcloudpresence', 'ha_token', '');

		if (empty($url) || empty($token)) {
			return [
				'success' => false,
				'message' => 'Home Assistant URL and token must be configured',
			];
		}

		try {
			$client = $this->clientService->newClient();
			$response = $client->get($url . '/api/', [
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type' => 'application/json',
				],
				'timeout' => 10,
			]);

			if ($response->getStatusCode() === 200) {
				$data = json_decode($response->getBody(), true);
				return [
					'success' => true,
					'message' => 'Successfully connected to Home Assistant',
				];
			}

			return [
				'success' => false,
				'message' => 'Failed to connect: HTTP ' . $response->getStatusCode(),
			];
		} catch (\Exception $e) {
			$this->logger->error('Failed to connect to Home Assistant: ' . $e->getMessage(), [
				'exception' => $e,
			]);
			return [
				'success' => false,
				'message' => 'Connection error: ' . $e->getMessage(),
			];
		}
	}

	/**
	 * Fetch all person entities from Home Assistant
	 *
	 * @return array{success: bool, data?: array, error?: string}
	 */
	public function getPersonPresence(): array {
		$url = $this->config->getAppValue('nextcloudpresence', 'ha_url', '');
		$token = $this->config->getAppValue('nextcloudpresence', 'ha_token', '');

		if (empty($url) || empty($token)) {
			return [
				'success' => false,
				'error' => 'Home Assistant is not configured',
			];
		}

		// Check cache
		$cacheKey = 'person_presence';
		if (isset($this->cache[$cacheKey])
			&& time() - $this->cache[$cacheKey]['timestamp'] < self::CACHE_TTL) {
			return $this->cache[$cacheKey]['data'];
		}

		try {
			$client = $this->clientService->newClient();
			$response = $client->get($url . '/api/states', [
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type' => 'application/json',
				],
				'timeout' => 10,
			]);

			if ($response->getStatusCode() !== 200) {
				return [
					'success' => false,
					'error' => 'Failed to fetch data: HTTP ' . $response->getStatusCode(),
				];
			}

			$allStates = json_decode($response->getBody(), true);
			if (!is_array($allStates)) {
				return [
					'success' => false,
					'error' => 'Invalid response from Home Assistant',
				];
			}

			// Filter for person.* entities and format the data
			$persons = [];
			foreach ($allStates as $state) {
				if (isset($state['entity_id']) && str_starts_with($state['entity_id'], 'person.')) {
					$persons[] = [
						'entity_id' => $state['entity_id'],
						'name' => $state['attributes']['friendly_name'] ?? $state['entity_id'],
						'state' => $state['state'] ?? 'unknown',
						'last_changed' => $state['last_changed'] ?? null,
					];
				}
			}

			$result = [
				'success' => true,
				'data' => $persons,
			];

			// Update cache
			$this->cache[$cacheKey] = [
				'timestamp' => time(),
				'data' => $result,
			];

			return $result;
		} catch (\Exception $e) {
			$this->logger->error('Failed to fetch person presence from Home Assistant: ' . $e->getMessage(), [
				'exception' => $e,
			]);
			return [
				'success' => false,
				'error' => 'Connection error: ' . $e->getMessage(),
			];
		}
	}
}
