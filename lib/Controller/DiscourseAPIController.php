<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Discourse\Controller;

use OCA\Discourse\AppInfo\Application;
use OCA\Discourse\Service\DiscourseAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;

use OCP\IConfig;
use OCP\IRequest;
use OCP\Security\ICrypto;

class DiscourseAPIController extends Controller {

	private string $accessToken;
	private string $clientID;
	private string $discourseUrl;
	private string $discourseUsername;

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		ICrypto $crypto,
		private DiscourseAPIService $discourseAPIService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
		$this->accessToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		if ($this->accessToken !== '') {
			$this->accessToken = $crypto->decrypt($this->accessToken);
		}
		$this->clientID = $this->config->getUserValue($this->userId, Application::APP_ID, 'client_id');
		if ($this->clientID !== '') {
			$this->clientID = $crypto->decrypt($this->clientID);
		}
		$this->discourseUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'url');
		$this->discourseUsername = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
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
		$avatar = $this->discourseAPIService->getDiscourseAvatar($this->discourseUrl, $this->accessToken, $username);
		$headers = [];
		if (isset($avatar['mime'])) {
			$headers['Content-Type'] = $avatar['mime'];
		}
		$response = new DataDisplayResponse($avatar['content'] ?? '', Http::STATUS_OK, $headers);
		$response->cacheFor(60 * 60 * 24);
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
