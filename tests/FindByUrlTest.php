<?php

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\QueryParameters;
use OCA\Bookmarks\Service\BookmarkService;
use OCA\Bookmarks\Service\FolderService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserManager;
use OCP\Share\IShare;

/**
 * BookmarkService::findByUrl() looks up the user's own bookmark by url_hash and walks up
 * from it (TreeMapper::isVisibleBelow) instead of running the recursive folder_tree CTE
 * over the whole tree. Every case here checks it against that full query
 * (BookmarkMapper::findAll with a URL filter), which stays the reference.
 */
class FindByUrlTest extends TestCase {
	private Db\BookmarkMapper $bookmarkMapper;
	private Db\TreeMapper $treeMapper;
	private Db\FolderMapper $folderMapper;
	private BookmarkService $bookmarkService;
	private IUserManager $userManager;

	protected function setUp(): void {
		parent::setUp();
		$this->cleanUp();
		$this->bookmarkMapper = \OCP\Server::get(Db\BookmarkMapper::class);
		$this->treeMapper = \OCP\Server::get(Db\TreeMapper::class);
		$this->folderMapper = \OCP\Server::get(Db\FolderMapper::class);
		$this->bookmarkService = \OCP\Server::get(BookmarkService::class);
		$this->userManager = \OCP\Server::get(IUserManager::class);
	}

	private function user(string $name): string {
		if (!$this->userManager->userExists($name)) {
			$this->userManager->createUser($name, 'password');
		}
		return $this->userManager->get($name)->getUID();
	}

	private function folder(string $userId, string $title, int $parentId): int {
		$folder = new Db\Folder();
		$folder->setTitle($title);
		$folder->setUserId($userId);
		$this->folderMapper->insert($folder);
		$this->treeMapper->move(Db\TreeMapper::TYPE_FOLDER, $folder->getId(), $parentId);
		return $folder->getId();
	}

	private function bookmark(string $userId, string $url, array $folderIds): int {
		$bookmark = $this->bookmarkMapper->insertOrUpdate(Db\Bookmark::fromArray([
			'userId' => $userId,
			'url' => $url,
			'title' => $url,
			'description' => '',
		]));
		$this->treeMapper->addToFolders(Db\TreeMapper::TYPE_BOOKMARK, $bookmark->getId(), $folderIds);
		return $bookmark->getId();
	}

	private function root(string $userId): int {
		return $this->folderMapper->findRootFolder($userId)->getId();
	}

