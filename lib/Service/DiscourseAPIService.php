<?php
/**
 * Nextcloud - discourse
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Discourse\Service;

use Exception;
use OCA\Discourse\AppInfo\Application;
use OCP\Http\Client\IClient;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\Http\Client\IClientService;
use Throwable;

/**
 * Service to make requests to Discourse v3 (JSON) API
 */
class DiscourseAPIService {

	private IClient $client;

	public function __construct (string                  $appName,
								 private LoggerInterface $logger,
								 private IL10N           $l10n,
								 IClientService          $clientService) {
		$this->client = $clientService->newClient();
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param ?string $since
	 * @return array
	 */
	public function getNotifications(string $url, string $accessToken, ?string $since = null): array {
		$result = $this->request($url, $accessToken, 'notifications.json');
		if (isset($result['error'])) {
			return $result;
		}
		$notifications = [];
		if (isset($result['notifications']) && is_array($result['notifications'])) {
			foreach ($result['notifications'] as $notification) {
				$notifications[] = $notification;
			}
		}

		return $notifications;
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param string $term
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 */
	public function searchTopics(string $url, string $accessToken, string $term, int $offset = 0, int $limit = 5): array {
		$params = [
			'q' => $term,
		];
		$result = $this->request($url, $accessToken, 'search.json', $params);
		if (isset($result['error'])) {
			return $result;
		}
		$results = [];
		if (isset($result['topics']) && is_array($result['topics'])) {
			return array_slice($result['topics'], $offset, $limit);
		}

		return $results;
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param string $term
	 * @param int|null $offset
	 * @param int|null $limit
	 * @return array
	 */
	public function searchPosts(string $url, string $accessToken, string $term, ?int $offset = 0, ?int $limit = 5): array {
		$params = [
			'q' => $term,
		];
		$result = $this->request($url, $accessToken, 'search.json', $params);
		if (isset($result['error'])) {
			return $result;
		}
		$results = [];
		if (isset($result['posts']) && is_array($result['posts'])) {
			return array_slice($result['posts'], $offset, $limit);
		}

		return $results;
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param string $username
	 * @return array
	 */
	public function getDiscourseAvatar(string $url, string $accessToken, string $username): array {
		$result = $this->request($url, $accessToken, 'users/'.$username.'.json');
		if (isset($result['user']) && isset($result['user']['avatar_template'])) {
			$avatarUrl = $url . str_replace('{size}', '32', $result['user']['avatar_template']);
			try {
				$response = $this->client->get($avatarUrl);
				$avatar = ['content' => $response->getBody()];
				$ct = $response->getHeader('Content-Type');
				if ($ct) {
					if (is_array($ct) && count($ct) > 0) {
						$ct = $ct[0];
					}
					$avatar['mime'] = $ct;
				}
				return $avatar;
			} catch (Exception | Throwable $e) {
				$this->logger->warning('Discourse API error : '.$e->getMessage(), ['app' => Application::APP_ID]);
				return ['content' => ''];
			}
		}
		return [];
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function request(string $url, string $accessToken, string $endPoint, array $params = [], string $method = 'GET'): array {
		try {
			$url = $url . '/' . $endPoint;
			$options = [
				'headers' => [
					'User-Api-Key' => $accessToken,
					// optional
					//'User-Api-Client-Id' => $clientId,
					'User-Agent' => 'Nextcloud Discourse integration'
				],
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					// manage array parameters
					$paramsContent = '';
					foreach ($params as $key => $value) {
						if (is_array($value)) {
							foreach ($value as $oneArrayValue) {
								$paramsContent .= $key . '[]=' . urlencode($oneArrayValue) . '&';
							}
							unset($params[$key]);
						}
					}
					$paramsContent .= http_build_query($params);

					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = $params;
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				return json_decode($body, true);
			}
		} catch (Exception | Throwable $e) {
			$this->logger->warning('Discourse API error : '.$e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}
}
