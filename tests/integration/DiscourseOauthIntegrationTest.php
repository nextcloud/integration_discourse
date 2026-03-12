<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Discourse\Tests\Integration;

use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use OCA\Discourse\AppInfo\Application;
use OCA\Discourse\Controller\ConfigController;
use OCA\Discourse\Service\DiscourseAPIService;
use OCA\Discourse\Settings\Personal;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ICrypto;
use OCP\Server;
use Test\TestCase;

/**
 * @group DB
 */
class DiscourseOauthIntegrationTest extends TestCase {

	private ConfigController $configController;
	private ?string $discourseUrl;
	private ?string $discourseLogin;
	private ?string $discoursePassword;
	private Client $client;
	private CookieJar $cookieJar;
	private IConfig $config;
	private IAppConfig $appConfig;
	private ICrypto $crypto;
	private DiscourseAPIService $discourseAPIService;
	private Personal $personalSettings;
	private ?string $userId;

	private function resetUserConfig(string $userId): void {
		foreach ([
			'token',
			'url',
			'user_id',
			'user_name',
			'nonce',
			'client_id',
			'navigation_enabled',
			'search_topics_enabled',
			'search_posts_enabled',
		] as $key) {
			$this->config->deleteUserValue($userId, Application::APP_ID, $key);
		}
	}

	protected function setUp(): void {
		parent::setUp();

		$appManager = Server::get(IAppManager::class);
		$appManager->enableApp(Application::APP_ID);

		$this->discourseUrl = getenv('DISCOURSE_URL') ?: null;
		$this->discourseLogin = getenv('DISCOURSE_USER_LOGIN') ?: null;
		$this->discoursePassword = getenv('DISCOURSE_USER_PASSWORD') ?: null;

		$userManager = Server::get(IUserManager::class);
		$user = $userManager->get('discourse_test_user')
			?? $userManager->createUser('discourse_test_user', 'test-password');
		self::loginAsUser($user->getUID());

		$this->config = Server::get(IConfig::class);
		$this->resetUserConfig($user->getUID());

		$this->configController = Server::get(ConfigController::class);
		$this->appConfig = Server::get(IAppConfig::class);
		$this->crypto = Server::get(ICrypto::class);
		$this->discourseAPIService = Server::get(DiscourseAPIService::class);
		$this->personalSettings = Server::get(Personal::class);
		$this->userId = Server::get(IUserSession::class)->getUser()?->getUID();
		$this->newClient();
	}

	private function newClient() {
		$this->cookieJar = new CookieJar();
		$this->client = new Client(['allow_redirects' => ['track_redirects' => true], 'cookies' => $this->cookieJar]);
		return $this->client;
	}

	private function requireCredentials(): void {
		if ($this->discourseUrl === null || $this->discourseLogin === null || $this->discoursePassword === null) {
			$this->markTestSkipped('DISCOURSE_URL and/or DISCOURSE_USER_LOGIN and/or DISCOURSE_USER_PASSWORD not set');
		}
		if ($this->userId === null || $this->userId === '') {
			$this->markTestSkipped('No Nextcloud user is available for the OAuth integration test');
		}
	}

	private function makeNonce(int $length): string {
		$charset = '0123456789ABCDEFGHIJKLMNOPQRSTUVXYZabcdefghijklmnopqrstuvwxyz-._~';
		$nonce = '';
		for ($i = 0; $i < $length; $i++) {
			$nonce .= $charset[random_int(0, strlen($charset) - 1)];
		}
		return $nonce;
	}

	private function resolveDiscourseUrl(string $url): string {
		if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
			return $url;
		}

		if (str_starts_with($url, '/')) {
			return rtrim($this->discourseUrl, '/') . $url;
		}

