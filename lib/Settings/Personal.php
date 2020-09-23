<?php
namespace OCA\Discourse\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\Util;
use OCP\IURLGenerator;
use OCP\IInitialStateService;

use OCA\Discourse\AppInfo\Application;

require_once __DIR__ . '/../../vendor/autoload.php';
use phpseclib\Crypt\RSA;

class Personal implements ISettings {

	private $request;
	private $config;
	private $dataDirPath;
	private $urlGenerator;
	private $l;

	public function __construct(
						string $appName,
						IL10N $l,
						IRequest $request,
						IConfig $config,
						IURLGenerator $urlGenerator,
						IInitialStateService $initialStateService,
						$userId) {
		$this->appName = $appName;
		$this->urlGenerator = $urlGenerator;
		$this->request = $request;
		$this->l = $l;
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->userId = $userId;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm() {
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token', '');
		$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', '');
		$userName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name', '');
		$searchEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_enabled', '0');

		// for OAuth
		$clientID = $this->config->getUserValue($this->userId, Application::APP_ID, 'client_id', '');
		$pubKey = $this->config->getAppValue(Application::APP_ID, 'public_key', '');
		$privKey = $this->config->getAppValue(Application::APP_ID, 'private_key', '');

		if ($clientID === '') {
			// random string of 32 chars length
			$permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
			$clientID = substr(str_shuffle($permitted_chars), 0, 32);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'client_id', $clientID);
		}
		if ($pubKey === '' or $privKey === '') {
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
			'search_enabled' => ($searchEnabled === '1'),
		];
		$this->initialStateService->provideInitialState($this->appName, 'user-config', $userConfig);
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection() {
		return 'connected-accounts';
	}

	public function getPriority() {
		return 10;
	}
}
