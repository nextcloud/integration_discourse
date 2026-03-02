<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Discourse\Tests\Integration;

use OCA\Discourse\Service\DiscourseAPIService;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

/**
 * Notification type constants matching src/views/Dashboard.vue TYPES
 */
const TYPES_MENTION = 1;
const TYPES_REPLY = 2;
const TYPES_QUOTED = 3;
const TYPES_EDIT = 4;
const TYPES_LIKE = 5;
const TYPES_PRIVATE_MESSAGE = 6;
const TYPES_REPLY_2 = 9;
const TYPES_LINKED = 11;
const TYPES_BADGE_EARNED = 12;
const TYPES_SOLVED = 14;
const TYPES_GROUP_MENTION = 15;
const TYPES_MODERATOR_OR_ADMIN_INBOX = 16;

/**
 * All notification types handled by the Dashboard widget
 */
const DASHBOARD_TYPES = [
	TYPES_MENTION,
	TYPES_REPLY,
	TYPES_QUOTED,
	TYPES_EDIT,
	TYPES_LIKE,
	TYPES_PRIVATE_MESSAGE,
	TYPES_REPLY_2,
	TYPES_LINKED,
	TYPES_SOLVED,
	TYPES_GROUP_MENTION,
	TYPES_MODERATOR_OR_ADMIN_INBOX,
];

#[Group('DB')]
class DiscourseAPIServiceIntegrationTest extends TestCase {

	private DiscourseAPIService $service;
	private ?string $discourseUrl;
	private ?string $discourseToken;

	protected function setUp(): void {
		parent::setUp();

		$this->discourseUrl = getenv('DISCOURSE_URL') ?: null;
		$this->discourseToken = getenv('DISCOURSE_TOKEN') ?: null;

		$this->service = Server::get(DiscourseAPIService::class);
	}

	private function requireCredentials(): void {
		if ($this->discourseUrl === null || $this->discourseToken === null) {
			$this->markTestSkipped('DISCOURSE_URL and/or DISCOURSE_TOKEN not set');
		}
	}

