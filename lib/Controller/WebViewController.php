<?php

/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Stefan Klemm <mail@stefan-klemm.de>
 * @copyright Stefan Klemm 2014
 */

namespace OCA\Bookmarks\Controller;

use OCP\AppFramework\Http\ContentSecurityPolicy;
use \OCP\IRequest;
use \OCP\AppFramework\Http\TemplateResponse;
use \OCP\AppFramework\Controller;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class WebViewController extends Controller {

	/** @var string */
	private $userId;

	/** @var EventDispatcherInterface */
	private $eventDispatcher;


	/**
	 * WebViewController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param $userId
	 * @param EventDispatcherInterface $eventDispatcher
	 */
	public function __construct($appName, $request, $userId, EventDispatcherInterface $eventDispatcher) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index() {
		$params = ['user' => $this->userId];

		$policy = new ContentSecurityPolicy();
		$policy->addAllowedFrameDomain("'self'");
		$policy->allowEvalScript(true);

		$this->eventDispatcher->dispatch(
			'\OCA\Bookmarks::loadAdditionalScripts',
			new GenericEvent(null, [])
		);

		$response = new TemplateResponse('bookmarks', 'main', $params);
		$response->setContentSecurityPolicy($policy);
		return $response;
	}
}
