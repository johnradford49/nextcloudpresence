<?php

declare(strict_types=1);

namespace OCA\NextcloudPresence\Service;

use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

class HomeAssistantService {
	private const API_BASE_PATH = '/api/';
	private const API_STATES_PATH = '/api/states';

	private array $cache = [];

	public function __construct(
		private IClientService $clientService,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Get the cache TTL from configuration
	 *
	 * @return int Cache TTL in seconds
	 */
	private function getCacheTTL(): int {
		return (int)$this->appConfig->getValueString('nextcloudpresence', 'ha_polling_interval', '30', lazy: true);
	}

	/**
	 * Get the connection timeout from configuration
	 *
	 * @return int Connection timeout in seconds
	 */
	private function getConnectionTimeout(): int {
		return (int)$this->appConfig->getValueString('nextcloudpresence', 'ha_connection_timeout', '10', lazy: true);
	}

	/**
	 * Get SSL verification setting from configuration
	 *
	 * @return bool Whether to verify SSL certificates
	 */
	private function getVerifySSL(): bool {
		return $this->appConfig->getValueString('nextcloudpresence', 'ha_verify_ssl', '1', lazy: true) === '1';
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
			$url = $this->appConfig->getValueString('nextcloudpresence', 'ha_url', '', lazy: true);
			$this->logger->debug('Using saved URL from config');
		}
		if ($token === '') {
			$token = $this->appConfig->getValueString('nextcloudpresence', 'ha_token', '', lazy: true);
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
			'api_endpoint' => $sanitizedUrl . self::API_BASE_PATH,
			'timeout' => $connectionTimeout,
			'verify_ssl' => $verifySSL,
		]);

		try {
			$client = $this->clientService->newClient();
			$this->logger->debug('HTTP client created successfully');

			$response = $client->get($url . self::API_BASE_PATH, [
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
		} catch (\OCP\Http\Client\LocalServerException $e) {
			$this->logger->warning('Connection to local server blocked by SSRF protection', [
				'url' => $sanitizedUrl,
			]);
			return [
				'success' => false,
				'message' => 'Cannot connect to a local server. If your Home Assistant is on a local network, ask your Nextcloud administrator to add "allow_local_remote_servers" => true to config.php.',
			];
		} catch (\Throwable $e) {
			$this->logger->error('Failed to connect to Home Assistant: ' . $e->getMessage(), [
				'exception' => $e,
				'exception_class' => get_class($e),
				'url' => $sanitizedUrl,
				'timeout' => $connectionTimeout,
				'verify_ssl' => $verifySSL,
			]);
			return [
				'success' => false,
				'message' => 'Could not connect to Home Assistant. Please verify the URL is correct and the server is running and accessible.',
			];
		}
	}

	/**
	 * Fetch all person entities from Home Assistant
	 *
	 * @return array{success: bool, data?: array, error?: string}
	 */
	public function getPersonPresence(): array {
		$url = $this->appConfig->getValueString('nextcloudpresence', 'ha_url', '', lazy: true);
		$token = $this->appConfig->getValueString('nextcloudpresence', 'ha_token', '', lazy: true);

		$this->logger->debug('Fetching person presence from Home Assistant', [
			'url_configured' => !empty($url),
			'token_configured' => !empty($token),
		]);

		if (empty($url) || empty($token)) {
			$this->logger->warning('Home Assistant is not configured');
			return [
				'success' => false,
				'error' => 'Home Assistant is not configured',
			];
		}

		// Sanitize URL for logging throughout this method
		$sanitizedUrl = $this->sanitizeUrlForLogging($url);

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

		$this->logger->debug('Cache miss, fetching fresh person presence data', [
			'url' => $sanitizedUrl,
			'timeout' => $this->getConnectionTimeout(),
			'verify_ssl' => $this->getVerifySSL(),
		]);

		try {
			$client = $this->clientService->newClient();
			$response = $client->get($url . self::API_STATES_PATH, [
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
		} catch (\Throwable $e) {
			$this->logger->error('Failed to fetch person presence from Home Assistant: ' . $e->getMessage(), [
				'exception' => $e,
				'exception_class' => get_class($e),
				'url' => $sanitizedUrl,
				'timeout' => $this->getConnectionTimeout(),
				'verify_ssl' => $this->getVerifySSL(),
			]);
			return [
				'success' => false,
				'error' => 'Could not connect to Home Assistant. Please check your settings.',
			];
		}
	}
}