		return rtrim($this->discourseUrl, '/') . '/' . ltrim($url, '/');
	}

	private function getOauthStartUrl(): string {
		$this->personalSettings->getForm();

		$clientId = $this->config->getUserValue($this->userId, Application::APP_ID, 'client_id');
		if ($clientId !== '') {
			$clientId = $this->crypto->decrypt($clientId);
		}
		$publicKey = $this->appConfig->getValueString(Application::APP_ID, 'public_key', lazy: true);
		$nonce = $this->makeNonce(16);
		$this->configController->setConfig(['nonce' => $nonce]);

		$query = http_build_query([
			'client_id' => $clientId,
			'auth_redirect' => 'web+nextclouddiscourse://auth-redirect',
			'application_name' => 'Nextclouddiscourseintegration',
			'nonce' => $nonce,
			'public_key' => $publicKey,
			'scopes' => 'read,write,notifications',
		], arg_separator: '&', encoding_type: PHP_QUERY_RFC3986);

		return $this->discourseUrl . '/user-api-key/new?' . $query;
	}

	private function getFinalResponseUrl($response, string $fallbackUrl): string {
		$redirectHistory = $response->getHeader('X-Guzzle-Redirect-History');
		if ($redirectHistory !== []) {
			return end($redirectHistory);
		}

		return $fallbackUrl;
	}

	/**
	 * When submitting the login form in a browser, a first GET to /session/csrf is done
	 * to get the csrf token in a JSON payload
	 * Then a request to /session is done which sets a few cookies
	 * Then a POST to /login returns a 302 that redirects to /user-api-key/new which is now the "authorize" page with a confirmation button
	 */
	private function loginToDiscourse(string $oauthStartUrl, string $username, string $password) {
		// load the page that is actually requested in real life by the browser in case we get some cookies from it
		$this->client->get($oauthStartUrl);

		// get the CSRF token
		$csrfUrl = $this->discourseUrl . '/session/csrf';
		$csrfResponse = $this->client->get($csrfUrl, [
			'headers' => [
				'X-Requested-With' => 'XMLHttpRequest',
			],
		]);
		$csrfStatusCode = $csrfResponse->getStatusCode();
		$this->assertSame(Http::STATUS_OK, $csrfStatusCode, 'the request to /session/csrf has failed');
		$body = $csrfResponse->getBody()->getContents();
		$decodedBody = json_decode($body, true);
		$this->assertIsArray($decodedBody, 'the decoded /session/csrf response body is not an array: ' . $body);
		$this->assertArrayHasKey('csrf', $decodedBody);
		$csrfToken = $decodedBody['csrf'];


		$sessionUrl = $this->discourseUrl . '/session';
		$sessionResponse = $this->client->post($sessionUrl, [
			'headers' => [
				'X-CSRF-Token' => $csrfToken,
				'X-Requested-With' => 'XMLHttpRequest',
			],
			'form_params' => [
				'login' => $username,
				'password' => $password,
				'second_factor_method' => '1',
				'timezone' => 'Europe/Paris',
			],
		]);
		$sessionStatusCode = $sessionResponse->getStatusCode();
		$this->assertSame(Http::STATUS_OK, $sessionStatusCode, 'the request to /session has failed');

		$currentSessionResponse = $this->client->get($this->discourseUrl . '/session/current.json');
		$currentSessionStatusCode = $currentSessionResponse->getStatusCode();
		$currentSessionBody = $currentSessionResponse->getBody()->getContents();
		$this->assertSame(
			Http::STATUS_OK,
			$currentSessionStatusCode,
			'login via /session did not create an authenticated session, /session/current.json response: ' . $currentSessionBody,
		);
		$currentSessionData = json_decode($currentSessionBody, true);
		$this->assertIsArray($currentSessionData, 'the decoded /session/current.json response body is not an array: ' . $currentSessionBody);
		$this->assertArrayHasKey('current_user', $currentSessionData, 'no current_user found in /session/current.json response: ' . $currentSessionBody);

		// post to login
		$loginUrl = $this->discourseUrl . '/login';
		$redirect = str_replace($this->discourseUrl, '', $oauthStartUrl);
		return $this->client->post($loginUrl, [
			'form_params' => [
				'username' => $username,
				'password' => $password,
				// this is the initial URL that NC redirect the user to, but relative
				'redirect' => $redirect,
			],
		]);
	}

	/**
	 * The authorize page contains a form with a lot of hidden inputs and a submit button
	 */
	private function submitAuthorizePage($loginResponse, string $authorizePageUrl) {
		$doc = new DOMDocument();
		$doc->loadHtml($loginResponse->getBody()->getContents());
		$selector = new DOMXpath($doc);
		$result = $selector->query('//form');
		$form = $result->item(0);
		$this->assertNotNull($form, 'Authorize form not found on page: ' . $authorizePageUrl);
		$formAction = $form->getAttribute('action');
		$formActionUrl = $formAction === '' ? $authorizePageUrl : $this->resolveDiscourseUrl($formAction);
		$formParams = [];
		$hiddenInputs = $selector->query('.//input[@type="hidden" and @name]', $form);
		foreach ($hiddenInputs as $hiddenInput) {
			$formParams[$hiddenInput->getAttribute('name')] = $hiddenInput->getAttribute('value');
		}
		$this->assertNotEmpty($hiddenInputs, 'hiddenInputs should not be empty');
		$this->assertArrayHasKey('authenticity_token', $formParams);
		$this->assertArrayHasKey('nonce', $formParams);
		$this->assertArrayHasKey('client_id', $formParams);
		$this->assertArrayHasKey('auth_redirect', $formParams);
		$this->assertArrayHasKey('application_name', $formParams);
		$this->assertArrayHasKey('public_key', $formParams);

		$submitButton = $selector->query('.//input[@type="submit" and @name="commit"]', $form)?->item(0);
		$submitValue = $submitButton?->getAttribute('value') ?? 'Authorize';
		$formParams['commit'] = $submitValue;
		libxml_clear_errors();

		return $this->client->post($formActionUrl, [
			// we need the custom protocol redirect URL so we don't allow redirects for this request
			'allow_redirects' => false,
			'form_params' => $formParams,
		]);
	}

	public function testOauthLogin(): void {
		$this->requireCredentials();

		$this->configController->setConfig(['url' => $this->discourseUrl]);

		// make request to authorize page with the same GET parameters as we do in the personal settings
		$oauthStartUrl = $this->getOauthStartUrl();
		// submit the discourse login form with the user login (login-account-name) and password (login-account-password)
		$loginResponse = $this->loginToDiscourse($oauthStartUrl, $this->discourseLogin, $this->discoursePassword);
		$status = $loginResponse->getStatusCode();
		self::assertSame(Http::STATUS_OK, $status);
		$authorizePageUrl = $this->getFinalResponseUrl($loginResponse, $oauthStartUrl);

		// in the new loaded page, submit the authorize form
		$authorizeResponse = $this->submitAuthorizePage($loginResponse, $authorizePageUrl);
		$status = $authorizeResponse->getStatusCode();
		self::assertSame(Http::STATUS_FOUND, $status);
		$customProtocolLocation = $authorizeResponse->getHeaderLine('location');

		// redirected to the custom protocol URL, call the controller method
		$oauthRedirectResponse = $this->configController->oauthProtocolRedirect($customProtocolLocation);

		// check that we now have a token
		$this->assertStringContainsString('discourseToken=success', $oauthRedirectResponse->getRedirectURL());

		$storedToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$this->assertNotSame('', $storedToken);

		$accessToken = $this->crypto->decrypt($storedToken);
		$userInfo = $this->discourseAPIService->request($this->discourseUrl, $accessToken, 'session/current.json');
		$this->assertArrayNotHasKey('error', $userInfo);
		$this->assertArrayHasKey('current_user', $userInfo);
		$this->assertArrayHasKey('id', $userInfo['current_user']);
		$this->assertArrayHasKey('username', $userInfo['current_user']);
	}
}
