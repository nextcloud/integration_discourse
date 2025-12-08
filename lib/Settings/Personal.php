<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Discourse\Settings;

use OCA\Discourse\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\PreConditionNotMetException;
use OCP\Security\ICrypto;

use OCP\Settings\ISettings;

require_once __DIR__ . '/../../vendor/autoload.php';
use phpseclib\Crypt\RSA;

class Personal implements ISettings {

	public function __construct(
		private IConfig $config,
		private IAppConfig $appConfig,
		private ICrypto $crypto,
		private IInitialState $initialStateService,
		private ?string $userId,
	) {
	}

	/**
	 * @return TemplateResponse
	 * @throws PreConditionNotMetException
	 */
	public function getForm(): TemplateResponse {
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		if ($token !== '') {
			$token = $this->crypto->decrypt($token);
		}
		$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url');
		$userName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
		$navigationEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'navigation_enabled', '0');
		$searchTopicsEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_topics_enabled', '0');
		$searchPostsEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_posts_enabled', '0');

		// for OAuth
		$clientID = $this->config->getUserValue($this->userId, Application::APP_ID, 'client_id');
		if ($clientID !== '') {
			$clientID = $this->crypto->decrypt($clientID);
		}
		$pubKey = $this->appConfig->getValueString(Application::APP_ID, 'public_key', lazy: true);
		$privKey = $this->appConfig->getValueString(Application::APP_ID, 'private_key', lazy: true);

		if ($clientID === '') {
			// random string of 32 chars length
			$permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
			$clientID = $this->crypto->encrypt(substr(str_shuffle($permitted_chars), 0, 32));
			$this->config->setUserValue($this->userId, Application::APP_ID, 'client_id', $clientID);
		}
		if ($pubKey === '' || $privKey === '') {
			$rsa = new RSA();
			$rsa->setPrivateKeyFormat(RSA::PRIVATE_FORMAT_PKCS1);
			$rsa->setPublicKeyFormat(RSA::PUBLIC_FORMAT_PKCS1);
			$keys = $rsa->createKey(2048);
			$pubKey = $keys['publickey'];
			$privKey = $this->crypto->encrypt($keys['privatekey']);

			$this->appConfig->setValueString(Application::APP_ID, 'public_key', $pubKey, lazy: true);
			$this->appConfig->setValueString(Application::APP_ID, 'private_key', $privKey, lazy: true, sensitive: true);
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
