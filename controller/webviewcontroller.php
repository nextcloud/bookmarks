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
use \OCA\Bookmarks\Controller\Lib\Bookmarks;
use OCP\IURLGenerator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class WebViewController extends Controller {

	/** @var  string */
	private $userId;

	/** @var IURLGenerator  */
	private $urlgenerator;

	/** @var Bookmarks */
	private $bookmarks;

	/** @var EventDispatcherInterface */
	private $eventDispatcher;


	/**
	 * WebViewController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param $userId
	 * @param IURLGenerator $urlgenerator
	 * @param Bookmarks $bookmarks
	 * @param EventDispatcherInterface $eventDispatcher
	 */
	public function __construct($appName, IRequest $request, $userId, IURLGenerator $urlgenerator, Bookmarks $bookmarks, EventDispatcherInterface $eventDispatcher) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->urlgenerator = $urlgenerator;
		$this->bookmarks = $bookmarks;
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index() {
		$params = array('user' => $this->userId);

		$policy = new ContentSecurityPolicy();
		$policy->addAllowedFrameDomain("'self'");

		$this->eventDispatcher->dispatch(
			'\OCA\Bookmarks::loadAdditionalScripts', new GenericEvent(null, [])
		);

		$response = new TemplateResponse('bookmarks', 'main', $params);
		$response->setContentSecurityPolicy($policy);
		return $response;
	}

	/**
	 * @param string $url
	 * @param string $title
	 * @return TemplateResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function bookmarklet($url = "", $title = "") {
		$bookmarkExists = $this->bookmarks->bookmarkExists($url, $this->userId);
		$description = "";
        $tags = [];
		if ($bookmarkExists !== false){
			$bookmark = $this->bookmarks->findUniqueBookmark($bookmarkExists, $this->userId);
			$description = $bookmark['description'];
            $tags = $bookmark['tags'];
		}
		$params = array(
            'url'           => $url,
            'title'         => $title,
            'description'   => $description,
            'bookmarkExists'=> $bookmarkExists,
            'tags'          => $tags
        );
		return new TemplateResponse('bookmarks', 'addBookmarklet', $params);  // templates/main.php
	}

}
