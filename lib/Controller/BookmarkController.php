<?php

/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Stefan Klemm <mail@stefan-klemm.de>
 * @copyright Stefan Klemm 2014
 */

namespace OCA\Bookmarks\Controller;

use DateInterval;
use DateTime;
use OCA\Bookmarks\Contract\IBookmarkPreviewer;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\TagMapper;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\UnauthorizedAccessError;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCA\Bookmarks\ExportResponse;
use OCA\Bookmarks\Service\Authorizer;
use OCA\Bookmarks\Service\BookmarkPreviewer;
use OCA\Bookmarks\Service\FaviconPreviewer;
use OCA\Bookmarks\Service\HtmlExporter;
use OCA\Bookmarks\Service\HtmlImporter;
use OCA\Bookmarks\Service\LinkExplorer;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;

class BookmarkController extends ApiController {
	const IMAGES_CACHE_TTL = 7 * 24 * 60 * 60;

	private $userId;

	/**
	 * @var IL10N
	 */
	private $l10n;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var ILogger
	 */
	private $logger;

	/**
	 * @var IUserSession
	 */
	private $userSession;

	/**
	 * @var BookmarkMapper
	 */
	private $bookmarkMapper;

	/**
	 * @var TagMapper
	 */
	private $tagMapper;

	/**
	 * @var FolderMapper
	 */
	private $folderMapper;

	/**
	 * @var LinkExplorer
	 */
	private $linkExplorer;

	/**
	 * @var IBookmarkPreviewer
	 */
	private $bookmarkPreviewer;

	/**
	 * @var IBookmarkPreviewer
	 */
	private $faviconPreviewer;

	/**
	 * @var ITimeFactory
	 */
	private $timeFactory;

	/**
	 * @var IURLGenerator
	 */
	private $url;

	/**
	 * @var HtmlImporter
	 */
	private $htmlImporter;

	/**
	 * @var HtmlExporter
	 */
	private $htmlExporter;

	/**
	 * @var Authorizer
	 */
	private $authorizer;

