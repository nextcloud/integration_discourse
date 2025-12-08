<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Discourse\Migration;

use Closure;
use OCA\Discourse\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\Security\ICrypto;

class Version030200Date20251208110814 extends SimpleMigrationStep {

	public function __construct(
		private ICrypto $crypto,
		private IConfig $config,
		private IAppConfig $appConfig,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return null|ISchemaWrapper
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		// migrate (already manually encrypted) private_key: make it lazy and sensitive
		$privKey = $this->config->getAppValue(Application::APP_ID, 'private_key');
		if ($privKey !== '') {
			$privKey = $this->crypto->decrypt($privKey);
			$this->appConfig->setValueString(Application::APP_ID, 'private_key', $privKey, lazy: true, sensitive: true);
		}

		$pubKey = $this->config->getAppValue(Application::APP_ID, 'public_key');
		if ($pubKey !== '') {
			$this->appConfig->setValueString(Application::APP_ID, 'public_key', $pubKey, lazy: true);
		}
	}
}
