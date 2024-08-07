<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Controller;

use DateInterval;
use DateTime;
use Exception;
use OCA\Bookmarks\Contract\IImage;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\PublicFolder;
use OCA\Bookmarks\Db\PublicFolderMapper;
use OCA\Bookmarks\Db\TagMapper;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\HtmlParseError;
use OCA\Bookmarks\Exception\UnauthenticatedError;
use OCA\Bookmarks\Exception\UnauthorizedAccessError;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCA\Bookmarks\ExportResponse;
use OCA\Bookmarks\QueryParameters;
use OCA\Bookmarks\Service\Authorizer;
use OCA\Bookmarks\Service\BookmarkService;
use OCA\Bookmarks\Service\FolderService;
use OCA\Bookmarks\Service\HtmlExporter;
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
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class BookmarkController extends ApiController {
	private const IMAGES_CACHE_TTL = 7 * 24 * 60 * 60;

	/**
	 * @var IL10N
	 */
	private $l10n;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

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
	 * @var ITimeFactory
	 */
	private $timeFactory;

	/**
	 * @var IURLGenerator
	 */
	private $url;

	/**
	 * @var HtmlExporter
	 */
	private $htmlExporter;

	/**
	 * @var Authorizer
	 */
	private $authorizer;
	/**
	 * @var TreeMapper
	 */
	private $treeMapper;

	/**
	 * @var PublicFolderMapper
	 */
	private $publicFolderMapper;

	private $rootFolderId;

	/**
	 * @var BookmarkService
	 */
	private $bookmarks;
	/**
	 * @var FolderService
	 */
	private $folders;
	/**
	 * @var IRootFolder
	 */
	private $rootFolder;
	/**
	 * @var \OCA\Bookmarks\Service\LockManager
	 */
	private $lockManager;

	public function __construct(
		$appName, $request, IL10N $l10n, BookmarkMapper $bookmarkMapper, TagMapper $tagMapper, FolderMapper $folderMapper, TreeMapper $treeMapper, PublicFolderMapper $publicFolderMapper, ITimeFactory $timeFactory, LoggerInterface $logger, IURLGenerator $url, HtmlExporter $htmlExporter, Authorizer $authorizer, BookmarkService $bookmarks, FolderService $folders, IRootFolder $rootFolder, \OCA\Bookmarks\Service\LockManager $lockManager
	) {
		parent::__construct($appName, $request);
		$this->request = $request;
		$this->l10n = $l10n;
		$this->bookmarkMapper = $bookmarkMapper;
		$this->tagMapper = $tagMapper;
		$this->folderMapper = $folderMapper;
		$this->treeMapper = $treeMapper;
		$this->publicFolderMapper = $publicFolderMapper;
		$this->timeFactory = $timeFactory;
		$this->logger = $logger;
		$this->url = $url;
		$this->htmlExporter = $htmlExporter;
		$this->authorizer = $authorizer;
		$this->bookmarks = $bookmarks;
		$this->folders = $folders;
		$this->rootFolder = $rootFolder;
		$this->lockManager = $lockManager;

		$this->authorizer->setCORS(true);
	}

	/**
	 * @param Bookmark $bookmark
	 *
	 * @return ((int|mixed)[]|mixed)[]
	 *
	 * @psalm-return array{folders: array<array-key, int>, tags: array<array-key, mixed>|mixed, archivedFilePath?: mixed|string, archivedFileType?: mixed|string, ...<array-key, mixed>}
	 */
	private function _returnBookmarkAsArray(Bookmark $bookmark): array {
		$array = $bookmark->toArray();
		if (!isset($array['folders'])) {
			$array['folders'] = array_map(function (Folder $folder) {
				return $this->toExternalFolderId($folder->getId());
			}, $this->treeMapper->findParentsOf(TreeMapper::TYPE_BOOKMARK, $bookmark->getId()));
		} else {
			$array['folders'] = array_map(function ($id) {
				return $this->toExternalFolderId($id);
			}, $array['folders']);
		}
		if (!isset($array['tags'])) {
			$array['tags'] = $this->tagMapper->findByBookmark($bookmark->getId());
		}
		if ($array['archivedFile'] !== 0) {
			$results = $this->rootFolder->getById($array['archivedFile']);
			if (count($results)) {
				$array['archivedFilePath'] = $results[0]->getPath();
				$array['archivedFileType'] = $results[0]->getMimePart();
			}
		}
		return $array;
	}

	/**
	 * @return int|null
	 */
	private function _getRootFolderId(): ?int {
		if ($this->rootFolderId !== null) {
			return $this->rootFolderId;
		}
		if ($this->authorizer->getUserId() !== null) {
			$this->rootFolderId = $this->folderMapper->findRootFolder($this->authorizer->getUserId())->getId();
		}
		if ($this->authorizer->getToken() !== null) {
			try {
				/**
				 * @var $publicFolder PublicFolder
				 */
				$publicFolder = $this->publicFolderMapper->find($this->authorizer->getToken());
				$this->rootFolderId = $publicFolder->getFolderId();
			} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
				$this->logger->error($e->getMessage()."\n".$e->getMessage());
			}
		}
		return $this->rootFolderId;
	}

	/**
	 * @param int $external
	 *
	 * @return int|null
	 */
	private function toInternalFolderId(int $external): ?int {
		if ($external === -1) {
			return $this->_getRootFolderId();
		}
		return $external;
	}

	/**
	 * @param int $internal
	 * @return int
	 */
	private function toExternalFolderId(int $internal): int {
		if ($internal === $this->_getRootFolderId()) {
			return -1;
		}
		return $internal;
	}

	/**
	 * @param string $id
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @PublicPage
	 */
	public function getSingleBookmark($id): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForBookmark($id, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
		}
		try {
			/**
			 * @var $bm Bookmark
			 */
			$bm = $this->bookmarkMapper->find($id);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
		}
		return new JSONResponse(['item' => $this->_returnBookmarkAsArray($bm), 'status' => 'success']);
	}

	/**
	 * @param int $page
	 * @param string[] $tags
	 * @param string $conjunction
	 * @param string $sortby
	 * @param array $search
	 * @param int $limit
	 * @param bool $untagged
	 * @param int|null $folder
	 * @param string|null $url
	 * @param bool|null $unavailable
	 * @param bool|null $archived
	 * @param bool|null $duplicated
	 * @param bool|null $recursive
	 * @param bool|null $deleted
	 * @return DataResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @PublicPage
	 */
	public function getBookmarks(
		$page = 0,
		$tags = null,
		$conjunction = 'or',
		$sortby = '',
		$search = [],
		$limit = 10,
		$untagged = false,
		?int $folder = null,
		?string $url = null,
		?bool $unavailable = null,
		?bool $archived = null,
		?bool $duplicated = null,
		bool $recursive = false,
		bool $deleted = false,
	): DataResponse {
		$this->registerResponder('rss', function (DataResponse $res) {
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
			/** @var array<string, mixed> $headers */
			$headers = $res->getHeaders();
			$response->setHeaders($headers);
			$response->setStatus($res->getStatus());
			if (stripos($this->request->getHeader('accept'), 'application/rss+xml') !== false) {
				$response->addHeader('Content-Type', 'application/rss+xml');
			} else {
				$response->addHeader('Content-Type', 'text/xml; charset=UTF-8');
			}
			return $response;
		});

		$this->authorizer->setCredentials($this->request);
		if ($this->authorizer->getUserId() === null && $this->authorizer->getToken() === null) {
			$res = new DataResponse(['status' => 'error', 'data' => ['Please authenticate first']], Http::STATUS_UNAUTHORIZED);
			$res->addHeader('WWW-Authenticate', 'Basic realm="Nextcloud", charset="UTF-8"');
			return $res;
		}
		$userId = $this->authorizer->getUserId();

		if (is_array($tags)) {
			$filterTag = $tags;
		} else {
			$filterTag = [];
		}

		// set query params
		$params = new QueryParameters();
		if ($url !== null) {
			$params->setUrl($url);
		}
		if ($unavailable !== null) {
			$params->setUnavailable($unavailable);
		}
		if ($untagged !== null) {
			$params->setUntagged($untagged);
		}
		if ($archived !== null) {
			$params->setArchived($archived);
		}
		if ($duplicated !== null) {
			$params->setDuplicated($duplicated);
		}
		// search soft deleted bookmarks
		$params->setSoftDeleted($deleted);
		// search bookmarks only in soft-deleted folders
		$params->setSoftDeletedFolders($deleted);
		$params->setTags($filterTag);
		$params->setSearch($search);
		$params->setConjunction($conjunction);
		$params->setOffset($page * $limit);
		$params->setLimit($limit);
		if ($page === -1) {
			$params->setLimit(-1);
			$params->setOffset(0);
		}
		if ($sortby) {
			$params->setSortBy($sortby);
		} else {
			$params->setSortBy('lastmodified');
		}

		if ($folder !== null) {
			if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($folder, $this->request))) {
				return new DataResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
			}
			try {
				/** @var Folder $folderEntity */
				$folderEntity = $this->folderMapper->find($this->toInternalFolderId($folder));
				// IMPORTANT:
				// If we have this user's permission to see the contents of their folder, simply set the userID
				// to theirs
				$userId = $folderEntity->getUserId();
			} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
				return new DataResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
			}
			$params->setFolder($this->toInternalFolderId($folder));
			$params->setRecursive($recursive);
		}

		if ($userId !== null) {
			try {
				$result = $this->bookmarkMapper->findAll($userId, $params);
			} catch (UrlParseError $e) {
				return new DataResponse(['status' => 'error', 'data' => ['Failed to parse URL']], Http::STATUS_BAD_REQUEST);
			}
		} else {
			try {
				$result = $this->bookmarkMapper->findAllInPublicFolder($this->authorizer->getToken(), $params);
			} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
				return new DataResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
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
	 * @param string|null $title
	 * @param string $description
	 * @param array $tags
	 * @param array $folders
	 * @param string $target
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @PublicPage
	 */
	public function newBookmark($url = '', $title = null, $description = null, $tags = null, $folders = [], $target = null): JSONResponse {
		$permissions = Authorizer::PERM_ALL;
		$this->authorizer->setCredentials($this->request);
		foreach ($folders as $folder) {
			$permissions &= $this->authorizer->getPermissionsForFolder($folder, $this->request);
		}
		if (!Authorizer::hasPermission(Authorizer::PERM_WRITE, $permissions) || $this->authorizer->getUserId() === null) {
			return new JSONResponse(['status' => 'error', 'data' => ['Could not add bookmark']], Http::STATUS_BAD_REQUEST);
		}

		try {
			$folders = array_map(function ($folderId) {
				return $this->toInternalFolderId($folderId);
			}, $folders);
			$bookmark = $this->bookmarks->create($this->authorizer->getUserId(), $target ?: $url, $title, $description, $tags, $folders);
			return new JSONResponse(['item' => $this->_returnBookmarkAsArray($bookmark), 'status' => 'success']);
		} catch (AlreadyExistsError $e) {
			// This is really unlikely, as we make sure to use the existing one if it already exists
			return new JSONResponse(['status' => 'error', 'data' => ['Bookmark already exists']], Http::STATUS_BAD_REQUEST);
		} catch (UrlParseError $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Invalid URL']], Http::STATUS_BAD_REQUEST);
		} catch (UserLimitExceededError $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['User limit exceeded']], Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException|\OCP\DB\Exception) {
			return new JSONResponse(['status' => 'error', 'data' => ['Could not add bookmark']], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Internal server error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Internal server error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}


	/**
	 * @param int|null $id
	 * @param string|null $url
	 * @param string|null $title
	 * @param string|null $description
	 * @param array|null $tags
	 * @param array|null $folders
	 * @param string|null $target
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @PublicPage
	 */
	public function editBookmark($id = null, $url = null, $title = null, $description = null, $tags = null, $folders = null, $target = null): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForBookmark($id, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => ['Could not edit bookmark']], Http::STATUS_NOT_FOUND);
		}

		try {
			if (isset($folders)) {
				$folders = array_map(function ($folderId) {
					return $this->toInternalFolderId((int)$folderId);
				}, $folders);
				$permissions = Authorizer::PERM_ALL;
				foreach ($folders as $folder) {
					$permissions &= $this->authorizer->getPermissionsForFolder($folder, $this->request);
				}
				if (!Authorizer::hasPermission(Authorizer::PERM_WRITE, $permissions)) {
					return new JSONResponse(['status' => 'error', 'data' => ['Insufficient permissions']], Http::STATUS_FORBIDDEN);
				}
			}
			$bookmark = $this->bookmarks->update($this->authorizer->getUserId(), $id, $target ?: $url, $title, $description, $tags, $folders);
			return new JSONResponse(['item' => $bookmark ? $this->_returnBookmarkAsArray($bookmark) : null, 'status' => 'success']);
		} catch (AlreadyExistsError $e) {
			// This is really unlikely, as we make sure to use the existing one if it already exists
			return new JSONResponse(['status' => 'error', 'data' => ['Bookmark already exists']], Http::STATUS_BAD_REQUEST);
		} catch (UrlParseError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return new JSONResponse(['status' => 'error', 'data' => ['Invald URL']], Http::STATUS_BAD_REQUEST);
		} catch (UserLimitExceededError $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['User limit exceeded']], Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Could not edit bookmark']], Http::STATUS_NOT_FOUND);
		} catch (\OCP\DB\Exception $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Could not edit bookmark']], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Internal server error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Could not add bookmark']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @param int $id
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @PublicPage
	 */
	public function deleteBookmark($id): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForBookmark($id, $this->request))) {
			return new JSONResponse(['status' => 'success']);
		}

		try {
			$this->bookmarkMapper->find($id);
		} catch (DoesNotExistException | MultipleObjectsReturnedException) {
			return new JSONResponse(['status' => 'success']);
		}

		try {
			$this->bookmarks->delete($id);
		} catch (UnsupportedOperation) {
			return new JSONResponse(['status' => 'error', 'data' => ['Unsupported operation']], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (DoesNotExistException) {
			return new JSONResponse(['status' => 'success']);
		} catch (MultipleObjectsReturnedException) {
			return new JSONResponse(['status' => 'error', 'data' => ['Multiple objects found']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new JSONResponse(['status' => 'success']);
	}

	/**
	 *
	 * @param string $url
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @PublicPage
	 */
	public function clickBookmark($url = ''): JSONResponse {
		$this->authorizer->setCredentials($this->request);
		if ($this->authorizer->getUserId() === null) {
			return new JSONResponse(['status' => 'error', 'data' => ['Unauthenticated']], Http::STATUS_FORBIDDEN);
		}
		try {
			$bookmark = $this->bookmarks->findByUrl($this->authorizer->getUserId(), $url);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
		} catch (UrlParseError $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Failed to parse URL']], Http::STATUS_BAD_REQUEST);
		}

		if ($bookmark->getUserId() !== $this->authorizer->getUserId()) {
			return new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->bookmarks->click($bookmark->getId());
		} catch (UrlParseError $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Failed to parse URL']], Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(['status' => 'success'], Http::STATUS_OK);
	}

	/**
	 *
	 * @param int $id The id of the bookmark whose favicon should be returned
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @PublicPage
	 * @BruteForceProtection(action=bookmarks#getBookmarkImage)
	 * @return DataDisplayResponse|NotFoundResponse|RedirectResponse
	 */
	public function getBookmarkImage($id) {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForBookmark($id, $this->request))) {
			$res = new NotFoundResponse();
			$res->throttle();
			return $res;
		}
		try {
			$image = $this->bookmarks->getImage($id);
			if ($image === null) {
				return new NotFoundResponse();
			}
			return $this->doImageResponse($image);
		} catch (DoesNotExistException | MultipleObjectsReturnedException | Exception $e) {
			return new NotFoundResponse();
		}
	}

	/**
	 *
	 * @param int $id The id of the bookmark whose image should be returned
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection(action=bookmarks#getBookmarkFavicon)
	 * @PublicPage
	 * @return DataDisplayResponse|NotFoundResponse|DataResponse
	 */
	public function getBookmarkFavicon($id) {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForBookmark($id, $this->request))) {
			$res = new NotFoundResponse();
			$res->throttle();
			return $res;
		}
		try {
			$image = $this->bookmarks->getFavicon($id);
			if ($image === null) {
				return new NotFoundResponse();
			}
			return $this->doImageResponse($image);
		} catch (DoesNotExistException | MultipleObjectsReturnedException | Exception $e) {
			return new NotFoundResponse();
		}
	}

	/**
	 * @param $image
	 *
	 * @return DataDisplayResponse|NotFoundResponse
	 *
	 * @throws Exception
	 */
	public function doImageResponse(?IImage $image) {
		if ($image === null || $image->getData() === null) {
			return new NotFoundResponse();
		}
		$response = new DataDisplayResponse($image->getData());
		$response->addHeader('Content-Type', $image->getContentType());

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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection(action=bookmarks#importBookmark)
	 * @PublicPage
	 */
	public function importBookmark($folder = null): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_WRITE, $this->authorizer->getPermissionsForFolder($folder ?? -1, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Folder not found']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		}

		$full_input = $this->request->getUploadedFile('bm_import');

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

		if ($folder !== null) {
			$folder = $this->toInternalFolderId($folder);
		} else {
			$folder = $this->_getRootFolderId();
		}

		try {
			$result = $this->folders->importFile($this->authorizer->getUserId(), $file, $folder);
		} catch (UnauthorizedAccessError $e) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Folder not found']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Folder not found']], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Multiple objects found']], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (HtmlParseError $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Parse error: Invalid HTML']], Http::STATUS_BAD_REQUEST);
		} catch (UserLimitExceededError $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Could not import all bookmarks: User limit Exceeded']], Http::STATUS_BAD_REQUEST);
		} catch (AlreadyExistsError $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Could not import all bookmarks: Already exists']], Http::STATUS_BAD_REQUEST);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Internal server error']], Http::STATUS_INTERNAL_SERVER_ERROR);
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
	 * @return ExportResponse|JSONResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection(action=bookmarks#exportBookmark)
	 * @PublicPage
	 */
	public function exportBookmark() {
		$this->authorizer->setCredentials($this->request);
		if ($this->authorizer->getUserId() === null) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']]);
			$res->throttle();
			return $res;
		}
		try {
			$data = $this->htmlExporter->exportFolder($this->authorizer->getUserId(), $this->_getRootFolderId());
		} catch (UnauthorizedAccessError $e) {
			// Will probably never happen
			return new JSONResponse(['status' => 'error', 'data' => ['Not found']]);
		} catch (DoesNotExistException $e) {
			// Neither will this
			return new JSONResponse(['status' => 'error', 'data' => ['Not found']]);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Multiple objects found']]);
		}
		return new ExportResponse($data);
	}


	/**
	 * @param int $folder
	 * @return JSONResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection(action=bookmarks#countBookmarks)
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function countBookmarks(int $folder): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($folder, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}

		$folder = $this->toInternalFolderId($folder);
		if ($folder === null) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}
		$count = $this->treeMapper->countBookmarksInFolder($folder);
		return new JSONResponse(['status' => 'success', 'item' => $count]);
	}

	/**
	 * @return JSONResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection(action=bookmarks#countUnavailable)
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function countUnavailable(): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder(-1, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Unauthorized']], Http::STATUS_FORBIDDEN);
			$res->throttle();
			return $res;
		}

		try {
			$count = $this->bookmarkMapper->countUnavailable($this->authorizer->getUserId());
		} catch (\OCP\DB\Exception $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new JSONResponse(['status' => 'success', 'item' => $count]);
	}

	/**
	 * @return JSONResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection(action=bookmarks#countArchived)
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function countArchived(): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder(-1, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Unauthorized']], Http::STATUS_FORBIDDEN);
			$res->throttle();
			return $res;
		}

		$count = $this->bookmarkMapper->countArchived($this->authorizer->getUserId());
		return new JSONResponse(['status' => 'success', 'item' => $count]);
	}

	/**
	 * @return JSONResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection(action=bookmarks#countDuplicated)
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function countDuplicated(): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder(-1, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Unauthorized']], Http::STATUS_FORBIDDEN);
			$res->throttle();
			return $res;
		}

		$count = $this->bookmarkMapper->countDuplicated($this->authorizer->getUserId());
		return new JSONResponse(['status' => 'success', 'item' => $count]);
	}

	/**
	 * @return JSONResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection(action=bookmarks#acquireLock)
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function acquireLock(): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_WRITE, $this->authorizer->getPermissionsForFolder(-1, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Unauthorized']], Http::STATUS_FORBIDDEN);
			$res->throttle();
			return $res;
		}

		try {
			if ($this->lockManager->getLock($this->authorizer->getUserId()) === true) {
				return new JSONResponse(['status' => 'error', 'data' => ['Resource is already locked']], Http::STATUS_LOCKED);
			}

			$this->lockManager->setLock($this->authorizer->getUserId(), true);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage());
			return new JSONResponse(['status' => 'error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new JSONResponse(['status' => 'success']);
	}

	/**
	 * @return JSONResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection(action=bookmarks#releaseLock)
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function releaseLock(): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_WRITE, $this->authorizer->getPermissionsForFolder(-1, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Unauthorized']], Http::STATUS_FORBIDDEN);
			$res->throttle();
			return $res;
		}

		try {
			if ($this->lockManager->getLock($this->authorizer->getUserId()) === false) {
				return new JSONResponse(['status' => 'success']);
			}

			$this->lockManager->setLock($this->authorizer->getUserId(), false);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage());
			return new JSONResponse(['status' => 'error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new JSONResponse(['status' => 'success']);
	}

	/**
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection(action=bookmarks#getDeletedBookmarks)
	 * @PublicPage
	 */
	public function getDeletedBookmarks(): DataResponse {
		$this->authorizer->setCredentials($this->request);
		if ($this->authorizer->getUserId() === null) {
			$res = new Http\DataResponse(['status' => 'error', 'data' => ['Unauthorized']], Http::STATUS_FORBIDDEN);
			$res->throttle();
			return $res;
		}
		try {
			$bookmarks = $this->treeMapper->getSoftDeletedRootItems($this->authorizer->getUserId(), TreeMapper::TYPE_BOOKMARK);
		} catch (UrlParseError|\OCP\DB\Exception $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new Http\DataResponse(['status' => 'success', 'data' => array_map(fn ($bookmark) => $this->_returnBookmarkAsArray($bookmark), $bookmarks)]);
	}

	/**
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection(action=bookmarks#countAllClicks)
	 * @return DataResponse
	 */
	public function countAllClicks(): DataResponse {
		$this->authorizer->setCredentials($this->request);
		if ($this->authorizer->getUserId() === null) {
			$res = new Http\DataResponse(['status' => 'error', 'data' => ['Unauthorized']], Http::STATUS_FORBIDDEN);
			$res->throttle();
			return $res;
		}
		try {
			$count = $this->bookmarkMapper->countAllClicks($this->authorizer->getUserId());
			return new DataResponse(['status' => 'success', 'item' => $count]);
		} catch (\OCP\DB\Exception $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection(action=bookmarks#countWithClicks)
	 * @return DataResponse
	 */
	public function countWithClicks(): DataResponse {
		$this->authorizer->setCredentials($this->request);
		if ($this->authorizer->getUserId() === null) {
			$res = new Http\DataResponse(['status' => 'error', 'data' => ['Unauthorized']], Http::STATUS_FORBIDDEN);
			$res->throttle();
			return $res;
		}
		try {
			$count = $this->bookmarkMapper->countWithClicks($this->authorizer->getUserId());
			return new DataResponse(['status' => 'success', 'item' => $count]);
		} catch (\OCP\DB\Exception $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
