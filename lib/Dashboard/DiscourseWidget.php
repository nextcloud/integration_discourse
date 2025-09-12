<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Discourse\Dashboard;

use OCA\Discourse\AppInfo\Application;
use OCP\Dashboard\IWidget;
use OCP\IL10N;
use OCP\IURLGenerator;

use OCP\Util;

class DiscourseWidget implements IWidget {

	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $url,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'discourse_notifications';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Discourse notifications');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconClass(): string {
		return 'icon-discourse';
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): ?string {
		return $this->url->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']);
	}

	/**
	 * @inheritDoc
	 */
	public function load(): void {
		Util::addScript(Application::APP_ID, Application::APP_ID . '-dashboard');
		Util::addStyle(Application::APP_ID, 'dashboard');
	}
}
