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

namespace OCA\Bookmarks\Controller\Rest;

use \OCP\IRequest;
use \OCP\AppFramework\ApiController;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http;
use \OCP\IDb;
use \OCA\Bookmarks\Controller\Lib\Bookmarks;
use \OCA\Bookmarks\Controller\Lib\ExportResponse;

class BookmarkController extends ApiController {

	private $userId;
	private $db;

	public function __construct($appName, IRequest $request, $userId, IDb $db) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->db = $db;
		$this->request = $request;
	}

	/**
	 * @NoAdminRequired
	 */
	public function legacyGetBookmarks($type = "bookmark", $tag = '', $page = 0, $sort = "bookmarks_sorting_recent") {
		return $this->getBookmarks($type, $tag, $page, $sort);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getBookmarks($type = "bookmark", $tag = '', $page = 0, $sort = "bookmarks_sorting_recent") {

		if ($type == 'rel_tags') {
			$tags = Bookmarks::analyzeTagRequest($tag);
			$qtags = Bookmarks::findTags($this->userId, $this->db, $tags);
			return new JSONResponse(array('data' => $qtags, 'status' => 'success'));
		} else { // type == bookmark
			$filterTag = Bookmarks::analyzeTagRequest($tag);

			$offset = $page * 10;

			if ($sort == 'bookmarks_sorting_clicks') {
				$sqlSortColumn = 'clickcount';
			} else {
				$sqlSortColumn = 'lastmodified';
			}
			$bookmarks = Bookmarks::findBookmarks($this->userId, $this->db, $offset, $sqlSortColumn, $filterTag, true);
			return new JSONResponse(array('data' => $bookmarks, 'status' => 'success'));
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function newBookmark($url = "", $tag = '', $from_own = 0, $title = "", $is_public = false, $description = "") {

		// Check if it is a valid URL
		if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
			return new JSONResponse(array('status' => 'error'), Http::STATUS_BAD_REQUEST);
		}

		$tags = Bookmarks::analyzeTagRequest($tag);

		if ($from_own == 0) {
			$datas = Bookmarks::getURLMetadata($url);
			if (isset($datas['title'])) {
				$title = $datas['title'];
			}
		}
		$id = Bookmarks::addBookmark($this->userId, $this->db, $url, $title, $tags, $description, $is_public);
		$bm = Bookmarks::findUniqueBookmark($id, $this->userId, $this->db);
		return new JSONResponse(array('item' => $bm, 'status' => 'success'));
	}

	/**
	  @NoAdminRequired
	 * 
	 * @param int $id
	 * @param bool $is_public Description
	 * @return \OCP\AppFramework\Http\TemplateResponse
	 */
	//TODO id vs record_id?
	public function legacyEditBookmark($id = null, $url = "", $item = array(), $title = "", $is_public = false, $record_id = null, $description = "") {
		if ($id == null) {
			return $this->newBookmark($url, implode(',', $item['tags']), false, $title, $is_public, $description);
		} else {
			return $this->editBookmark($id, $url, $item, $title, $is_public, $record_id, $description);
		}
	}

	/**
	  @NoAdminRequired
	 * 
	 * @param int $id
	 * @param bool $is_public Description
	 * @return \OCP\AppFramework\Http\TemplateResponse
	 */
	public function editBookmark($id = null, $url = "", $item = array(), $title = "", $is_public = false, $record_id = null, $description = "") {

		// Check if it is a valid URL
		if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}

		if ($record_id == null) {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}

		$tags = isset($item['tags']) ? $item['tags'] : array();

		if (is_numeric($record_id)) {
			$id = Bookmarks::editBookmark($this->userId, $this->db, $record_id, $url, $title, $tags, $description, $is_public = false);
		}

		$bm = Bookmarks::findUniqueBookmark($id, $this->userId, $this->db);
		return new JSONResponse(array('item' => $bm, 'status' => 'success'));
	}

	/**
	  @NoAdminRequired
	 * 
	 * @param int $id
	 * @param bool $is_public Description
	 * @return \OCP\AppFramework\Http\JSONResponse
	 */
	public function legacyDeleteBookmark($id = -1) {
		return $this->deleteBookmark($id);
	}

	/**
	  @NoAdminRequired
	 * 
	 * @param int $id
	 * @param bool $is_public Description
	 * @return \OCP\AppFramework\Http\JSONResponse
	 */
	public function deleteBookmark($id = -1) {
		if ($id == -1) {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}

		if (!Bookmarks::deleteUrl($this->userId, $this->db, $id)) {
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

		// Check if it is a valid URL
		if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
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

		$l = new \OC_l10n('bookmarks');

		$full_input = $this->request->getUploadedFile("bm_import");

		if (empty($full_input)) {
			\OCP\Util::writeLog('bookmarks', "No file provided for import", \OCP\Util::WARN);
			$error = array();
			$error[] = $l->t('No file provided for import');
		} else {
			$error = array();
			$file = $full_input['tmp_name'];
			if ($full_input['type'] == 'text/html') {
				$error = Bookmarks::importFile($this->userId, $this->db, $file);
				if (empty($error)) {
					return new JSONResponse(array('status' => 'success'));
				}
			} elseif ($full_input['type'] == 'text/xml') {
				$error = Bookmarks::importDeliciousFile($this->userId, $this->db, $file);
				if (empty($error)) {
					return new JSONResponse(array('status' => 'success'));
				}
			} else {
				$error[] = $l->t('Unsupported file type for import');
			}
		}

		return new JSONResponse(array('status' => 'error', 'data' => $error));
	}

	/**
	  @NoAdminRequired
	 * 
	 * @return \OCP\AppFramework\Http\JSONResponse
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
		$bookmarks = Bookmarks::findBookmarks($this->userId, $this->db, 0, 'id', array(), true, -1);
		foreach ($bookmarks as $bm) {
			$title = $bm['title'];
			if (trim($title) === '') {
				$url_parts = parse_url($bm['url']);
				$title = isset($url_parts['host']) ? OCA\Bookmarks\Controller\Lib\Helper::getDomainWithoutExt($url_parts['host']) : $bm['url'];
			}
			$file .= '<DT><A HREF="' . $bm['url'] . '" TAGS="' . implode(',', $bm['tags']) . '">';
			$file .= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</A>';
			if ($bm['description'])
				$file .= '<DD>' . htmlspecialchars($bm['description'], ENT_QUOTES, 'UTF-8');
			$file .= "\n";
		}

		return new ExportResponse($file);
	}

}
