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

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use Psr\Log\LoggerInterface;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Discourse\Service\DiscourseAPIService;
use OCA\Discourse\AppInfo\Application;

class DiscourseAPIController extends Controller {


	private $userId;
	private $config;
	private $dbconnection;
	private $dbtype;

	public function __construct($AppName,
								IRequest $request,
								IServerContainer $serverContainer,
								IConfig $config,
								IL10N $l10n,
								IAppManager $appManager,
								IAppData $appData,
								LoggerInterface $logger,
								DiscourseAPIService $discourseAPIService,
								$userId) {
		parent::__construct($AppName, $request);
		$this->userId = $userId;
		$this->l10n = $l10n;
		$this->appData = $appData;
		$this->serverContainer = $serverContainer;
		$this->config = $config;
		$this->logger = $logger;
		$this->discourseAPIService = $discourseAPIService;
		$this->accessToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'token', '');
		$this->clientID = $this->config->getUserValue($this->userId, Application::APP_ID, 'client_id', '');
		$this->discourseUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', '');
		$this->discourseUsername = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name', '');
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getDiscourseUrl(): DataResponse {
		return new DataResponse($this->discourseUrl);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getDiscourseUsername(): DataResponse {
		return new DataResponse($this->discourseUsername);
	}

	/**
	 * get discourse user avatar
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $username
	 * @return DataDisplayResponse
	 */
	public function getDiscourseAvatar(string $username): DataDisplayResponse {
		$response = new DataDisplayResponse($this->discourseAPIService->getDiscourseAvatar($this->discourseUrl, $this->accessToken, $username));
		$response->cacheFor(60*60*24);
		return $response;
	}

	/**
	 * get todo list
	 * @NoAdminRequired
	 *
	 * @param string $since
	 * @return DataResponse
	 */
	public function getNotifications(string $since = ''): DataResponse {
		if ($this->accessToken === '' || $this->clientID === '' || !preg_match('/^(https?:\/\/)?[A-Za-z0-9]+\.[A-Za-z0-9].*/', $this->discourseUrl)) {
			return new DataResponse('', 400);
		}
		$result = $this->discourseAPIService->getNotifications($this->discourseUrl, $this->accessToken, $since);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, 401);
		}
		return $response;
	}

}