	/**
	 * Test that getNotifications returns a valid array and that every notification
	 * has the fields required by the Dashboard.vue computed properties and methods
	 * so that rendering won't crash.
	 */
	public function testGetNotifications(): void {
		$this->requireCredentials();

		$result = $this->service->getNotifications($this->discourseUrl, $this->discourseToken);

		$this->assertIsArray($result);
		$this->assertArrayNotHasKey('error', $result);

		// echo json_encode($result, JSON_PRETTY_PRINT);

		$this->assertGreaterThan(0, count($result), 'test data requires at least one notification');

		foreach ($result as $i => $n) {
			$prefix = "notification[$i] (id=" . ($n['id'] ?? '?') . ', type=' . ($n['notification_type'] ?? '?') . ')';

			// Fields accessed by every notification in Dashboard.vue:
			// getUniqueKey -> n.id
			// filter -> n.read, n.notification_type
			// getTargetTitle -> n.fancy_title (fallback)
			// getFormattedDate -> n.created_at
			// getNotificationTarget -> n.slug
			// items computed -> all above methods
			$this->assertArrayHasKey('id', $n, "$prefix: missing 'id'");
			$this->assertArrayHasKey('notification_type', $n, "$prefix: missing 'notification_type'");
			$this->assertIsInt($n['notification_type'], "$prefix: 'notification_type' should be int");
			$this->assertArrayHasKey('read', $n, "$prefix: missing 'read'");
			$this->assertArrayHasKey('created_at', $n, "$prefix: missing 'created_at'");
			$this->assertIsString($n['created_at'], "$prefix: 'created_at' should be string");
			$this->assertArrayHasKey('slug', $n, "$prefix: missing 'slug'");

			$type = $n['notification_type'];

			// Only validate type-specific fields for types the Dashboard actually handles.
			// Other types are filtered out and never rendered.
			if (!in_array($type, DASHBOARD_TYPES)) {
				continue;
			}

			// getTargetTitle accesses n.fancy_title as a fallback for all non-MODERATOR_OR_ADMIN_INBOX types
			if ($type !== TYPES_MODERATOR_OR_ADMIN_INBOX) {
				$this->assertArrayHasKey('fancy_title', $n, "$prefix: missing 'fancy_title'");
			}

			// n.data is accessed by virtually every method for handled types
			$this->assertArrayHasKey('data', $n, "$prefix: missing 'data'");
			$this->assertIsArray($n['data'], "$prefix: 'data' should be an array");
			$data = $n['data'];

			switch ($type) {
				case TYPES_MENTION:
				case TYPES_PRIVATE_MESSAGE:
					// getNotificationTarget -> n.slug, n.topic_id
					$this->assertArrayHasKey('topic_id', $n, "$prefix: missing 'topic_id'");
					// getNotificationImage -> n.data.original_username
					$this->assertArrayHasKey('original_username', $data, "$prefix: missing 'data.original_username'");
					// getDisplayAndOriginalUsername (via getSubline) -> n.data.display_username, n.data.original_username
					$this->assertArrayHasKey('display_username', $data, "$prefix: missing 'data.display_username'");
					break;

				case TYPES_REPLY:
				case TYPES_REPLY_2:
				case TYPES_LIKE:
					// getNotificationTarget -> n.slug, n.topic_id, n.post_number
					$this->assertArrayHasKey('topic_id', $n, "$prefix: missing 'topic_id'");
					$this->assertArrayHasKey('post_number', $n, "$prefix: missing 'post_number'");
					// getNotificationImage -> n.data.original_username
					$this->assertArrayHasKey('original_username', $data, "$prefix: missing 'data.original_username'");
					// getDisplayAndOriginalUsername (via getSubline) -> n.data.display_username
					$this->assertArrayHasKey('display_username', $data, "$prefix: missing 'data.display_username'");
					break;

				case TYPES_SOLVED:
					// getNotificationTarget -> n.slug, n.topic_id, n.post_number
					$this->assertArrayHasKey('topic_id', $n, "$prefix: missing 'topic_id'");
					$this->assertArrayHasKey('post_number', $n, "$prefix: missing 'post_number'");
					// getNotificationImage -> n.data.display_username
					$this->assertArrayHasKey('display_username', $data, "$prefix: missing 'data.display_username'");
					// getSubline -> n.data.display_username (already checked above)
					break;

				case TYPES_BADGE_EARNED:
					// getNotificationTarget -> n.data.badge_id, n.data.badge_slug, n.data.username
					$this->assertArrayHasKey('badge_id', $data, "$prefix: missing 'data.badge_id'");
					$this->assertArrayHasKey('badge_slug', $data, "$prefix: missing 'data.badge_slug'");
					$this->assertArrayHasKey('username', $data, "$prefix: missing 'data.username'");
					// getSubline -> n.data.badge_name
					$this->assertArrayHasKey('badge_name', $data, "$prefix: missing 'data.badge_name'");
					break;

				case TYPES_MODERATOR_OR_ADMIN_INBOX:
					// getTargetTitle accesses n.data.group_name, n.data.inbox_count (guarded by && checks, so won't crash)
					// nbAdminInboxItem / nbModeratorInboxItem -> n.data.group_name (guarded by n.data && check)
					// These are safe even if missing since the code guards with `n.data && n.data.group_name`
					// but we still verify they are present for correctness
					$this->assertArrayHasKey('group_name', $data, "$prefix: missing 'data.group_name'");
					$this->assertArrayHasKey('inbox_count', $data, "$prefix: missing 'data.inbox_count'");
					break;
			}
		}
	}

