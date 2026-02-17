<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\NextcloudPresence\AppInfo\Application::APP_ID, OCA\NextcloudPresence\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\NextcloudPresence\AppInfo\Application::APP_ID, OCA\NextcloudPresence\AppInfo\Application::APP_ID . '-main');

?>

<div id="nextcloudpresence"></div>
