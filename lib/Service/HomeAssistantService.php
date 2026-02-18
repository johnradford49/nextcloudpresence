<?php

declare(strict_types=1);

namespace OCA\NextcloudPresence\Service;

use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class HomeAssistantService {
	private array $cache = [];

	public function __construct(
		private IClientService $clientService,
		private IConfig $config,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Get the cache TTL from configuration
	 *
	 * @return int Cache TTL in seconds
	 */
	private function getCacheTTL(): int {
		return (int)$this->config->getAppValue('nextcloudpresence', 'ha_polling_interval', '30');
	}

	/**
	 * Get the connection timeout from configuration
	 *
	 * @return int Connection timeout in seconds
	 */
	private function getConnectionTimeout(): int {
		return (int)$this->config->getAppValue('nextcloudpresence', 'ha_connection_timeout', '10');
	}

	/**
	 * Get SSL verification setting from configuration
	 *
	 * @return bool Whether to verify SSL certificates
	 */
	private function getVerifySSL(): bool {
		return $this->config->getAppValue('nextcloudpresence', 'ha_verify_ssl', '1') === '1';
	}

	/**
	 * Sanitize URL for logging by removing path and query components
	 *
	 * @param string $url The URL to sanitize
	 * @return string Sanitized URL containing only scheme, host, and port
	 */
	private function sanitizeUrlForLogging(string $url): string {
		$parsedUrl = parse_url($url);
		if ($parsedUrl === false || !isset($parsedUrl['host'])) {
			return 'invalid-url';
		}

		$sanitizedUrl = ($parsedUrl['scheme'] ?? 'http') . '://' . $parsedUrl['host'];
		if (isset($parsedUrl['port'])) {
			$sanitizedUrl .= ':' . $parsedUrl['port'];
		}

		return $sanitizedUrl;
	}

	/**
	 * Test the connection to Home Assistant
	 *
	 * @param string $url URL to test (defaults to saved config if empty)
	 * @param string $token Token to test (defaults to saved config if empty)
	 * @param int $connectionTimeout Connection timeout in seconds (defaults to saved config or 10)
	 * @param bool $verifySSL Whether to verify SSL certificates (defaults to saved config or true)
	 * @return array{success: bool, message: string}
	 */
	public function testConnection(
		string $url = '',
		string $token = '',
		int $connectionTimeout = 0,
		bool $verifySSL = true,
	): array {
		$this->logger->info('Testing Home Assistant connection', [
			'url_provided' => !empty($url),
			'token_provided' => !empty($token),
			'timeout' => $connectionTimeout,
			'verify_ssl' => $verifySSL,
		]);

		// Use provided values if not empty, otherwise fall back to saved config
		if ($url === '') {
			$url = $this->config->getAppValue('nextcloudpresence', 'ha_url', '');
			$this->logger->debug('Using saved URL from config');
		}
		if ($token === '') {
			$token = $this->config->getAppValue('nextcloudpresence', 'ha_token', '');
			$this->logger->debug('Using saved token from config');
		}
		if ($connectionTimeout <= 0) {
			$connectionTimeout = $this->getConnectionTimeout();
			$this->logger->debug('Using connection timeout from config', ['timeout' => $connectionTimeout]);
		}

		if ($url === '' || $token === '') {
			$this->logger->warning('Home Assistant URL or token is empty', [
				'url_empty' => $url === '',
				'token_empty' => $token === '',
			]);
			return [
				'success' => false,
				'message' => 'Home Assistant URL and token must be configured',
			];
		}

		$sanitizedUrl = $this->sanitizeUrlForLogging($url);

		$this->logger->info('Initiating connection test', [
			'url' => $sanitizedUrl,
			'api_endpoint' => $sanitizedUrl . '/api/',
			'timeout' => $connectionTimeout,
			'verify_ssl' => $verifySSL,
		]);

		try {
			$client = $this->clientService->newClient();
			$this->logger->debug('HTTP client created successfully');

			$response = $client->get($url . '/api/', [
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type' => 'application/json',
				],
				'timeout' => $connectionTimeout,
				'verify' => $verifySSL,
			]);

			$statusCode = $response->getStatusCode();
			$this->logger->info('Received response from Home Assistant', [
				'status_code' => $statusCode,
			]);

			if ($statusCode === 200) {
				$this->logger->info('Home Assistant connection test successful');
				return [
					'success' => true,
					'message' => 'Successfully connected to Home Assistant',
				];
			}

			$this->logger->warning('Home Assistant returned non-200 status code', [
				'status_code' => $statusCode,
			]);
			return [
				'success' => false,
				'message' => 'Failed to connect: HTTP ' . $statusCode,
			];
		} catch (\Exception $e) {
			$this->logger->error('Failed to connect to Home Assistant: ' . $e->getMessage(), [
				'exception' => $e,
				'exception_class' => get_class($e),
				'url' => $sanitizedUrl,
				'timeout' => $connectionTimeout,
				'verify_ssl' => $verifySSL,
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

		$this->logger->debug('Fetching person presence from Home Assistant', [
			'url_configured' => !empty($url),
			'token_configured' => !empty($token),
		]);

		if (empty($url) || empty($token)) {
			$this->logger->warning('Home Assistant not configured for person presence fetch');
			return [
				'success' => false,
				'error' => 'Home Assistant is not configured',
			];
		}

		// Check cache
		$cacheKey = 'person_presence';
		$cacheTTL = $this->getCacheTTL();
		if (isset($this->cache[$cacheKey])
			&& time() - $this->cache[$cacheKey]['timestamp'] < $cacheTTL) {
			$this->logger->debug('Returning cached person presence data', [
				'cache_age' => time() - $this->cache[$cacheKey]['timestamp'],
				'cache_ttl' => $cacheTTL,
			]);
			return $this->cache[$cacheKey]['data'];
		}

		$sanitizedUrl = $this->sanitizeUrlForLogging($url);

		$this->logger->debug('Cache miss, fetching fresh person presence data', [
			'url' => $sanitizedUrl,
			'timeout' => $this->getConnectionTimeout(),
			'verify_ssl' => $this->getVerifySSL(),
		]);

		try {
			$client = $this->clientService->newClient();
			$response = $client->get($url . '/api/states', [
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type' => 'application/json',
				],
				'timeout' => $this->getConnectionTimeout(),
				'verify' => $this->getVerifySSL(),
			]);

			$statusCode = $response->getStatusCode();
			$this->logger->debug('Received response from Home Assistant states endpoint', [
				'status_code' => $statusCode,
			]);

			if ($statusCode !== 200) {
				$this->logger->warning('Failed to fetch person presence, non-200 status', [
					'status_code' => $statusCode,
				]);
				return [
					'success' => false,
					'error' => 'Failed to fetch data: HTTP ' . $statusCode,
				];
			}

			$allStates = json_decode($response->getBody(), true);
			if (!is_array($allStates)) {
				$this->logger->error('Invalid response format from Home Assistant', [
					'response_type' => gettype($allStates),
				]);
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

			$this->logger->info('Successfully fetched person presence data', [
				'person_count' => count($persons),
			]);

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
				'exception_class' => get_class($e),
				'url' => $sanitizedUrl,
				'timeout' => $this->getConnectionTimeout(),
				'verify_ssl' => $this->getVerifySSL(),
			]);
			return [
				'success' => false,
				'error' => 'Connection error: ' . $e->getMessage(),
			];
		}
	}
}
