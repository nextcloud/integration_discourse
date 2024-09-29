<?php
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Discourse\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\PreConditionNotMetException;
use OCP\Settings\ISettings;

use OCA\Discourse\AppInfo\Application;

require_once __DIR__ . '/../../vendor/autoload.php';
use phpseclib\Crypt\RSA;

class Personal implements ISettings {

	public function __construct(private IConfig $config,
								private IInitialState $initialStateService,
								private ?string $userId) {
	}

	/**
	 * @return TemplateResponse
	 * @throws PreConditionNotMetException
	 */
	public function getForm(): TemplateResponse {
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url');
		$userName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
		$navigationEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'navigation_enabled', '0');
		$searchTopicsEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_topics_enabled', '0');
		$searchPostsEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_posts_enabled', '0');

		// for OAuth
		$clientID = $this->config->getUserValue($this->userId, Application::APP_ID, 'client_id');
		$pubKey = $this->config->getAppValue(Application::APP_ID, 'public_key');
		$privKey = $this->config->getAppValue(Application::APP_ID, 'private_key');

		if ($clientID === '') {
			// random string of 32 chars length
			$permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
			$clientID = substr(str_shuffle($permitted_chars), 0, 32);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'client_id', $clientID);
		}
		if ($pubKey === '' || $privKey === '') {
			$rsa = new RSA();
			$rsa->setPrivateKeyFormat(RSA::PRIVATE_FORMAT_PKCS1);
			$rsa->setPublicKeyFormat(RSA::PUBLIC_FORMAT_PKCS1);
			$keys = $rsa->createKey(2048);
			$pubKey = $keys['publickey'];
			$privKey = $keys['privatekey'];

			$this->config->setAppValue(Application::APP_ID, 'public_key', $pubKey);
			$this->config->setAppValue(Application::APP_ID, 'private_key', $privKey);
		}

		$userConfig = [
			'token' => $token,
			'url' => $url,
			'client_id' => $clientID,
			'public_key' => $pubKey,
			'user_name' => $userName,
			'navigation_enabled' => ($navigationEnabled === '1'),
			'search_posts_enabled' => ($searchPostsEnabled === '1'),
			'search_topics_enabled' => ($searchTopicsEnabled === '1'),
		];
		$this->initialStateService->provideInitialState('user-config', $userConfig);
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
