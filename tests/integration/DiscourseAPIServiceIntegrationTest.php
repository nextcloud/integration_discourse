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

	public function testGetNotifications(): void {
		$this->requireCredentials();

		$result = $this->service->getNotifications($this->discourseUrl, $this->discourseToken);

		$this->assertIsArray($result);
		$this->assertArrayNotHasKey('error', $result);

		if (count($result) > 0) {
			$notification = $result[0];
			$this->assertArrayHasKey('id', $notification);
			$this->assertArrayHasKey('notification_type', $notification);
			$this->assertArrayHasKey('read', $notification);
			$this->assertArrayHasKey('created_at', $notification);
			$this->assertArrayHasKey('slug', $notification);
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
