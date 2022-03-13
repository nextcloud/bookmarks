<?php
/*
 * Copyright (c) 2022. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Collaboration\Resources;

use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Service\Authorizer;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Collaboration\Resources\IProvider;
use OCP\Collaboration\Resources\IResource;
use OCP\IURLGenerator;
use OCP\IUser;
use Psr\Log\LoggerInterface;

class ResourceProvider implements IProvider {
	public const RESOURCE_TYPE = 'bookmarks';
	/**
	 * @var BookmarkMapper
	 */
	private $bookmarkMapper;
	/**
	 * @var IURLGenerator
	 */
	private $url;
	/**
	 * @var Authorizer
	 */
	private $authorizer;
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct(BookmarkMapper $bookmarkMapper, IURLGenerator $url, Authorizer $authorizer, LoggerInterface $logger) {
		$this->bookmarkMapper = $bookmarkMapper;
		$this->url = $url;
		$this->authorizer = $authorizer;
		$this->logger = $logger;
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
		$bookmark = $this->getBookmark($resource);
		$favicon = $this->url->linkToRouteAbsolute('bookmarks.internal_bookmark.get_bookmark_favicon', ['id' => $bookmark->getId()]);
		$resourceUrl = $this->url->linkToRouteAbsolute('bookmarks.web_view.indexbookmark', ['bookmark' => $bookmark->getId()]);

		return [
			'type' => self::RESOURCE_TYPE,
			'id' => $resource->getId(),
			'name' => $bookmark->getTitle(),
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
		$bookmark = $this->getBookmark($resource);
		if ($bookmark === null) {
			return false;
		}
		if ($bookmark->getUserId() === $user->getUID()) {
			return true;
		}
		$permissions = $this->authorizer->getUserPermissionsForBookmark($user->getUID(), $bookmark->getId());
		return Authorizer::hasPermission(Authorizer::PERM_READ, $permissions);
	}

	private function getBookmark(IResource $resource) : ?Bookmark {
		try {
			return $this->bookmarkMapper->find((int) $resource->getId());
		} catch (MultipleObjectsReturnedException|DoesNotExistException $e) {
			return null;
		}
	}
}
