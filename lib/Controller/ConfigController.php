<?php
/**
 * Nextcloud - discourse
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Discourse\Controller;

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
use phpseclib\Crypt\RSA;

class ConfigController extends Controller {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;
	/**
	 * @var IL10N
	 */
	private $l;
	/**
	 * @var DiscourseAPIService
	 */
	private $discourseAPIService;
	/**
	 * @var string|null
	 */
	private $userId;

	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								IURLGenerator $urlGenerator,
								IL10N $l,
								DiscourseAPIService $discourseAPIService,
								?string $userId) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->l = $l;
		$this->discourseAPIService = $discourseAPIService;
		$this->userId = $userId;
	}

	/**
	 * set config values
	 * @NoAdminRequired
	 *
	 * @param array $values
	 * @return DataResponse
	 */
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
