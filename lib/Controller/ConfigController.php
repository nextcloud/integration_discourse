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

use OCP\App\IAppManager;
use OCP\Files\IAppData;
use OCP\AppFramework\Http\DataDisplayResponse;

use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IServerContainer;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\IDBConnection;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\Http\Client\IClientService;

use OCA\Discourse\AppInfo\Application;
use OCA\Discourse\Service\DiscourseAPIService;

require_once __DIR__ . '/../../vendor/autoload.php';
use phpseclib\Crypt\RSA;

class ConfigController extends Controller {


	private $userId;
	private $config;
	private $dbconnection;
	private $dbtype;

	public function __construct($AppName,
								IRequest $request,
								IServerContainer $serverContainer,
								IConfig $config,
								IAppManager $appManager,
								IAppData $appData,
								IDBConnection $dbconnection,
								IURLGenerator $urlGenerator,
								IL10N $l,
								LoggerInterface $logger,
								DiscourseAPIService $discourseAPIService,
								IClientService $clientService,
								$userId) {
		parent::__construct($AppName, $request);
		$this->l = $l;
		$this->userId = $userId;
		$this->appData = $appData;
		$this->serverContainer = $serverContainer;
		$this->config = $config;
		$this->dbconnection = $dbconnection;
		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
		$this->discourseAPIService = $discourseAPIService;
		$this->clientService = $clientService;
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
		$response = new DataResponse(1);
		return $response;
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
		$response = new DataResponse(1);
		return $response;
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
		$configNonce = $this->config->getUserValue($this->userId, Application::APP_ID, 'nonce', '');
		// decrypt payload
		$privKey = $this->config->getAppValue(Application::APP_ID, 'private_key', '');
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
				$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', '');
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
