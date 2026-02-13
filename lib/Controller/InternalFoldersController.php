<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Controller;

use OCA\Bookmarks\Service\Authorizer;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class InternalFoldersController extends ApiController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ?string $userId,
		private FoldersController $controller,
		Authorizer $authorizer,
	) {
		parent::__construct($appName, $request);
		if ($this->userId !== null) {
			$authorizer->setUserId($userId);
		}
		$authorizer->setCORS(false);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/folder')]
	public function addFolder(string $title = '', int $parent_folder = -1): JSONResponse {
		return $this->controller->addFolder($title, $parent_folder);
	}



	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/folder/{folderId}/childorder')]
	public function getFolderChildrenOrder(int $folderId, int $layers = 0): JSONResponse {
		return $this->controller->getFolderChildrenOrder($folderId, $layers);
	}


	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PATCH', url: '/folder/{folderId}/childorder')]
	public function setFolderChildrenOrder(int $folderId, array $data = []): JSONResponse {
		return $this->controller->setFolderChildrenOrder($folderId, $data);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/folder/{folderId}', requirements: ['folderId' => '[0-9]+'])]
	public function deleteFolder(int $folderId, bool $hardDelete = false): JSONResponse {
		return $this->controller->deleteFolder($folderId, $hardDelete);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/folder/{folderId}/undelete')]
	public function undeleteFolder(int $folderId): JSONResponse {
		return $this->controller->undeleteFolder($folderId);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/folder/{folderId}/bookmarks/{bookmarkId}')]
	public function addToFolder(int $folderId, int $bookmarkId): JSONResponse {
		return $this->controller->addToFolder($folderId, $bookmarkId);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/folder/{folderId}/bookmarks/{bookmarkId}')]
	public function removeFromFolder(int $folderId, int $bookmarkId, bool $hardDelete = false): JSONResponse {
		return $this->controller->removeFromFolder($folderId, $bookmarkId, $hardDelete);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/folder/{folderId}/bookmarks/{bookmarkId}/undelete')]
	public function undeleteFromFolder(int $folderId, int $bookmarkId): JSONResponse {
		return $this->controller->undeleteFromFolder($folderId, $bookmarkId);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/folder/{folderId}')]
	public function editFolder(int $folderId, ?string $title = null, ?int $parent_folder = null): JSONResponse {
		return $this->controller->editFolder($folderId, $title, $parent_folder);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/folder/{folderId}/hash')]
	public function hashFolder(int $folderId, array $fields = ['title', 'url']): JSONResponse {
		return $this->controller->hashFolder($folderId, $fields);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/folder')]
	public function getFolders(int $root = -1, int $layers = -1): JSONResponse {
		return $this->controller->getFolders($root, $layers);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/folder/{folderId}/publictoken')]
	public function getFolderPublicToken(int $folderId): DataResponse {
		return $this->controller->getFolderPublicToken($folderId);
	}


	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/folder/{folderId}/publictoken')]
	public function createFolderPublicToken(int $folderId): DataResponse {
		return $this->controller->createFolderPublicToken($folderId);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/folder/{folderId}/publictoken')]
	public function deleteFolderPublicToken(int $folderId): DataResponse {
		return $this->controller->deleteFolderPublicToken($folderId);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/folder/{folderId}/shares')]
	public function getShares(int $folderId): DataResponse {
		return $this->controller->getShares($folderId);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/folder/shared')]
	public function findSharedFolders(): DataResponse {
		return $this->controller->findSharedFolders();
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/share')]
	public function findShares(): DataResponse {
		return $this->controller->findShares();
	}


	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/folder/{folderId}/shares')]
	public function createShare(int $folderId, string $participant, int $type, bool $canWrite = false, bool $canShare = false): DataResponse {
		return $this->controller->createShare($folderId, $participant, $type, $canWrite, $canShare);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/share/{shareId}')]
	public function editShare(int $shareId, bool $canWrite = false, bool $canShare = false): DataResponse {
		return $this->controller->editShare($shareId, $canWrite, $canShare);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/share/{shareId}')]
	public function deleteShare(int $shareId): DataResponse {
		return $this->controller->deleteShare($shareId);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/folder/deleted')]
	public function getDeletedFolders(): DataResponse {
		return $this->controller->getDeletedFolders();
	}
}
