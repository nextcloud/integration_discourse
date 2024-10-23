<?php
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Discourse\Controller;

use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IL10N;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Discourse\AppInfo\Application;
use OCA\Discourse\Service\DiscourseAPIService;

require_once __DIR__ . '/../../vendor/autoload.php';

use OCP\PreConditionNotMetException;
use OCP\Security\ICrypto;
use phpseclib\Crypt\RSA;

class ConfigController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private ICrypto $crypto,
		private IURLGenerator $urlGenerator,
		private IL10N $l,
		private DiscourseAPIService $discourseAPIService,
		private ?string $userId
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
		if (isset($values['token']) && $values['token'] === '') {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', '');
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', '');
		}
		return new DataResponse(1);
	}

	/**
	 * set admin config values
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setAdminConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setAppValue(Application::APP_ID, $key, $value);
		}
		return new DataResponse(1);
	}

	/**
	 * receive oauth encrypted payload with protocol handler redirect
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $url
	 * @return RedirectResponse
	 */
	public function oauthProtocolRedirect(string $url = ''): RedirectResponse {
		if ($url === '') {
			$result = $this->l->t('Error during authentication exchanges');
			return new RedirectResponse(
				$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
				'?discourseToken=error&message=' . urlencode($result)
			);
		}
		$parts = parse_url($url);
		parse_str($parts['query'], $params);
		return $this->oauthRedirect($params['payload'] ?? '');
	}

	/**
	 * receive oauth encrypted payload
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $payload
	 * @return RedirectResponse
	 * @throws PreConditionNotMetException
	 */
	public function oauthRedirect(string $payload = ''): RedirectResponse {
		if ($payload === '') {
			$message = $this->l->t('Error during authentication exchanges');
			return new RedirectResponse(
				$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
				'?discourseToken=error&message=' . urlencode($message)
			);
		}
		$configNonce = $this->config->getUserValue($this->userId, Application::APP_ID, 'nonce');
		// decrypt payload
		$privKey = $this->config->getAppValue(Application::APP_ID, 'private_key');
		if ($privKey !== '') {
			$privKey = $this->crypto->decrypt($privKey);
		}
		$decPayload = base64_decode($payload);
		$rsa = new RSA();
		$rsa->setEncryptionMode(RSA::ENCRYPTION_PKCS1);
		$rsa->loadKey($privKey);
		$rsadec = $rsa->decrypt($decPayload);
		$payloadArray = json_decode($rsadec, true);

		// anyway, reset nonce
		$this->config->setUserValue($this->userId, Application::APP_ID, 'nonce', '');

		if (is_array($payloadArray) && $configNonce !== '' && $configNonce === $payloadArray['nonce']) {
			if (isset($payloadArray['key'])) {
				$accessToken = $payloadArray['key'];
				if ($accessToken !== '') {
					$accessToken = $this->crypto->encrypt($accessToken);
				}
				$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $accessToken);
				// get user info
				$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url');
				$info = $this->discourseAPIService->request($url, $accessToken, 'session/current.json', []);
				if (isset($info['current_user'], $info['current_user']['id'], $info['current_user']['username'])) {
					$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', $info['current_user']['id']);
					$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', $info['current_user']['username']);
				}
				return new RedirectResponse(
					$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
					'?discourseToken=success'
				);
			}
			$message = $this->l->t('No API key returned by Discourse');
		} else {
			$message = $this->l->t('Error during authentication exchanges');
		}
		return new RedirectResponse(
			$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
			'?discourseToken=error&message=' . urlencode($message)
		);
	}
}
