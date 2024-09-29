<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Discourse\Search;

use OCA\Discourse\Service\DiscourseAPIService;
use OCA\Discourse\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class DiscourseSearchPostsProvider implements IProvider {

	public function __construct(private IAppManager         $appManager,
								private IL10N               $l10n,
								private IConfig             $config,
								private IURLGenerator       $urlGenerator,
								private DiscourseAPIService $discourseAPIService) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'discourse-search-post';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('Discourse posts');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if (strpos($route, Application::APP_ID . '.') === 0) {
			// Active app, prefer Discourse results
			return -1;
		}

		return 20;
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if (!$this->appManager->isEnabledForUser(Application::APP_ID, $user)) {
			return SearchResult::complete($this->getName(), []);
		}

		$limit = $query->getLimit();
		$term = $query->getTerm();
		$offset = $query->getCursor();
		$offset = $offset ? intval($offset) : 0;

		$theme = $this->config->getUserValue($user->getUID(), 'accessibility', 'theme');
		$thumbnailUrl = ($theme === 'dark')
			? $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
			: $this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg');

		$discourseUrl = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'url');
		$accessToken = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'token');

		$searchPostsEnabled = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'search_posts_enabled', '0') === '1';
		if ($accessToken === '' || !$searchPostsEnabled) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$searchResults = $this->discourseAPIService->searchPosts($discourseUrl, $accessToken, $term, $offset, $limit);

		if (isset($searchResults['error'])) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$formattedResults = array_map(function (array $entry) use ($thumbnailUrl, $discourseUrl): SearchResultEntry {
			return new SearchResultEntry(
				$this->getThumbnailUrl($entry, $thumbnailUrl),
				$this->getMainText($entry),
				$this->getSubline($entry),
				$this->getLinkToDiscourse($entry, $discourseUrl),
				'',
				true
			);
		}, $searchResults);

		return SearchResult::paginated(
			$this->getName(),
			$formattedResults,
			$offset + $limit
		);
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getMainText(array $entry): string {
		return $entry['blurb'] ?? $entry['username'];
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getSubline(array $entry): string {
		return '#' . $entry['topic_id'];
	}

	/**
	 * @param array $entry
	 * @param string $url
	 * @return string
	 */
	protected function getLinkToDiscourse(array $entry, string $url): string {
		return $url . '/t/dum/' . $entry['topic_id'];
	}

	/**
	 * @param array $entry
	 * @param string $thumbnailUrl
	 * @return string
	 */
	protected function getThumbnailUrl(array $entry, string $thumbnailUrl): string {
		return isset($entry['username'])
			? $this->urlGenerator->linkToRouteAbsolute('integration_discourse.discourseAPI.getDiscourseAvatar', ['username' => $entry['username']])
			: $thumbnailUrl;
	}
}
