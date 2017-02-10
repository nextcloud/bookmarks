<?php

/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Stefan Klemm <mail@stefan-klemm.de>
 * @copyright Stefan Klemm 2014
 */

namespace OCA\Bookmarks\Controller\Rest;

use OCP\IDBConnection;
use OCP\IL10N;
use \OCP\IRequest;
use \OCP\AppFramework\ApiController;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http;
use \OC\User\Manager;
use \OCA\Bookmarks\Controller\Lib\Bookmarks;
use \OCA\Bookmarks\Controller\Lib\ExportResponse;
use \OCA\Bookmarks\Controller\Lib\Helper;
use OCP\Util;

class BookmarkController extends ApiController {

	private $userId;
	private $db;
	private $l10n;
	private $userManager;

	/** @var Bookmarks */
	private $bookmarks;

	public function __construct($appName, IRequest $request, $userId, IDBConnection $db, IL10N $l10n, Bookmarks $bookmarks, Manager $userManager) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->db = $db;
		$this->request = $request;
		$this->l10n = $l10n;
		$this->bookmarks = $bookmarks;
		$this->userManager = $userManager;
	}

	/**
	 * @param string $type
	 * @param string $tag
	 * @param int $page
	 * @param string $sort
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function legacyGetBookmarks($type = "bookmark", $tag = '', $page = 0, $sort = "bookmarks_sorting_recent") {
		return $this->getBookmarks($type, $tag, $page, $sort);
	}

	/**
	 * @param string $type
	 * @param string $tag
	 * @param int $page
	 * @param string $sort
	 * @return JSONResponse
	 *
	 * @CORS
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getBookmarks(
		$type = "bookmark",
		$tag = '', // legacy
		$page = 0,
		$sort = "bookmarks_sorting_recent", // legacy
		$user = null,
		$tags = array(),
		$conjunction = "or",
		$select = null,
		$sortby = "",
		$search = array()
	) {
		if ($user == null) {
			$user = $this->userId;
			$publicOnly = false;
		}else {
			$publicOnly = true;
			if ($this->userManager->userExists($user) == false) {
				return $this->newJsonErrorMessage("User could not be identified");
			}
		}
		if ($type == 'rel_tags' && !$publicOnly) { // XXX: libbookmarks#findTags needs a publicOnly option
			$tags = $this->bookmarks->analyzeTagRequest($tag);
			$qtags = $this->bookmarks->findTags($user, $tags);
			return new JSONResponse(array('data' => $qtags, 'status' => 'success'));
		} else { // type == bookmark
			if ($tag) {
				$filterTag = $this->bookmarks->analyzeTagRequest($tag);
			}
			if (!is_array($tags)) {		
				if(is_string($tags) && $tags !== '') {		
					$tags = [ $tags ];		
				} else {		
					$tags = array();		
				}		
			}
			$tagsOnly = true;
			if (count($search) > 0) {
				$tags = array_merge($tags, $search);
				$tagsOnly = false;
			}
			if (count($tags) > 0) {
				$filterTag = $tags;
			}

			$limit = 10;
			$offset = $page * 10;
			if ($page == -1) {
				$limit = -1;
				$offset = 0;
			}

			if ($sort == 'bookmarks_sorting_clicks') {
				$sqlSortColumn = 'clickcount';
			} else {
				$sqlSortColumn = 'lastmodified';
			}
			if ($sortby) {
				$sqlSortColumn = $sortby;
			}
			
			$attributesToSelect = array('url', 'title');		
			if ($select != null) {		
				$attributesToSelect = array_merge($attributesToSelect, $select);		
				$attributesToSelect = array_unique($attributesToSelect);		
			}

			$bookmarks = $this->bookmarks->findBookmarks($user, $offset, $sqlSortColumn, $filterTag,
				$tagsOnly, $limit, $publicOnly, $attributesToSelect, $conjunction);
			return new JSONResponse(array('data' => $bookmarks, 'status' => 'success'));
		}
	}

	/**
	 * @param string $url
	 * @param array $item
	 * @param string $title
	 * @param bool $is_public
	 * @param string $description
	 * @return JSONResponse
	 *
	 * @CORS
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function newBookmark($url = "", $item = array(), $title = "", $is_public = false, $description = "") {
		$title = trim($title);
		if ($title === '') {
			$title = $url;
			// allow only http(s) and (s)ftp
			$protocols = '/^(https?|s?ftp)\:\/\//i';
			try {
				if (preg_match($protocols, $url)) {
					$data = $this->bookmarks->getURLMetadata($url);
					$title = isset($data['title']) ? $data['title'] : $title;
				} else {
					// if no allowed protocol is given, evaluate https and https
					foreach(['https://', 'http://'] as $protocol) {
						$testUrl = $protocol . $url;
						$data = $this->bookmarks->getURLMetadata($testUrl);
						if(isset($data['title'])) {
							$title = $data['title'];
							$url   = $testUrl;
							break;
						}
					}
				}
			} catch (\Exception $e) {
				// only because the server cannot reach a certain URL it does not
				// mean the user's browser cannot.
				\OC::$server->getLogger()->logException($e, ['app' => 'bookmarks']);
			}
		}

		// Check if it is a valid URL (after adding http(s) prefix)
		$urlData = parse_url($url);
		if(!$this->isProperURL($urlData)) {
			return new JSONResponse(array('status' => 'error'), Http::STATUS_BAD_REQUEST);
		}

		$tags = isset($item['tags']) ? $item['tags'] : array();

		$id = $this->bookmarks->addBookmark($this->userId, $url, $title, $tags, $description, $is_public);
		$bm = $this->bookmarks->findUniqueBookmark($id, $this->userId);
		return new JSONResponse(array('item' => $bm, 'status' => 'success'));
	}

	/**
	 * @param int $id
	 * @param string $url
	 * @param array $item
	 * @param string $title
	 * @param bool $is_public Description
	 * @param null $record_id
	 * @param string $description
	 * @return Http\TemplateResponse
	 *
	 * @NoAdminRequired
	 */
	//TODO id vs record_id?
	public function legacyEditBookmark($id = null, $url = "", $item = array(), $title = "", $is_public = false, $record_id = null, $description = "") {
		if ($id == null) {
			return $this->newBookmark($url, $item, $title, $is_public, $description);
		} else {
			return $this->editBookmark($id, $url, $item, $title, $is_public, $record_id, $description);
		}
	}

	/**
	 * @param int $id
	 * @param string $url
	 * @param array $item
	 * @param string $title
	 * @param bool $is_public Description
	 * @param null $record_id
	 * @param string $description
	 * @return JSONResponse
	 *
	 * @CORS
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function editBookmark($id = null, $url = "", $item = array(), $title = "", $is_public = false, $record_id = null, $description = "") {

		// Check if it is a valid URL
		$urlData = parse_url($url);
		if(!$this->isProperURL($urlData)) {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}

		if ($record_id == null) {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}

		$tags = isset($item['tags']) ? $item['tags'] : array();

		if (is_numeric($record_id)) {
			$id = $this->bookmarks->editBookmark($this->userId, $record_id, $url, $title, $tags, $description, $is_public = false);
		}

		$bm = $this->bookmarks->findUniqueBookmark($id, $this->userId);
		return new JSONResponse(array('item' => $bm, 'status' => 'success'));
	}

	/**
	 * @param int $id
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function legacyDeleteBookmark($id = -1) {
		return $this->deleteBookmark($id);
	}

	/**
	 * @param int $id
	 * @return \OCP\AppFramework\Http\JSONResponse
	 *
	 * @CORS
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function deleteBookmark($id = -1) {
		if ($id == -1) {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}

		if (!$this->bookmarks->deleteUrl($this->userId, $id)) {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		} else {
			return new JSONResponse(array('status' => 'success'), Http::STATUS_OK);
		}
	}

	/**
	  @NoAdminRequired
	 * 
	 * @param string $url
	 * @return \OCP\AppFramework\Http\JSONResponse
	 */
	public function clickBookmark($url = "") {
		$urlData = parse_url($url);
		if(!$this->isProperURL($urlData)) {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}

		$query = $this->db->prepareQuery('
			UPDATE `*PREFIX*bookmarks`
			SET `clickcount` = `clickcount` + 1
			WHERE `user_id` = ?
				AND `url` LIKE ?
		');

		$params = array($this->userId, htmlspecialchars_decode($url));
		$query->execute($params);

		return new JSONResponse(array('status' => 'success'), Http::STATUS_OK);
	}

	/**
	  @NoAdminRequired
	 * 
	 * @return \OCP\AppFramework\Http\JSONResponse
	 */
	public function importBookmark() {
		$full_input = $this->request->getUploadedFile("bm_import");

		if (empty($full_input)) {
			Util::writeLog('bookmarks', "No file provided for import", Util::WARN);
			$error = array();
			$error[] = $this->l10n->t('No file provided for import');
		} else {
			$error = array();
			$file = $full_input['tmp_name'];
			if ($full_input['type'] == 'text/html') {
				$error = $this->bookmarks->importFile($this->userId, $file);
				if (empty($error)) {
					return new JSONResponse(array('status' => 'success'));
				}
			} else {
				$error[] = $this->l10n->t('Unsupported file type for import');
			}
		}

		return new JSONResponse(array('status' => 'error', 'data' => $error));
	}

	/**
	  @NoAdminRequired
	 * 
	 * @return \OCP\AppFramework\Http\Response
	 */
	public function exportBookmark() {

		$file = <<<EOT
<!DOCTYPE NETSCAPE-Bookmark-file-1>
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
<!-- This is an automatically generated file.
It will be read and overwritten.
Do Not Edit! -->
<TITLE>Bookmarks</TITLE>
<H1>Bookmarks</H1>
<DL><p>
EOT;
		$bookmarks = $this->bookmarks->findBookmarks($this->userId, 0, 'id', [], true, -1);
		foreach ($bookmarks as $bm) {
			$title = $bm['title'];
			if (trim($title) === '') {
				$url_parts = parse_url($bm['url']);
				$title = isset($url_parts['host']) ? Helper::getDomainWithoutExt($url_parts['host']) : $bm['url'];
			}
			$file .= '<DT><A HREF="' . \OC_Util::sanitizeHTML($bm['url']) . '" TAGS="' . implode(',', \OC_Util::sanitizeHTML($bm['tags'])) . '">';
			$file .= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</A>';
			if ($bm['description'])
				$file .= '<DD>' . htmlspecialchars($bm['description'], ENT_QUOTES, 'UTF-8');
			$file .= "\n";
		}

		return new ExportResponse($file);
	}

	/**
	 * Checks whether parse_url was able to return proper URL data
	 *
	 * @param bool|array $urlData result of parse_url
	 * @return bool
	 */
	protected function isProperURL($urlData) {
		if ($urlData === false || !isset($urlData['scheme']) || !isset($urlData['host'])) {
			return false;
		}
		return true;
	}

}
