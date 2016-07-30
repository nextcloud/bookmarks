<?php

/**
 * ownCloud - bookmarks
 *
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
use \OCP\IDb;
use \OCA\Bookmarks\Controller\Lib\Bookmarks;

class WebViewController extends Controller {

	private $userId;
	private $urlgenerator;
	private $db;

	public function __construct($appName, IRequest $request, $userId, $urlgenerator, IDb $db) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->urlgenerator = $urlgenerator;
		$this->db = $db;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index() {
		$bookmarkleturl = $this->urlgenerator->getAbsoluteURL('index.php/apps/bookmarks/bookmarklet');
		$params = array('user' => $this->userId, 'bookmarkleturl' => $bookmarkleturl);

		$policy = new ContentSecurityPolicy();
		$policy->addAllowedFrameDomain("'self'");

		$response = new TemplateResponse('bookmarks', 'main', $params);
		$response->setContentSecurityPolicy($policy);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function bookmarklet($url = "", $title = "") {
		$bookmarkExists = Bookmarks::bookmarkExists($url, $this->userId, $this->db);
		$tags = array();
		$description = "";
		if ($bookmarkExists !== false) {
			$bookmark = Bookmarks::findUniqueBookmark($bookmarkExists, $this->userId, $this->db);
			$tags = $bookmark['tags'];
			$description = $bookmark['description'];
		}
		$params = array('url' => $url, 'title' => $title, 'tags' => $tags,
				'description' => $description, 'bookmarkExists' => $bookmarkExists);
		return new TemplateResponse('bookmarks', 'addBookmarklet', $params);  // templates/main.php
	}

}