	/**
	 * Test that searchTopics returns results whose structure matches
	 * what DiscourseSearchTopicsProvider expects:
	 *   getMainText -> $entry['title']
	 *   getSubline -> $entry['id'], $entry['posts_count']
	 *   getLinkToDiscourse -> $entry['slug'], $entry['id']
	 */
	public function testSearchTopics(): void {
		$this->requireCredentials();

		$result = $this->service->searchTopics($this->discourseUrl, $this->discourseToken, 'error', 0, 5);

		$this->assertIsArray($result);
		$this->assertArrayNotHasKey('error', $result);
		$this->assertNotEmpty($result, 'Searching "error" should return at least one topic');

		// echo json_encode($result, JSON_PRETTY_PRINT);
		// echo '-----------------------------------------------';

		foreach ($result as $i => $entry) {
			$prefix = "topic[$i] (id=" . ($entry['id'] ?? '?') . ')';

			// DiscourseSearchTopicsProvider::getMainText
			$this->assertArrayHasKey('title', $entry, "$prefix: missing 'title'");
			$this->assertIsString($entry['title'], "$prefix: 'title' should be a string");

			// DiscourseSearchTopicsProvider::getSubline
			$this->assertArrayHasKey('id', $entry, "$prefix: missing 'id'");
			$this->assertArrayHasKey('posts_count', $entry, "$prefix: missing 'posts_count'");
			$this->assertIsInt($entry['posts_count'], "$prefix: 'posts_count' should be an int");

			// DiscourseSearchTopicsProvider::getLinkToDiscourse
			$this->assertArrayHasKey('slug', $entry, "$prefix: missing 'slug'");
			$this->assertIsString($entry['slug'], "$prefix: 'slug' should be a string");
		}
	}

	/**
	 * Test that searchPosts returns results whose structure matches
	 * what DiscourseSearchPostsProvider expects:
	 *   getMainText -> $entry['blurb'] ?? $entry['username']
	 *   getSubline -> $entry['topic_id']
	 *   getLinkToDiscourse -> $entry['topic_id']
	 *   getThumbnailUrl -> $entry['username']
	 */
	public function testSearchPosts(): void {
		$this->requireCredentials();

		$result = $this->service->searchPosts($this->discourseUrl, $this->discourseToken, 'error', 0, 5);

		$this->assertIsArray($result);
		$this->assertArrayNotHasKey('error', $result);
		$this->assertNotEmpty($result, 'Searching "error" should return at least one post');

		// echo json_encode($result, JSON_PRETTY_PRINT);
		// echo '-----------------------------------------------';

		foreach ($result as $i => $entry) {
			$prefix = "post[$i] (id=" . ($entry['id'] ?? '?') . ')';

			// DiscourseSearchPostsProvider::getMainText -> $entry['blurb'] ?? $entry['username']
			// At least one of blurb or username must be present
			$this->assertTrue(
				isset($entry['blurb']) || isset($entry['username']),
				"$prefix: at least one of 'blurb' or 'username' must be present"
			);

			// DiscourseSearchPostsProvider::getSubline and getLinkToDiscourse
			$this->assertArrayHasKey('topic_id', $entry, "$prefix: missing 'topic_id'");

			// DiscourseSearchPostsProvider::getThumbnailUrl
			// username is used for the avatar URL; the provider falls back to a static
			// icon when it is absent, but it is always present in practice
			$this->assertArrayHasKey('username', $entry, "$prefix: missing 'username'");
			$this->assertIsString($entry['username'], "$prefix: 'username' should be a string");
		}
	}

	public function testGetNotificationsWithInvalidToken(): void {
		if ($this->discourseUrl === null) {
			$this->markTestSkipped('DISCOURSE_URL not set');
		}

		$result = $this->service->getNotifications($this->discourseUrl, 'invalid_token_12345');

		$this->assertIsArray($result);
		$this->assertArrayHasKey('error', $result);
	}

	public function testGetNotificationsWithInvalidUrl(): void {
		$result = $this->service->getNotifications('https://invalid.discourse.example.com', 'some_token');

		$this->assertIsArray($result);
		$this->assertArrayHasKey('error', $result);
	}
}
