<?php
/**
 * Nextcloud - Discourse
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Discourse\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

use OCA\Discourse\Dashboard\DiscourseWidget;
use OCA\Discourse\Search\DiscourseSearchTopicsProvider;
use OCA\Discourse\Search\DiscourseSearchPostsProvider;
use OCP\Util;

/**
 * Class Application
 *
 * @package OCA\Discourse\AppInfo
 */
class Application extends App implements IBootstrap {

	public const APP_ID = 'integration_discourse';

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(DiscourseWidget::class);
		$context->registerSearchProvider(DiscourseSearchPostsProvider::class);
		$context->registerSearchProvider(DiscourseSearchTopicsProvider::class);
	}

	public function boot(IBootContext $context): void {
		Util::addStyle(self::APP_ID, 'discourse-search');
	}
}

