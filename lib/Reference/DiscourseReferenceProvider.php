<?php
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Discourse\Reference;

use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\Discourse\AppInfo\Application;
use OCP\Collaboration\Reference\IReference;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;

class DiscourseReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider {

	private const RICH_OBJECT_TOPIC_TYPE = Application::APP_ID . '_topic';
	private const RICH_OBJECT_POST_TYPE = Application::APP_ID . '_post';

	public function __construct(private IConfig $config,
								private IL10N $l10n,
								private IURLGenerator $urlGenerator,
								private ReferenceManager $referenceManager,
								private ?string $userId) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string	{
		return 'discourse-topics-posts';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Discourse topics and posts');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int	{
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getSupportedSearchProviderIds(): array {
		if ($this->userId !== null) {
			$ids = [];
			$searchTopicsEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_topics_enabled', '0') === '1';
			$searchPostsEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_posts_enabled', '0') === '1';
			if ($searchPostsEnabled) {
				$ids[] = 'discourse-search-post';
			}
			if ($searchTopicsEnabled) {
				$ids[] = 'discourse-search-topic';
			}
			return $ids;
		}
		return ['discourse-search-post', 'discourse-search-topic'];
	}

	/**
	 * @inheritDoc
	 */
	public function matchReference(string $referenceText): bool {
		if ($this->userId !== null) {
			$linkPreviewEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'link_preview_enabled', '1') === '1';
			if (!$linkPreviewEnabled) {
				return false;
			}
		}
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		//if ($this->matchReference($referenceText)) {
		//}

		return null;
	}

	/**
	 * We use the userId here because when connecting/disconnecting from the GitHub account,
	 * we want to invalidate all the user cache and this is only possible with the cache prefix
	 * @inheritDoc
	 */
	public function getCachePrefix(string $referenceId): string {
		return $this->userId ?? '';
	}

	/**
	 * We don't use the userId here but rather a reference unique id
	 * @inheritDoc
	 */
	public function getCacheKey(string $referenceId): ?string {
		return $referenceId;
	}

	/**
	 * @param string $userId
	 * @return void
	 */
	public function invalidateUserCache(string $userId): void {
		$this->referenceManager->invalidateCache($userId);
	}
}
