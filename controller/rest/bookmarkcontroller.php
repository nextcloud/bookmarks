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
use OCP\ILogger;
use \OCP\IRequest;
use \OCP\AppFramework\ApiController;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http\DataResponse;
use \OCP\AppFramework\Http\TemplateResponse;
use \OCP\AppFramework\Http;
use \OC\User\Manager;
use \OCP\IUserSession;
use \OCA\Bookmarks\Controller\Lib\Bookmarks;
use \OCA\Bookmarks\Controller\Lib\ExportResponse;
use \OCA\Bookmarks\Controller\Lib\Helper;
use OCP\Util;

class BookmarkController extends ApiController {
	private $userId;
	private $db;
	private $l10n;
	private $userManager;
	private $logger;

	/** @var Bookmarks */
	private $bookmarks;

	public function __construct($appName, IRequest $request, $userId, IDBConnection $db, IL10N $l10n, Bookmarks $bookmarks, Manager $userManager, ILogger $logger, IUserSession $userSession) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->db = $db;
		$this->request = $request;
		$this->l10n = $l10n;
		$this->bookmarks = $bookmarks;
		$this->userManager = $userManager;
		$this->logger = $logger;
		$this->userSession = $userSession;
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
		} else {
			$publicOnly = true;
			if ($this->userManager->userExists($user) === false) {
				$error = "User could not be identified";
				return new JSONResponse(['status' => 'error', 'data'=> $error], Http::STATUS_BAD_REQUEST);
			}
		}
		$bm = $this->bookmarks->findUniqueBookmark($id, $user);
		if (!isset($bm['id'])) {
			return new JSONResponse(['status' => 'error'], Http::STATUS_NOT_FOUND);
		}
		if ($publicOnly === true && isset($bm['public']) && $bm['public'] !== '1' && $bm['public'] !== 1) {
			$error = "Insufficient permissions";
			return new JSONResponse(['status' => 'error', 'data' => $error], Http::STATUS_BAD_REQUEST);
		}
		return new JSONResponse(['item' => $bm, 'status' => 'success']);
	}

	/**
	 * @param string $type
	 * @param string $tag
	 * @param int $page
	 * @param string $sort
	 * @param string $user
	 * @param array $tags
	 * @param string $conjunction
	 * @param string $sortby
	 * @param array $search
	 * @param int $limit
	 * @param bool $untagged
	 * @param int $folder
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @PublicPage
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
		$search = [],
		$limit = 10,
		$untagged = false,
		$folder = null
	) {
		$this->registerResponder('rss', function ($res) {
			if ($res->getData()['status'] === 'success') {
				$bookmarks = $res->getData()['data'];
				$description = '';
			} else {
				$bookmarks = [['id' => -1]];
				$description = $res->getData()['data'];
			}

			$response = new TemplateResponse('bookmarks', 'rss', [
				'rssLang'		=> $this->l10n->getLanguageCode(),
				'rssPubDate'	=> date('r'),
				'description'	=> $description,
				'bookmarks'		=> $bookmarks
			], '');
			$response->setHeaders($res->getHeaders());
			$response->setStatus($res->getStatus());
			if (stristr($this->request->getHeader('accept'), 'application/rss+xml')) {
				$response->addHeader('Content-Type', 'application/rss+xml');
			} else {
				$response->addHeader('Content-Type', 'text/xml; charset=UTF-8');
			}
			return $response;
		});

		if ($this->request->getHeader('Authorization')) {
			list($method, $credentials) = explode(' ', $this->request->getHeader('Authorization'));
		} else {
			$res = new DataResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
			$res->addHeader('WWW-Authenticate', 'Basic realm="Nextcloud", charset="UTF-8"');
			return $res;
		}
		if ($method !== 'Basic') {
			$res = new DataResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
			$res->addHeader('WWW-Authenticate', 'Basic realm="Nextcloud", charset="UTF-8"');
			return $res;
		} else {
			list($username, $password) = explode(':', base64_decode($credentials));
			if (false === $this->userSession->login($username, $password)) {
				$res = new DataResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
				$res->addHeader('WWW-Authenticate', 'Basic realm="Nextcloud", charset="UTF-8"');
				return $res;
			}
		}

		if ($user === null) {
			$user = $this->userId;
			$publicOnly = false;
		} else {
			$publicOnly = true;
			if ($this->userManager->userExists($user) === false) {
				$error = "User could not be identified";
				return new DataResponse(['status' => 'error', 'data'=> $error]);
			}
		}
		if ($type === 'rel_tags' && !$publicOnly) { // XXX: libbookmarks#findTags needs a publicOnly option
			$tags = $this->bookmarks->analyzeTagRequest($tag);
			$qtags = $this->bookmarks->findTags($user, $tags);
			return new DataResponse(['data' => $qtags, 'status' => 'success']);
		}

		// type == bookmark

		if ($tag !== '') {
			$filterTag = $this->bookmarks->analyzeTagRequest($tag);
		} elseif (is_array($tags)) {
			$filterTag = $tags;
		} elseif (is_string($tags) && $tags !== '') {
			$filterTag= [ $tags ];
		} else {
			$filterTag = [];
		}

		$tagsOnly = true;
		if (count($search) > 0) {
			$filterTag = array_merge($filterTag, $search);
			$tagsOnly = false;
		}

		$offset = $page * $limit;
		if ($page === -1) {
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

		$attributesToSelect = ['url', 'title', 'id', 'user_id', 'description', 'public',
			'added', 'lastmodified', 'clickcount', 'tags', 'image', 'favicon', 'folders'];

		$bookmarks = $this->bookmarks->findBookmarks(
			$user,
			$offset,
			$sqlSortColumn,
			$filterTag,
			$tagsOnly,
			$limit,
			$publicOnly,
			$attributesToSelect,
			$conjunction,
			$untagged,
			$folder
		);
		return new DataResponse(['data' => $bookmarks, 'status' => 'success']);
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
	public function newBookmark($url = "", $item = [], $title = null, $is_public = false, $description = "", $tags = [], $folders = null) {
		if (isset($title)) {
			$title = trim($title);
		}
		if (count($tags) === 0) {
			$tags = isset($item['tags']) ? $item['tags'] : [];
		}

		try {
			$id = $this->bookmarks->addBookmark($this->userId, $url, $title, $tags, $description, $is_public, $folders);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['status' => 'error', 'data' => [$e->getMessage()]], Http::STATUS_BAD_REQUEST);
		}
		$bm = $this->bookmarks->findUniqueBookmark($id, $this->userId);
		return new JSONResponse(['item' => $bm, 'status' => 'success']);
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
	public function legacyEditBookmark($id = null, $url = "", $item = [], $title = "", $is_public = false, $record_id = null, $description = "") {
		if ($id === null) {
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
	 * @param array $folders
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function editBookmark($id = null, $url = null, $item = null, $title = null, $is_public = null, $record_id = null, $description = null, $tags = null, $folders = null) {
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
		} elseif (is_array($tags)) {
			$newProps['tags'] = $tags;
		} else {
			$newProps['tags'] = [];
		}
		foreach ($newProps as $prop => $value) {
			if (!is_null($value)) {
				$bookmark[$prop] = $value;
			}
		}

		// Check if url and id are valid
		$urlData = parse_url($bookmark['url']);
		if (!$this->bookmarks->isProperURL($urlData) || !is_numeric($bookmark['id'])) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		$id = $this->bookmarks->editBookmark($this->userId, $bookmark['id'], $bookmark['url'], $bookmark['title'], $bookmark['tags'], $bookmark['description'], $bookmark['public'], $folders);

		$bm = $this->bookmarks->findUniqueBookmark($id, $this->userId);
		return new JSONResponse(['item' => $bm, 'status' => 'success']);
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
		if ($id === -1) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		$bm = $this->bookmarks->findUniqueBookmark($id, $this->userId);
		if (!isset($bm['id'])) {
			// If the item to delete is non-existent, let them believe we'ved deleted it
			return new JSONResponse(['status' => 'success'], Http::STATUS_OK);
		}

		if (!$this->bookmarks->deleteUrl($this->userId, $id)) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		} else {
			return new JSONResponse(['status' => 'success'], Http::STATUS_OK);
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
		if (!$this->bookmarks->isProperURL($urlData)) {
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
			$this->logger->warn("No file provided for import", ['app' => 'bookmarks']);
			$error = [];
			$error[] = $this->l10n->t('No file provided for import');
		} else {
			$error = [];
			$file = $full_input['tmp_name'];
			if ($full_input['type'] === 'text/html') {
				$error = $this->bookmarks->importFile($this->userId, $file);
				if (empty($error)) {
					return new JSONResponse(['status' => 'success']);
				}
			} else {
				$error[] = $this->l10n->t('Unsupported file type for import');
			}
		}

		return new JSONResponse(['status' => 'error', 'data' => $error]);
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
<TITLE>Bookmarks</TITLE>
EOT;

		$file .= $this->serializeFolder($this->userId, -1);

		return new ExportResponse($file);
	}

	private function serializeFolder($userId, $id) {
		if ($id !== -1) {
			$folder = $this->bookmarks->getFolder($userId, $id);
			$output = '<DT><h3>'.htmlspecialchars($folder['title']).'</h3>'."\n"
					  .'<DL><p>';
		} else {
			$output = '<H1>Bookmarks</h1>'."\n"
					  .'<DL><p>';
		}

		$childBookmarks = $this->bookmarks->findBookmarks($userId, 0, 'lastmodified', [], true, -1, false, null, "and", false, $id);
		foreach ($childBookmarks as $bookmark) {
			// discards records with no URL. This should not happen but
			// a database could have old entries
			if ($bookmark['url'] === '') {
				continue;
			}

			$tags = implode(',', Util::sanitizeHTML($bookmark['tags']));
			$title = trim($bookmark['title']);
			if ($title === '') {
				$url_parts = parse_url($bookmark['url']);
				$title = isset($url_parts['host']) ? Helper::getDomainWithoutExt($url_parts['host']) : $url;
			}
			$url = Util::sanitizeHTML($bookmark['url']);
			$title = Util::sanitizeHTML($title);
			$description = Util::sanitizeHTML($bookmark['description']);

			$output .= '<DT><A HREF="' . $url . '" TAGS="' . $tags . '" ADD_DATE="' . $bookmark['added']. '">' . $title . '</A>'."\n";
			if (strlen($description)>0) {
				$output .= '<DD>' . $description .'</DD>';
			}
			$output .= "\n";
		}

		$childFolders = $this->bookmarks->listFolders($userId, $id, 1);
		foreach ($childFolders as $childFolder) {
			$output .= $this->serializeFolder($userId, $childFolder['id']);
		}

		$output .= '</p></DL>';
		return $output;
	}
}
