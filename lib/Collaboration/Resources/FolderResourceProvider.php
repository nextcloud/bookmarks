<?php
/*
 * Copyright (c) 2022. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Collaboration\Resources;

use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Service\Authorizer;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Collaboration\Resources\IProvider;
use OCP\Collaboration\Resources\IResource;
use OCP\IURLGenerator;
use OCP\IUser;

class FolderResourceProvider implements IProvider {
	public const RESOURCE_TYPE = 'bookmarks-folder';
	/**
	 * @var IURLGenerator
	 */
	private $url;
	/**
	 * @var Authorizer
	 */
	private $authorizer;
	/**
	 * @var FolderMapper
	 */
	private $folderMapper;

	public function __construct(IURLGenerator $url, Authorizer $authorizer, FolderMapper $folderMapper) {
		$this->url = $url;
		$this->authorizer = $authorizer;
		$this->folderMapper = $folderMapper;
	}

	/**
	 * @inheritDoc
	 */
	public function getType(): string {
		return self::RESOURCE_TYPE;
	}

	/**
	 * @inheritDoc
	 */
	public function getResourceRichObject(IResource $resource): array {
		$folder = $this->getFolder($resource);
		$favicon = $this->url->imagePath('bookmarks', 'bookmarks-black.svg');
		$resourceUrl = $this->url->linkToRouteAbsolute('bookmarks.web_view.indexfolder', ['folder' => $folder->getId()]);

		return [
			'type' => self::RESOURCE_TYPE,
			'id' => $resource->getId(),
			'name' => $folder->getTitle(),
			'link' => $resourceUrl,
			'iconUrl' => $favicon,
		];
	}

	/**
	 * @inheritDoc
	 */
	public function canAccessResource(IResource $resource, ?IUser $user): bool {
		if ($resource->getType() !== self::RESOURCE_TYPE || !($user instanceof IUser)) {
			return false;
		}
		$folder = $this->getFolder($resource);
		if ($folder === null) {
			return false;
		}
		if ($folder->getUserId() === $user->getUID()) {
			return true;
		}
		$permissions = $this->authorizer->getUserPermissionsForFolder($user->getUID(), $folder->getId());
		return Authorizer::hasPermission(Authorizer::PERM_READ, $permissions);
	}

	private function getFolder(IResource $resource) : ?Folder {
		try {
			return $this->folderMapper->find((int) $resource->getId());
		} catch (MultipleObjectsReturnedException|DoesNotExistException $e) {
			return null;
		}
	}
}
