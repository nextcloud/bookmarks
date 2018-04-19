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
	 * @param string $id
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function getSingleBookmark($id, $user = null) {
		if ($user === null) {
			$user = $this->userId;
			$publicOnly = false;
		}else {
			$publicOnly = true;
			if ($this->userManager->userExists($user) == false) {
				$error = "User could not be identified";
				return new JSONResponse(array('status' => 'error', 'data'=> $error), Http::STATUS_BAD_REQUEST);
			}
		}
		$bm = $this->bookmarks->findUniqueBookmark($id, $user);
		if(!isset($bm['id'])) {
			return new JSONResponse(['status' => 'error'], Http::STATUS_NOT_FOUND);
		}
		if ($publicOnly === true && isset($bm['public']) && $bm['public'] != '1') {
            $error = "Insufficient permissions";
			return new JSONResponse(array('status' => 'error', 'data' => $error), Http::STATUS_BAD_REQUEST);
    	}
		return new JSONResponse(array('item' => $bm, 'status' => 'success'));
	}

	/**
	 * @param string $type
	 * @param string $tag
	 * @param int $page
	 * @param string $sort
	 * @param string user
	 * @param array tags
	 * @param string conjunction
	 * @param string sortby
	 * @param array search
	 * @param int limit
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function getBookmarks(
		$type = "bookmark",
		$tag = '', // legacy
		$page = 0,
		$sort = "bookmarks_sorting_recent", // legacy
		$user = null,
		$tags = null,
		$conjunction = "or",
		$sortby = "",
		$search = array(),
    $limit = 10
	) {
		if ($user === null) {
			$user = $this->userId;
			$publicOnly = false;
		}else {
			$publicOnly = true;
			if ($this->userManager->userExists($user) == false) {
				$error = "User could not be identified";
				return new JSONResponse(array('status' => 'error', 'data'=> $error));
			}
		}
		if ($type === 'rel_tags' && !$publicOnly) { // XXX: libbookmarks#findTags needs a publicOnly option
			$tags = $this->bookmarks->analyzeTagRequest($tag);
			$qtags = $this->bookmarks->findTags($user, $tags);
			return new JSONResponse(array('data' => $qtags, 'status' => 'success'));
		}

		// type == bookmark

		if ($tag !== '') {
		  $filterTag = $this->bookmarks->analyzeTagRequest($tag);
		} elseif (is_array($tags)) {
		  $filterTag = $tags;
		} elseif (is_string($tags) && $tags !== '') {
			$filterTag= [ $tags ];
		} else {
			$filterTag = array();
		}

		$tagsOnly = true;
		if (count($search) > 0) {
			$filterTag = array_merge($filterTag, $search);
			$tagsOnly = false;
		}

		$offset = $page * $limit;
		if ($page == -1) {
			$limit = -1;
			$offset = 0;
		}

		if ($sort === 'bookmarks_sorting_clicks') {
			$sqlSortColumn = 'clickcount';
		} elseif ($sortby) {
			$sqlSortColumn = $sortby;
		} else {
			$sqlSortColumn = 'lastmodified';
		}

		$attributesToSelect = array('url', 'title', 'id', 'user_id', 'description', 'public',
			'added', 'lastmodified', 'clickcount', 'tags', 'image', 'favicon');

		$bookmarks = $this->bookmarks->findBookmarks($user, $offset, $sqlSortColumn, $filterTag,
			$tagsOnly, $limit, $publicOnly, $attributesToSelect, $conjunction);
		return new JSONResponse(array('data' => $bookmarks, 'status' => 'success'));
	}

	/**
	 * @param string $url
	 * @param array $item
	 * @param string $title
	 * @param bool $is_public
	 * @param string $description
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function newBookmark($url = "", $item = array(), $title = "", $is_public = false, $description = "") {
		$title = trim($title);
		$image = null;
		$tags = isset($item['tags']) ? $item['tags'] : array();

        try {
            $id = $this->bookmarks->addBookmark($this->userId, $url, $title, $tags, $description, $is_public, $image);
		} catch(\InvalidArgumentException $e) {
			return new JSONResponse(array('status' => 'error', 'data' => [$e->getMessage()]), Http::STATUS_BAD_REQUEST);
        }
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
	 * @CORS
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
	 * @param int $record_id
	 * @param string $description
	 * @param array $tags
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function editBookmark($id = null, $url = null, $item = null, $title = null, $is_public = null, $record_id = null, $description = null, $tags = null) {

		if ($record_id !== null) {
			$id = $record_id;
		}

		$bookmark = $this->bookmarks->findUniqueBookmark($id, $this->userId);
		$newProps = [
			'url' => $url,
			'title' => $title,
			'public' => $is_public,
			'description' => $description
		];
		if (is_array($item) && isset($item['tags']) && is_array($item['tags'])) {
			$newProps['tags'] = $item['tags'];
		}elseif (is_array($tags)) {
			$newProps['tags'] = $tags;
		}else{
			$newProps['tags'] = [];
		}
		foreach ($newProps as $prop => $value) {
			if(!is_null($value)) {
				$bookmark[$prop] = $value;
			}
		}

		// Check if url and id are valid
		$urlData = parse_url($bookmark['url']);
		if(!$this->bookmarks->isProperURL($urlData) || !is_numeric($bookmark['id'])) {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}

		$id = $this->bookmarks->editBookmark($this->userId, $bookmark['id'], $bookmark['url'], $bookmark['title'], $bookmark['tags'], $bookmark['description'], $bookmark['public']);

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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function deleteBookmark($id = -1) {
		if ($id == -1) {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}

		$bm = $this->bookmarks->findUniqueBookmark($id, $this->userId);
		if(!isset($bm['id'])) {
			// If the item to delete is non-existent, let them believe we'ved deleted it
			return new JSONResponse(array('status' => 'success'), Http::STATUS_OK);
		}

		if (!$this->bookmarks->deleteUrl($this->userId, $id)) {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		} else {
			return new JSONResponse(array('status' => 'success'), Http::STATUS_OK);
		}
	}

	/**
	 *
	 * @param string $url
	 * @return \OCP\AppFramework\Http\JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function clickBookmark($url = "") {
		$url = urldecode($url);
		$urlData = parse_url($url);
		if(!$this->bookmarks->isProperURL($urlData)) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		$qb = $this->db->getQueryBuilder();
		$qb->update('bookmarks')
			->set('clickcount', $qb->createFunction('`clickcount` +1'))
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
			->andWhere($qb->expr()->eq('url', $qb->createNamedParameter(htmlspecialchars_decode($url))))
			->execute();

		return new JSONResponse(['status' => 'success'], Http::STATUS_OK);
	}

	/**
	 *
	 * @return \OCP\AppFramework\Http\JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS	 
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
	 * Hit this GET endpoint to export bookmarks via your API client.
	 * http://server_ip/nextcloud/index.php/apps/bookmarks/public/rest/v2/bookmark/export
	 * Basic authentication required.
	 * @return \OCP\AppFramework\Http\Response
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
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

			$url = \OC_Util::sanitizeHTML($bm['url']);

			// discards records with no URL. This should not happen but 
			// a database could have old entries
			if ($url === '') continue;

			$tags = implode(',', \OC_Util::sanitizeHTML($bm['tags']));
			$title = trim($bm['title']);
			if ($title === '') {
				$url_parts = parse_url($bm['url']);
				$title = isset($url_parts['host']) ? Helper::getDomainWithoutExt($url_parts['host']) : $url;
			}
			$title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
			$description = htmlspecialchars($bm['description'], ENT_QUOTES, 'UTF-8');

			$file .= '<DT><A HREF="' . $url . '" TAGS="' . $tags . '">' . $title . '</A>';
			if(strlen($description)>0) $file .= '<DD>' . $description;
			$file .= . "\n";
		}

		return new ExportResponse($file);
	}
}