	public function __construct(
		$appName,
		$request,
		$userId,
		IL10N $l10n,
		BookmarkMapper $bookmarkMapper,
		TagMapper $tagMapper,
		FolderMapper $folderMapper,
		IUserManager $userManager,
		BookmarkPreviewer $bookmarkPreviewer,
		FaviconPreviewer $faviconPreviewer,
		ITimeFactory $timeFactory,
		ILogger $logger,
		IUserSession $userSession,
		LinkExplorer $linkExplorer,
		IURLGenerator $url,
		HtmlImporter $htmlImporter,
		HtmlExporter $htmlExporter,
		Authorizer $authorizer
	) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->request = $request;
		$this->l10n = $l10n;
		$this->bookmarkMapper = $bookmarkMapper;
		$this->tagMapper = $tagMapper;
		$this->folderMapper = $folderMapper;
		$this->userManager = $userManager;
		$this->bookmarkPreviewer = $bookmarkPreviewer;
		$this->faviconPreviewer = $faviconPreviewer;
		$this->timeFactory = $timeFactory;
		$this->logger = $logger;
		$this->userSession = $userSession;
		$this->linkExplorer = $linkExplorer;
		$this->url = $url;
		$this->htmlImporter = $htmlImporter;
		$this->htmlExporter = $htmlExporter;
		$this->authorizer = $authorizer;
	}

	/**
	 * @param Bookmark $bookmark
	 * @return array
	 */
	private function _returnBookmarkAsArray(Bookmark $bookmark): array {
		$array = $bookmark->toArray();
		$array['folders'] = array_map(function (Folder $folder) {
			return $folder->getId();
		}, $this->folderMapper->findByBookmark($bookmark->getId()));
		$array['tags'] = $this->tagMapper->findByBookmark($bookmark->getId());
		return $array;
	}

	/**
	 * @param string $id
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function getSingleBookmark($id) {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForBookmark($id, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Not found'], Http::STATUS_NOT_FOUND);
		}
		try {
			$bm = $this->bookmarkMapper->find($id);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Not found'], Http::STATUS_NOT_FOUND);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_BAD_REQUEST);
		}
		return new JSONResponse(['item' => $this->_returnBookmarkAsArray($bm), 'status' => 'success']);
	}

	/**
	 * @param int $page
	 * @param array $tags
	 * @param string $conjunction
	 * @param string $sortby
	 * @param array $search
	 * @param int $limit
	 * @param bool $untagged
	 * @param int $folder
	 * @param string $url
	 * @return DataResponse
	 *
	 * @throws UrlParseError
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @PublicPage
	 */
	public function getBookmarks(
		$page = 0,
		$tags = null,
		$conjunction = "or",
		$sortby = "",
		$search = [],
		$limit = 10,
		$untagged = false,
		$folder = null,
		$url = null
	) {
		$this->registerResponder('rss', function (Http\Response $res) {
			if ($res->getData()['status'] === 'success') {
				$bookmarks = $res->getData()['data'];
				$description = '';
			} else {
				$bookmarks = [['id' => -1]];
				$description = $res->getData()['data'];
			}

			$response = new TemplateResponse('bookmarks', 'rss', [
				'rssLang' => $this->l10n->getLanguageCode(),
				'rssPubDate' => date('r'),
				'description' => $description,
				'bookmarks' => $bookmarks,
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

		// Try to authenticate user ourselves
		if (!$this->userId) {
			if ($this->request->getHeader('Authorization')) {
				[$method, $credentials] = explode(' ', $this->request->getHeader('Authorization'));
			} else {
				$res = new DataResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
				$res->addHeader('WWW-Authenticate', 'Basic realm="Nextcloud", charset="UTF-8"');
				return $res;
			}
			if ($method !== 'Basic') {
				$res = new DataResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
				$res->addHeader('WWW-Authenticate', 'Basic realm="Nextcloud", charset="UTF-8"');
			} else {
				[$username, $password] = explode(':', base64_decode($credentials));
				if (false === $this->userSession->login($username, $password)) {
					$res = new DataResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
					$res->addHeader('WWW-Authenticate', 'Basic realm="Nextcloud", charset="UTF-8"');
					return $res;
				}
				$this->userId = $this->userSession->getUser()->getUID();
			}
		}

		if ($url !== null) {
			try {
				$bookmark = $this->bookmarkMapper->findByUrl($this->userId, $url);
			} catch (DoesNotExistException $e) {
				return new DataResponse(['data' => [], 'status' => 'success']);
			} catch (MultipleObjectsReturnedException $e) {
				return new DataResponse(['data' => [], 'status' => 'success']);
			}
			$bookmarks = [$this->_returnBookmarkAsArray($bookmark)];
			return new DataResponse(['data' => $bookmarks, 'status' => 'success']);
		}

		if (is_array($tags)) {
			$filterTag = $tags;
		} elseif (is_string($tags) && $tags !== '') {
			$tags = explode(',', $tags);
			$filterTag = [];
			foreach ($tags as $tag) {
				if (trim($tag) !== '') {
					$filterTag[] = trim($tag);
				}
			}
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

		if ($sortby) {
			$sqlSortColumn = $sortby;
		} else {
			$sqlSortColumn = 'lastmodified';
		}

		if ($folder !== null) {
			if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($folder, $this->userId, $this->request))) {
				return new DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
			}
			$result = $this->bookmarkMapper->findByFolder($folder, $sqlSortColumn, $offset, $limit);
		} else if (isset($this->userId)) {
			if ($untagged) {
				$result = $this->bookmarkMapper->findUntagged($this->userId, $sqlSortColumn, $offset, $limit);
			} else if ($tagsOnly && count($filterTag) > 0) {
				$result = $this->bookmarkMapper->findByTags($this->userId, $filterTag, $sqlSortColumn, $offset, $limit);
			} else {
				$result = $this->bookmarkMapper->findAll($this->userId, $filterTag, $conjunction, $sqlSortColumn, $offset, $limit);
			}
		} else {
			$this->authorizer->setCredentials($this->userId, $this->request);
			try {
				if ($untagged) {
					$result = $this->bookmarkMapper->findUntaggedInPublicFolder($this->authorizer->getToken(), $sqlSortColumn, $offset, $limit);
				} else if ($tagsOnly && count($filterTag) > 0) {
					$result = $this->bookmarkMapper->findByTagsInPublicFolder($this->authorizer->getToken(), $filterTag, $sqlSortColumn, $offset, $limit);
				} else {
					$result = $this->bookmarkMapper->findAllInPublicFolder($this->authorizer->getToken(), $filterTag, $conjunction, $sqlSortColumn, $offset, $limit);
				}
			} catch (DoesNotExistException $e) {
				return new DataResponse(['status' => 'error', 'data' => 'Not found'], Http::STATUS_BAD_REQUEST);
			} catch (MultipleObjectsReturnedException $e) {
				return new DataResponse(['status' => 'error', 'data' => 'Not found'], Http::STATUS_BAD_REQUEST);
			}
		}

		return new DataResponse([
			'data' => array_map(
				function ($bm) {
					return $this->_returnBookmarkAsArray($bm);
				}, $result
			),
			'status' => 'success',
		]);
	}

	/**
	 * @param string $url
	 * @param string $title
	 * @param string $description
	 * @param array $tags
	 * @param array $folders
	 * @return JSONResponse
	 *
	 * @throws AlreadyExistsError
	 * @throws UserLimitExceededError
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function newBookmark($url = "", $title = null, $description = "", $tags = [], $folders = []) {
		$permissions = Authorizer::PERM_ALL;
		foreach ($folders as $folder) {
			$permissions &= $this->authorizer->getPermissionsForFolder($folder, $this->userId, $this->request);
		}
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $permissions)) {
			return new JSONResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}

		if (isset($title)) {
			$title = trim($title);
		}

		// Inspect web page (do some light scraping)
		if (!isset($title)) {
			// allow only http(s) and (s)ftp
			$protocols = '/^(https?|s?ftp)\:\/\//i';
			if (preg_match($protocols, $url)) {
				$data = $this->linkExplorer->get($url);
			} else {
				// if no allowed protocol is given, evaluate https and https
				foreach (['https://', 'http://'] as $protocol) {
					$testUrl = $protocol . $url;
					$data = $this->linkExplorer->get($testUrl);
					if (isset($data['basic']) && isset($data['basic']['title'])) {
						break;
					}
				}
			}

			if (isset($data['url'])) {
				$url = $data['url'];
			}
			if ((!isset($title) || trim($title) === '' && strlen($title) !== 0)) {
				$title = isset($data['basic']) && isset($data['basic']['title']) ? $data['basic']['title'] : $url;
			}
			if (isset($data['basic']['description']) && (!isset($description) || trim($description) === '')) {
				$description = $data['basic']['description'];
			}
		}

		try {
			if (count($folders) === 0) {
				$bookmark = $this->_addBookmark($title, $url, $description, $this->userId, $tags, [-1]);
			} else {
				foreach ($folders as $folderId) {
					if (((int)$folderId) === -1) {
						$bookmark = $this->_addBookmark($title, $url, $description, $this->userId, $tags, [-1]);
					}
					try {
						$folder = $this->folderMapper->find($folderId);
					} catch (DoesNotExistException $e) {
						continue;
					} catch (MultipleObjectsReturnedException $e) {
						continue;
					}
					$bookmark = $this->_addBookmark($title, $url, $description, $folder->getUserId(), $tags, [$folder->getId()]);
				}
			}
		} catch (AlreadyExistsError $e) {
			// This is really unlikely, as we make sure to use the existing one if it already exists
			return new JSONResponse(['status' => 'error', 'data' => 'Bookmark already exists'], Http::STATUS_BAD_REQUEST);
		} catch (UrlParseError $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Invald URL'], Http::STATUS_BAD_REQUEST);
		} catch (UserLimitExceededError $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'User limit exceeded'], Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not add bookmark'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Internal server error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new JSONResponse(['item' => $this->_returnBookmarkAsArray($bookmark), 'status' => 'success']);
	}

	/**
	 * @param $title
	 * @param $url
	 * @param $description
	 * @param $userId
	 * @param $tags
	 * @param $folders
	 * @return Bookmark
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	private function _addBookmark($title, $url, $description, $userId, $tags, $folders) {
		$bookmark = new Bookmark();
		$bookmark->setTitle($title);
		$bookmark->setUrl($url);
		$bookmark->setDescription($description);
		$bookmark->setUserId($userId);
		$this->bookmarkMapper->insertOrUpdate($bookmark);
		$this->tagMapper->setOn($tags, $bookmark->getId());
		$this->folderMapper->setToFolders($bookmark->getId(), $folders);
		return $bookmark;
	}

	/**
	 * @param int $id
	 * @param string $url
	 * @param string $title
	 * @param string $description
	 * @param array $tags
	 * @param array $folders
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function editBookmark($id = null, $url = null, $title = null, $description = null, $tags = null, $folders = null) {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForBookmark($id, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$bookmark = $this->bookmarkMapper->find($id);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Multiple existing objects found']], Http::STATUS_BAD_REQUEST);
		}

		try {
			if (isset($url)) {
				$bookmark->setUrl($url);
			}
			if (isset($title)) {
				$bookmark->setTitle($title);
			}
			if (isset($description)) {
				$bookmark->setDescription($description);
			}

			if (isset($folders)) {
				$foreignFolders = array_filter($folders, function($folderId) use ($bookmark){
					try {
						$folder = $this->folderMapper->find($folderId);
						return ($bookmark->getUserId() !== $folder->getUserId());
					} catch (DoesNotExistException $e) {
						return false;
					} catch (MultipleObjectsReturnedException $e) {
						return false;
					}
				});
				$ownFolders = array_filter($folders, function($folderId) use ($bookmark){
					try {
						$folder = $this->folderMapper->find($folderId);
						return ($bookmark->getUserId() === $folder->getUserId());
					} catch (DoesNotExistException $e) {
						return false;
					} catch (MultipleObjectsReturnedException $e) {
						return false;
					}
				});

				$permissions = Authorizer::PERM_ALL;
				foreach ($foreignFolders as $folder) {
					$permissions &= $this->authorizer->getPermissionsForFolder($folder, $this->userId, $this->request);
				}
				if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $permissions)) {
					return new JSONResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
				}
				foreach ($foreignFolders as $folderId) {
					$folder = $this->folderMapper->find($folderId);
						$bookmark->setUserId($folder->getUserId());
						$this->_addBookmark($bookmark->getTitle(), $bookmark->getUrl(), $bookmark->getDescription(), $bookmark->getUserId(), isset($tags) ? $tags : [], [$folder->getId()]);
				}

				$this->folderMapper->setToFolders($bookmark->getId(), $ownFolders);
				if (count($ownFolders) === 0) {
					$this->bookmarkMapper->delete($bookmark);
					return new JSONResponse(['item' => $this->_returnBookmarkAsArray($bookmark), 'status' => 'success']);
				}
			}

			if (is_array($tags)) {
				$this->tagMapper->setOn($tags, $bookmark->getId());
			}
			$bookmark = $this->bookmarkMapper->update($bookmark);
		} catch (AlreadyExistsError $e) {
			// This is really unlikely, as we make sure to use the existing one if it already exists
			return new JSONResponse(['status' => 'error', 'data' => 'Bookmark already exists'], Http::STATUS_BAD_REQUEST);
		} catch (UrlParseError $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Invald URL'], Http::STATUS_BAD_REQUEST);
		} catch (UserLimitExceededError $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'User limit exceeded'], Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not add bookmark'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Internal server error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new JSONResponse(['item' => $this->_returnBookmarkAsArray($bookmark), 'status' => 'success']);
	}

	/**
	 * @param int $id
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function deleteBookmark($id) {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForBookmark($id, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$bookmark = $this->bookmarkMapper->find($id);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'success']);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Multiple objects found']], Http::STATUS_BAD_REQUEST);
		}
		$this->bookmarkMapper->delete($bookmark);
		return new JSONResponse(['status' => 'success']);
	}

	/**
	 *
	 * @param string $url
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @throws UrlParseError
	 */
	public function clickBookmark($url = "") {
		try {
			$bookmark = $this->bookmarkMapper->findByUrl($this->userId, $url);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Multiple objects found']], Http::STATUS_BAD_REQUEST);
		}

		$bookmark->incrementClickcount();
		try {
			$this->bookmarkMapper->update($bookmark);
		} catch (UrlParseError $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Could not save bookmark entry']], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(['status' => 'success'], Http::STATUS_OK);
	}

	/**
	 *
	 * @param int $id The id of the bookmark whose favicon shoudl be returned
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @return DataDisplayResponse|NotFoundResponse
	 * @throws \Exception
	 */
	public function getBookmarkImage($id) {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForBookmark($id, $this->userId, $this->request))) {
			return new NotFoundResponse();
		}
		try {
			$bookmark = $this->bookmarkMapper->find($id);
			if ($bookmark->getUserId() !== $this->userId) {
				return new NotFoundResponse();
			}
		} catch (DoesNotExistException $e) {
			return new NotFoundResponse();
		} catch (MultipleObjectsReturnedException $e) {
			return new NotFoundResponse();
		}

		$image = $this->bookmarkPreviewer->getImage($bookmark);
		if (isset($image)) {
			return $this->doImageResponse($image);
		}

		return new NotFoundResponse();
	}

	/**
	 *
	 * @param int $id The id of the bookmark whose image shoudl be returned
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @return DataDisplayResponse|NotFoundResponse|RedirectResponse
	 * @throws \Exception
	 */
	public function getBookmarkFavicon($id) {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForBookmark($id, $this->userId, $this->request))) {
			return new NotFoundResponse();
		}
		try {
			$bookmark = $this->bookmarkMapper->find($id);
		} catch (DoesNotExistException $e) {
			return new NotFoundResponse();
		} catch (MultipleObjectsReturnedException $e) {
			return new NotFoundResponse();
		}
		$image = $this->faviconPreviewer->getImage($bookmark);
		if (!isset($image)) {
			// Return a placeholder
			return new RedirectResponse($this->url->getAbsoluteURL('/svg/core/places/link?color=666666'));
		}
		return $this->doImageResponse($image);
	}

	/**
	 * @param $image
	 * @return DataDisplayResponse
	 * @throws \Exception
	 */
	public function doImageResponse($image) {
		$response = new DataDisplayResponse($image['data']);
		$response->addHeader('Content-Type', $image['contentType']);

		$response->cacheFor(self::IMAGES_CACHE_TTL);

		$expires = new DateTime();
		$expires->setTimestamp($this->timeFactory->getTime());
		$expires->add(new DateInterval('PT' . self::IMAGES_CACHE_TTL . 'S'));
		$response->addHeader('Expires', $expires->format(DateTime::RFC1123));
		$response->addHeader('Pragma', 'cache');

		return $response;
	}

	/**
	 *
	 * @param int $folder The id of the folder to import into
	 * @return JSONResponse
	 *
	 * @throws AlreadyExistsError
	 * @throws UserLimitExceededError
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function importBookmark($folder = -1) {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($folder, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => ['Insufficient permissions']]);
		}

		$full_input = $this->request->getUploadedFile("bm_import");

		$result = ['errors' => []];
		if (empty($full_input)) {
			$result['errors'][] = $this->l10n->t('No file provided for import');
			return new JSONResponse(['status' => 'error', 'data' => $result['errors']]);
		}

		$file = $full_input['tmp_name'];
		if ($full_input['type'] !== 'text/html') {
			$result['errors'][] = $this->l10n->t('Unsupported file type for import');
			return new JSONResponse(['status' => 'error', 'data' => $result['errors']]);
		}

		try {
			$result = $this->htmlImporter->importFile($this->userId, $file, $folder);
		} catch (UnauthorizedAccessError $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Unauthorized access']);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Folder not found']);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found']);
		}
		if (count($result['errors']) !== 0) {
			$this->logger->warning(var_export($result['errors'], true), ['app' => 'bookmarks']);
			return new JSONResponse(['status' => 'error', 'data' => $result['errors']]);
		}

		return new JSONResponse([
			'status' => 'success',
			'data' => $result['imported'],
		]);
	}

	/**
	 * Hit this GET endpoint to export bookmarks via your API client.
	 * http://server_ip/nextcloud/index.php/apps/bookmarks/public/rest/v2/bookmark/export
	 * Basic authentication required.
	 *
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @throws \OC\HintException
	 */
	public function exportBookmark() {
		try {
			$data = $this->htmlExporter->exportFolder($this->userId, -1);
		} catch (UnauthorizedAccessError $e) {
			// Will probably never happen
			return new JSONResponse(['status' => 'error', 'data' => 'Unauthorized access']);
		} catch (DoesNotExistException $e) {
			// Neither will this
			return new JSONResponse(['status' => 'error', 'data' => 'Not found']);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found']);
		}
		return new ExportResponse($data);
	}

}