	private function find(string $userId, string $url, bool $includeShared): ?int {
		try {
			return $this->bookmarkService->findByUrl($userId, $url, $includeShared)->getId();
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Same answer as the full CTE query: with $includeShared any bookmark that query
	 * finds (the user's own first), without it only the user's own one.
	 */
	private function assertSameAsFullQuery(string $userId, string $url, ?int $expectOwn): void {
		$all = $this->bookmarkMapper->findAll($userId, (new QueryParameters())->setUrl($url));
		$allIds = array_map(static fn (Db\Bookmark $b) => $b->getId(), $all);
		$ownIds = array_values(array_map(static fn (Db\Bookmark $b) => $b->getId(),
			array_filter($all, static fn (Db\Bookmark $b) => $b->getUserId() === $userId)));

		$this->assertSame($expectOwn, $ownIds[0] ?? null, "full query, own bookmark of $userId for $url");
		$this->assertSame($ownIds[0] ?? null, $this->find($userId, $url, false), "own lookup of $userId for $url");
		$found = $this->find($userId, $url, true);
		if ($allIds === []) {
			$this->assertNull($found, "lookup incl. shared of $userId for $url");
		} else {
			$this->assertContains($found, $allIds, "lookup incl. shared of $userId for $url");
			if ($ownIds !== []) {
				$this->assertSame($ownIds[0], $found, "own bookmark comes first for $userId, $url");
			}
		}
	}

	public function testOwnBookmarks(): void {
		$user = $this->user('findbyurl_own');
		$a = $this->folder($user, 'a', $this->root($user));
		$b = $this->folder($user, 'b', $a);

		$inRoot = $this->bookmark($user, 'https://example.org/in-root', [$this->root($user)]);
		$nested = $this->bookmark($user, 'https://example.org/nested', [$b]);

		$this->assertSameAsFullQuery($user, 'https://example.org/in-root', $inRoot);
		$this->assertSameAsFullQuery($user, 'https://example.org/nested', $nested);
		// not normalized the way it's stored: still found, as before
		$this->assertSameAsFullQuery($user, 'HTTPS://Example.ORG:443/nested', $nested);
		$this->assertSameAsFullQuery($user, 'https://example.org/unknown', null);
	}

	public function testTrash(): void {
		$user = $this->user('findbyurl_trash');
		$a = $this->folder($user, 'a', $this->root($user));
		$b = $this->folder($user, 'b', $a);
		$c = $this->folder($user, 'c', $this->root($user));

		$this->bookmark($user, 'https://example.org/trashed', [$a]);
		$this->bookmark($user, 'https://example.org/in-trashed-folder', [$b]);
		$twice = $this->bookmark($user, 'https://example.org/in-two-folders', [$b, $c]);

		$this->treeMapper->softDeleteEntry(Db\TreeMapper::TYPE_BOOKMARK, $this->bookmarkMapper->findByUrl($user, 'https://example.org/trashed')->getId());
		$this->treeMapper->softDeleteEntry(Db\TreeMapper::TYPE_FOLDER, $a);

		$this->assertSameAsFullQuery($user, 'https://example.org/trashed', null);
		$this->assertSameAsFullQuery($user, 'https://example.org/in-trashed-folder', null);
		// still visible through the folder that isn't in the trash
		$this->assertSameAsFullQuery($user, 'https://example.org/in-two-folders', $twice);
	}

	public function testSharedFolders(): void {
		$owner = $this->user('findbyurl_owner');
		$recipient = $this->user('findbyurl_recipient');
		$shared = $this->folder($owner, 'shared', $this->root($owner));
		$sub = $this->folder($owner, 'sub', $shared);
		\OCP\Server::get(FolderService::class)->createShare($shared, $recipient, IShare::TYPE_USER, true, false);

		// the owner's bookmark: the recipient sees it only through the share
		$ownersBookmark = $this->bookmark($owner, 'https://example.org/owners', [$sub]);
		// the recipient's own bookmark, placed inside the owner's shared folder
		$recipientsBookmark = $this->bookmark($recipient, 'https://example.org/recipients', [$sub]);
		// the same URL bookmarked by both
		$this->bookmark($owner, 'https://example.org/both', [$sub]);
		$recipientsCopy = $this->bookmark($recipient, 'https://example.org/both', [$this->root($recipient)]);

		$this->assertSameAsFullQuery($owner, 'https://example.org/owners', $ownersBookmark);
		$this->assertSameAsFullQuery($recipient, 'https://example.org/owners', null);
		$this->assertSame($ownersBookmark, $this->find($recipient, 'https://example.org/owners', true));

		$this->assertSameAsFullQuery($recipient, 'https://example.org/recipients', $recipientsBookmark);
		$this->assertSameAsFullQuery($owner, 'https://example.org/recipients', null);

		$this->assertSameAsFullQuery($recipient, 'https://example.org/both', $recipientsCopy);

		// the owner trashes the shared folder: the recipient must not find anything in it
		$this->treeMapper->softDeleteEntry(Db\TreeMapper::TYPE_FOLDER, $shared);
		$this->assertSameAsFullQuery($recipient, 'https://example.org/owners', null);
		$this->assertNull($this->find($recipient, 'https://example.org/owners', true));
		$this->assertSameAsFullQuery($recipient, 'https://example.org/recipients', null);
		$this->assertSameAsFullQuery($recipient, 'https://example.org/both', $recipientsCopy);
		$this->assertSameAsFullQuery($owner, 'https://example.org/owners', null);
	}
}
