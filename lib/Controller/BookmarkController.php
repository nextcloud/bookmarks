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
use OCA\Bookmarks\Service\LockManager;
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
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class BookmarkController extends ApiController {
	private const IMAGES_CACHE_TTL = 7 * 24 * 60 * 60;
	private ?int $rootFolderId = null;

	public function __construct(
		string $appName,
		IRequest $request,
		private IL10N $l10n,
		private BookmarkMapper $bookmarkMapper,
		private TagMapper $tagMapper,
		private FolderMapper $folderMapper,
		private TreeMapper $treeMapper,
		private PublicFolderMapper $publicFolderMapper,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
		private HtmlExporter $htmlExporter,
		private Authorizer $authorizer,
		private BookmarkService $bookmarks,
		private FolderService $folders,
		private IRootFolder $rootFolder,
		private LockManager $lockManager,
	) {
		parent::__construct($appName, $request);
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
			try {
				$array['tags'] = $this->tagMapper->findByBookmark($bookmark->getId());
			} catch (\OCP\DB\Exception $e) {
				$this->logger->warning('Could not load bookmark\'s tags: ' . $e->getMessage(), ['exception' => $e]);
				$array['tags'] = [];
			}
		}
		if ($array['archivedFile'] !== 0 && $array['archivedFile'] !== null && $this->authorizer->getUserId() === $bookmark->getUserId()) {
			$result = $this->rootFolder->getFirstNodeById($array['archivedFile']);
			if ($result !== null) {
				$array['archivedFilePath'] = $result->getPath();
				$array['archivedFileType'] = $result->getMimePart();
			}
		}
		return $array;
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	private function _getRootFolderId(): int {
		if ($this->authorizer->getToken() !== null) {
			try {
				$publicFolder = $this->publicFolderMapper->find($this->authorizer->getToken());
				$this->rootFolderId = $publicFolder->getFolderId();
				return $this->rootFolderId;
			} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
			}
		}
		if ($this->authorizer->getUserId() !== null) {
			try {
				$this->rootFolderId = $this->folderMapper->findRootFolder($this->authorizer->getUserId())->getId();
				return $this->rootFolderId;
			} catch (\OCP\DB\Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
			}
		}

		throw new \OCP\DB\Exception('Could not load root folder');
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	private function toInternalFolderId(int $external): int {
		if ($external === -1) {
			return $this->_getRootFolderId();
		}
		return $external;
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	private function toExternalFolderId(int $internal): int {
		if ($internal === $this->_getRootFolderId()) {
			return -1;
		}
		return $internal;
	}

	/**
	 * @return JSONResponse
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\FrontpageRoute(verb: 'GET', url: '/public/rest/v2/bookmark/{id}')]
	public function getSingleBookmark(int $id): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForBookmark($id, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}
		try {
			$bm = $this->bookmarkMapper->find($id);
		} catch (DoesNotExistException) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		} catch (MultipleObjectsReturnedException) {
			return new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
		}
		return new JSONResponse(['item' => $this->_returnBookmarkAsArray($bm), 'status' => 'success']);
	}

	/**
	 * @return DataResponse
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\FrontpageRoute(verb: 'GET', url: '/public/rest/v2/bookmark')]
	public function getBookmarks(
		int $page = 0,
		?array $tags = null,
		string $conjunction = 'or',
		string $sortby = '',
		array $search = [],
		int $limit = 10,
		bool $untagged = false,
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
				$res = new DataResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
				$res->throttle();
				return $res;
			}
			try {
				$folderEntity = $this->folderMapper->find($this->toInternalFolderId($folder));
				// IMPORTANT:
				// If we have this user's permission to see the contents of their folder, simply set the userID
				// to theirs
				$userId = $folderEntity->getUserId();
			} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
				$res = new DataResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
				$res->throttle();
				return $res;
			} catch (\OCP\DB\Exception) {
				return new DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
			try {
				$params->setFolder($this->toInternalFolderId($folder));
			} catch (\OCP\DB\Exception) {
				return new DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
			$params->setRecursive($recursive);
		}

		if ($userId !== null) {
			try {
				$result = $this->bookmarkMapper->findAll($userId, $params);
			} catch (UrlParseError) {
				return new DataResponse(['status' => 'error', 'data' => ['Failed to parse URL']], Http::STATUS_BAD_REQUEST);
			} catch (\OCP\DB\Exception) {
				return new DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
		} else {
			try {
				$result = $this->bookmarkMapper->findAllInPublicFolder($this->authorizer->getToken(), $params);
			} catch (DoesNotExistException|MultipleObjectsReturnedException) {
				return new DataResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
			} catch (\OCP\DB\Exception) {
				return new DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
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
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\FrontpageRoute(verb: 'POST', url: '/public/rest/v2/bookmark')]
	public function newBookmark(string $url = '', ?string $title = null, ?string $description = null, ?array $tags = null, array $folders = [], ?string $target = null): JSONResponse {
		$permissions = Authorizer::PERM_ALL;
		$this->authorizer->setCredentials($this->request);
		foreach ($folders as $folder) {
			$permissions &= $this->authorizer->getPermissionsForFolder($folder, $this->request);
		}
		if (!Authorizer::hasPermission(Authorizer::PERM_WRITE, $permissions) || $this->authorizer->getUserId() === null) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Could not add bookmark']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
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
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\FrontpageRoute(verb: 'PUT', url: '/public/rest/v2/bookmark/{id}')]
	public function editBookmark(?int $id = null, ?string $url = null, ?string $title = null, ?string $description = null, ?array $tags = null, ?array $folders = null, ?string $target = null): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForBookmark($id, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Could not edit bookmark']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
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
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\FrontpageRoute(verb: 'DELETE', url: '/public/rest/v2/bookmark/{id}')]
	public function deleteBookmark(int $id): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForBookmark($id, $this->request))) {
			$res = new JSONResponse(['status' => 'success']);
			$res->throttle();
			return $res;
		}

		try {
			$this->bookmarkMapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			$res = new JSONResponse(['status' => 'success']);
			$res->throttle();
			return $res;
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
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\BruteForceProtection(action: 'click')]
	#[Http\Attribute\FrontpageRoute(verb: 'POST', url: '/public/rest/v2/bookmark/click')]
	public function clickBookmark(string $url = ''): JSONResponse {
		$this->authorizer->setCredentials($this->request);
		if ($this->authorizer->getUserId() === null) {
			return new JSONResponse(['status' => 'error', 'data' => ['Unauthenticated']], Http::STATUS_FORBIDDEN);
		}
		try {
			$bookmark = $this->bookmarks->findByUrl($this->authorizer->getUserId(), $url);
		} catch (DoesNotExistException) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		} catch (UrlParseError) {
			return new JSONResponse(['status' => 'error', 'data' => ['Failed to parse URL']], Http::STATUS_BAD_REQUEST);
		} catch (\OCP\DB\Exception) {
			return new JSONResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if ($bookmark->getUserId() !== $this->authorizer->getUserId()) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		}

		try {
			$this->bookmarks->click($bookmark->getId());
		} catch (UrlParseError) {
			return new JSONResponse(['status' => 'error', 'data' => ['Failed to parse URL']], Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(['status' => 'success'], Http::STATUS_OK);
	}

	/**
	 * @return DataDisplayResponse|NotFoundResponse|RedirectResponse
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\BruteForceProtection(action: 'image')]
	#[Http\Attribute\FrontpageRoute(verb: 'GET', url: '/public/rest/v2/bookmark/{id}/image')]
	public function getBookmarkImage(int $id) {
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
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			return new NotFoundResponse();
		}
	}

	/**
	 * @return DataDisplayResponse|NotFoundResponse|DataResponse
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\BruteForceProtection(action: 'favicon')]
	#[Http\Attribute\FrontpageRoute(verb: 'GET', url: '/public/rest/v2/bookmark/{id}/favicon')]
	public function getBookmarkFavicon(int $id) {
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
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			return new NotFoundResponse();
		}
	}

	/**
	 * @return DataDisplayResponse|NotFoundResponse
	 *
	 * @throws Exception
	 */
	private function doImageResponse(?IImage $image) {
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
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\BruteForceProtection(action: 'import')]
	#[Http\Attribute\FrontpageRoute(verb: 'POST', url: '/public/rest/v2/bookmark/import')]
	#[Http\Attribute\FrontpageRoute(verb: 'POST', url: '/public/rest/v2/folder/{folder}/import')]
	public function importBookmark(?int $folder = null): JSONResponse {
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

		if (empty($full_input['tmp_name']) || $full_input['error']) {
			$result['errors'][] = $this->l10n->t('Failed to upload file');
			return new JSONResponse(['status' => 'error', 'data' => $result['errors']]);
		}

		$file = $full_input['tmp_name'];
		if ($full_input['type'] !== 'text/html' && $full_input['type'] !== 'application/html') {
			$result['errors'][] = $this->l10n->t('Unsupported file type for import: ' . $full_input['type']);
			return new JSONResponse(['status' => 'error', 'data' => $result['errors']]);
		}

		try {
			if ($folder !== null) {
				$folder = $this->toInternalFolderId($folder);
			} else {
				$folder = $this->_getRootFolderId();
			}
		} catch (\OCP\DB\Exception) {
			return new JSONResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		try {
			$result = $this->folders->importFile($this->authorizer->getUserId(), $file, $folder);
		} catch (UnauthorizedAccessError|DoesNotExistException) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Folder not found']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Multiple objects found']], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (HtmlParseError $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Parse error: Invalid HTML']], Http::STATUS_BAD_REQUEST);
		} catch (UserLimitExceededError $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Could not import all bookmarks: User limit Exceeded']], Http::STATUS_BAD_REQUEST);
		} catch (AlreadyExistsError $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Could not import all bookmarks: Already exists']], Http::STATUS_BAD_REQUEST);
		} catch (UnsupportedOperation|\OCP\DB\Exception $e) {
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
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\BruteForceProtection(action: 'export')]
	#[Http\Attribute\FrontpageRoute(verb: 'GET', url: '/public/rest/v2/bookmark/export')]
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
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\BruteForceProtection(action: 'count')]
	#[Http\Attribute\FrontpageRoute(verb: 'GET', url: '/public/rest/v2/folder/{folder}/count')]
	public function countBookmarks(int $folder): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($folder, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}

		try {
			$folder = $this->toInternalFolderId($folder);
		} catch (\OCP\DB\Exception $e) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}
		$count = $this->treeMapper->countBookmarksInFolder($folder);
		return new JSONResponse(['status' => 'success', 'item' => $count]);
	}

	/**
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\BruteForceProtection(action: 'countUnavailable')]
	#[Http\Attribute\FrontpageRoute(verb: 'GET', url: '/public/rest/v2/bookmark/unavailable')]
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
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\BruteForceProtection(action: 'countArchived')]
	#[Http\Attribute\FrontpageRoute(verb: 'GET', url: '/public/rest/v2/bookmark/archived')]
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
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\BruteForceProtection(action: 'countDuplicated')]
	#[Http\Attribute\FrontpageRoute(verb: 'GET', url: '/public/rest/v2/bookmark/duplicated')]
	public function countDuplicated(): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder(-1, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Unauthorized']], Http::STATUS_FORBIDDEN);
			$res->throttle();
			return $res;
		}

		try {
			$count = $this->bookmarkMapper->countDuplicated($this->authorizer->getUserId());
		} catch (\OCP\DB\Exception) {
			return new JSONResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new JSONResponse(['status' => 'success', 'item' => $count]);
	}

	/**
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\BruteForceProtection(action: 'countDeleted')]
	#[Http\Attribute\FrontpageRoute(verb: 'GET', url: '/public/rest/v2/bookmark/deletedCount')]
	public function countDeleted(): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder(-1, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Unauthorized']], Http::STATUS_FORBIDDEN);
			$res->throttle();
			return $res;
		}

		try {
			$count = $this->bookmarkMapper->countDeleted($this->authorizer->getUserId());
		} catch (\OCP\DB\Exception) {
			return new JSONResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new JSONResponse(['status' => 'success', 'item' => $count]);
	}

	/**
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\BruteForceProtection(action: 'acquireLock')]
	#[Http\Attribute\FrontpageRoute(verb: 'POST', url: '/public/rest/v2/lock')]
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
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\BruteForceProtection(action: 'releaseLock')]
	#[Http\Attribute\FrontpageRoute(verb: 'DELETE', url: '/public/rest/v2/lock')]
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
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\BruteForceProtection(action: 'getDeletedBookmarks')]
	#[Http\Attribute\FrontpageRoute(verb: 'GET', url: '/public/rest/v2/bookmark/deleted')]
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
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\BruteForceProtection(action: 'countAllClicks')]
	#[Http\Attribute\FrontpageRoute(verb: 'GET', url: '/public/rest/v2/bookmark/totalClicks')]
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
	 * @throws UnauthenticatedError
	 */
	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\BruteForceProtection(action: 'countWithClicks')]
	#[Http\Attribute\FrontpageRoute(verb: 'GET', url: '/public/rest/v2/bookmark/withClicks')]
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
