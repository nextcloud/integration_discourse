<?php
/**
 * @copyright Copyright (c) 2020 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Discourse\Dashboard;

use OCP\Dashboard\IWidget;
use OCP\IL10N;

class DiscourseWidget implements IWidget {

	/** @var IL10N */
	private $l10n;

	public function __construct(
		IL10N $l10n
	) {
		$this->l10n = $l10n;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'discourse';
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
		return \OC::$server->getURLGenerator()->linkToRoute('settings.PersonalSettings.index', ['section' => 'linked-accounts']);
	}

	/**
	 * @inheritDoc
	 */
	public function load(): void {
        \OC_Util::addScript('discourse', 'discourse-dashboard');
        \OC_Util::addStyle('discourse', 'dashboard');
    }
}