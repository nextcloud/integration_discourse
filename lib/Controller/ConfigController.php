<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Discourse\Controller;

use OCA\Discourse\AppInfo\Application;
use OCA\Discourse\Service\DiscourseAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PasswordConfirmationRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IL10N;

use OCP\IRequest;
use OCP\IURLGenerator;

require_once __DIR__ . '/../../vendor/autoload.php';

use OCP\PreConditionNotMetException;
use OCP\Security\ICrypto;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\RSA\PrivateKey as RSAPrivateKey;

class ConfigController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IAppConfig $appConfig,
		private ICrypto $crypto,
		private IURLGenerator $urlGenerator,
		private IL10N $l,
		private DiscourseAPIService $discourseAPIService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * set config values
	 *
	 * @param array $values
	 * @return DataResponse
	 * @throws PreConditionNotMetException
	 */
	#[NoAdminRequired]
	public function setConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}
		return new DataResponse(1);
	}

	/**
	 * @throws PreConditionNotMetException
	 */
	#[NoAdminRequired]
	#[PasswordConfirmationRequired]
	public function setSensitiveConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}
		if (isset($values['token']) && $values['token'] === '') {
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_id');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_name');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'token');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'nonce');
		}
		return new DataResponse('');
	}

	/**
	 * receive oauth encrypted payload with protocol handler redirect
	 *
	 * @param string $url
	 * @return RedirectResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function oauthProtocolRedirect(string $url = ''): RedirectResponse {
		if ($url === '') {
			$result = $this->l->t('Error during authentication exchanges');
			return new RedirectResponse(
				$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts'])
				. '?discourseToken=error&message=' . urlencode($result)
			);
		}
		$queryParams = parse_url($url, PHP_URL_QUERY);
		parse_str($queryParams, $paramsArray);
		return $this->oauthRedirect($paramsArray['payload'] ?? '');
	}

	/**
	 * receive oauth encrypted payload
	 *
	 * @param string $payload
	 * @return RedirectResponse
	 * @throws PreConditionNotMetException
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function oauthRedirect(string $payload = ''): RedirectResponse {
		if ($payload === '') {
			$message = $this->l->t('Error during authentication exchanges');
			return new RedirectResponse(
				$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts'])
				. '?discourseToken=error&message=' . urlencode($message)
			);
		}
		$configNonce = $this->config->getUserValue($this->userId, Application::APP_ID, 'nonce');
		$privKey = $this->appConfig->getValueString(Application::APP_ID, 'private_key', lazy: true);
		$decPayload = base64_decode($payload, true);
		if ($decPayload === false) {
			$message = $this->l->t('Error during authentication exchanges');
			return new RedirectResponse(
				$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts'])
				. '?discourseToken=error&message=' . urlencode($message)
			);
		}
		$loadedKey = PublicKeyLoader::loadPrivateKey($privKey);
		if (!$loadedKey instanceof RSAPrivateKey) {
			$message = $this->l->t('Error during authentication exchanges');
			return new RedirectResponse(
				$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts'])
				. '?discourseToken=error&message=' . urlencode($message)
			);
		}
		$privateKey = $loadedKey->withPadding(RSA::ENCRYPTION_PKCS1);
		$rsadec = $privateKey->decrypt($decPayload);
		$payloadArray = json_decode($rsadec, true);

		// anyway, reset nonce
		$this->config->deleteUserValue($this->userId, Application::APP_ID, 'nonce');

		if (is_array($payloadArray) && $configNonce !== '' && $configNonce === $payloadArray['nonce']) {
			if (isset($payloadArray['key'])) {
				$accessToken = $payloadArray['key'];
				if ($accessToken !== '') {
					$encryptedAccessToken = $this->crypto->encrypt($accessToken);
					$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $encryptedAccessToken);
				}
				// get user info
				$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url');
				$info = $this->discourseAPIService->request($url, $accessToken, 'session/current.json', []);
				if (isset($info['current_user'], $info['current_user']['id'], $info['current_user']['username'])) {
					$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', $info['current_user']['id']);
					$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', $info['current_user']['username']);
				}
				return new RedirectResponse(
					$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts'])
					. '?discourseToken=success'
				);
			}
			$message = $this->l->t('No API key returned by Discourse');
		} else {
			$message = $this->l->t('Error during authentication exchanges');
		}
		return new RedirectResponse(
			$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts'])
			. '?discourseToken=error&message=' . urlencode($message)
		);
	}
}
