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

use OCP\IL10N;
use OCP\ILogger;
use OCP\Http\Client\IClientService;

class DiscourseAPIService {

    private $l10n;
    private $logger;

    /**
     * Service to make requests to Discourse v3 (JSON) API
     */
    public function __construct (
        string $appName,
        ILogger $logger,
        IL10N $l10n,
        IClientService $clientService
    ) {
        $this->appName = $appName;
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->clientService = $clientService;
        $this->client = $clientService->newClient();
    }

    public function getTodos($url, $accessToken, $since = null) {
        $params = [
            'action' => ['assigned', 'mentioned', 'build_failed', 'marked', 'approval_required', 'unmergeable', 'directly_addressed'],
            'state' => 'pending',
        ];
        $result = $this->request($url, $accessToken, 'todos', $params);
        if (!is_array($result)) {
            return $result;
        }

        return $result;
    }

    public function getDiscourseAvatar($url) {
        return $this->client->get($url)->getBody();
    }

    public function request($url, $accessToken, $endPoint, $params = [], $method = 'GET') {
        try {
            $url = $url . '/' . $endPoint;
            $options = [
                'headers' => [
                    'Api-Key'  => $accessToken,
                    'Api-Username' => $accessUsername,
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
            }
            $body = $response->getBody();
            $respCode = $response->getStatusCode();

            if ($respCode >= 400) {
                return $this->l10n->t('Bad credentials');
            } else {
                return json_decode($body, true);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Discourse API error : '.$e, array('app' => $this->appName));
            return $e;
        }
    }

}
