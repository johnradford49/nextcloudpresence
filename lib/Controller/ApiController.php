<?php

namespace OCA\NextcloudPresence\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IConfig;
use OCP\Http\Client\IClientService;

class ApiController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IClientService $clientService,
		private \OCA\NextcloudPresence\Service\HAService $haService,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/syncTables')]
	public function syncTables(): DataResponse {
		$tableId = (int)$this->config->getAppValue($this->appName, 'tables_table_id', '0');
		if ($tableId <= 0) {
			return new DataResponse(['error' => 'tables_table_id not configured'], 400);
		}

		$persons = $this->haService->getPersonPresence(); // existing source of truth

		$client = $this->clientService->newClient();
		$base = rtrim($this->request->getServerProtocol() ? '' : '', ''); // not used; see note below

		// IMPORTANT NOTE:
		// From server-side PHP, you must call your own Nextcloud via an absolute URL.
		// Build it from the request, e.g. https://your.host
		$scheme = $this->request->getServerProtocol() === 'https' ? 'https' : 'http';
		$host = $this->request->getServerHost();
		$ncBase = $scheme . '://' . $host;

		// Because we’re doing option (2), we need to forward the logged-in user’s session.
		// The simplest approach is: DON'T do server-to-server calls; instead do the Tables calls in the browser (see section B).
		// If you insist on server-side, you need to authenticate as the current user, which is not trivial without an app password.
		return new DataResponse([
			'error' => 'Recommended approach for option (2): perform Tables API calls from the frontend using the user session. See section B.',
			'persons' => $persons,
		], 501);
	}
}
