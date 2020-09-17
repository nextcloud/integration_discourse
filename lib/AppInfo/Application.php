<?php
/**
 * Nextcloud - Discourse
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Discourse\AppInfo;

use OCP\IContainer;

use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

use OCA\Discourse\Controller\PageController;
use OCA\Discourse\Dashboard\DiscourseWidget;

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

		$container = $this->getContainer();
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(DiscourseWidget::class);
	}

	public function boot(IBootContext $context): void {
	}
}

