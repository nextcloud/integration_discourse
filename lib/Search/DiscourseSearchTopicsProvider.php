<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Discourse\Search;

use OCA\Discourse\AppInfo\Application;
use OCA\Discourse\Service\DiscourseAPIService;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IExternalProvider;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;
use OCP\Security\ICrypto;

class DiscourseSearchTopicsProvider implements IProvider, IExternalProvider {

	public function __construct(
		private IAppManager $appManager,
		private IL10N $l10n,
		private IConfig $config,
		private ICrypto $crypto,
		private IURLGenerator $urlGenerator,
		private DiscourseAPIService $discourseAPIService,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'discourse-search-topic';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('Discourse topics');
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

		$discourseUrl = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'url');
		$accessToken = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'token');
		if ($accessToken !== '') {
			$accessToken = $this->crypto->decrypt($accessToken);
		}

		$searchTopicsEnabled = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'search_topics_enabled', '0') === '1';
		if ($accessToken === '' || !$searchTopicsEnabled) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$searchResults = $this->discourseAPIService->searchTopics($discourseUrl, $accessToken, $term, $offset, $limit);

		if (isset($searchResults['error'])) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$formattedResults = array_map(function (array $entry) use ($discourseUrl): SearchResultEntry {
			return new SearchResultEntry(
				$this->getThumbnailUrl($entry),
				$this->getMainText($entry),
				$this->getSubline($entry),
				$this->getLinkToDiscourse($entry, $discourseUrl),
				'icon-discourse-search-fallback',
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
		return $entry['title'];
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getSubline(array $entry): string {
		return '#' . $entry['id'] . ' (' . $entry['posts_count'] . ' ' . $this->l10n->t('posts') . ')';
	}

	/**
	 * @param array $entry
	 * @param string $url
	 * @return string
	 */
	protected function getLinkToDiscourse(array $entry, string $url): string {
		return $url . '/t/' . $entry['slug'] . '/' . $entry['id'];
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getThumbnailUrl(array $entry): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);
	}

	public function isExternalProvider(): bool {
		return true;
	}
}
