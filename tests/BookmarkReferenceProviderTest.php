<?php

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Reference\BookmarkReferenceProvider;
use OCA\Bookmarks\Service\Authorizer;
use OCA\Bookmarks\Service\BookmarkService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;

class BookmarkReferenceProviderTest extends TestCase {
	private BookmarkService&MockObject $bookmarkService;
	private Authorizer&MockObject $authorizer;
	private BookmarkReferenceProvider $provider;

	protected function setUp(): void {
		parent::setUp();
		$this->bookmarkService = $this->createMock(BookmarkService::class);
		$this->authorizer = $this->createMock(Authorizer::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('getAbsoluteURL')->willReturnCallback(static fn (string $path) => 'https://cloud.example' . $path);
		$this->provider = new BookmarkReferenceProvider(
			$this->createMock(IL10N::class),
			'alice',
			$urlGenerator,
			$this->bookmarkService,
			$this->authorizer,
		);
	}

	private function bookmark(int $id, string $url): Bookmark {
		$bookmark = new Bookmark();
		$bookmark->setId($id);
		$bookmark->setUserId('alice');
		$bookmark->setUrl($url);
		return $bookmark;
	}

	public function testBookmarkedUrlIsLookedUpOnce(): void {
		$url = 'https://example.org/page';
		$this->bookmarkService->expects($this->once())
			->method('findByUrl')
			->with('alice', $url)
			->willReturn($this->bookmark(42, $url));
		$this->bookmarkService->expects($this->never())->method('findById');
		// permissions are checked for the bookmark that was found
		$this->authorizer->expects($this->once())
			->method('getUserPermissionsForBookmark')
			->with('alice', 42)
			->willReturn(Authorizer::PERM_ALL);

		$this->assertTrue($this->provider->matchReference($url));
		$reference = $this->provider->resolveReference($url);

		$this->assertNotNull($reference);
		$this->assertSame(42, $reference->getRichObject()['id']);
		$this->assertSame($url, $reference->getRichObject()['bookmark']['url']);
	}

	public function testUnknownUrlIsLookedUpOnce(): void {
		$url = 'https://example.org/unknown';
		$this->bookmarkService->expects($this->once())
			->method('findByUrl')
			->with('alice', $url)
			->willThrowException(new DoesNotExistException('URL does not exist'));
		$this->authorizer->expects($this->never())->method('getUserPermissionsForBookmark');

		$this->assertFalse($this->provider->matchReference($url));
		$this->assertNull($this->provider->resolveReference($url));
	}

	public function testUrlWithoutReadPermissionIsNotResolved(): void {
		$url = 'https://example.org/page';
		$this->bookmarkService->method('findByUrl')->willReturn($this->bookmark(42, $url));
		$this->authorizer->expects($this->once())
			->method('getUserPermissionsForBookmark')
			->with('alice', 42)
			->willReturn(Authorizer::PERM_NONE);

		$this->assertNull($this->provider->resolveReference($url));
	}

	public function testBookmarkLinkIsResolvedById(): void {
		$link = 'https://cloud.example/index.php/apps/bookmarks/bookmarks/7';
		$this->bookmarkService->expects($this->never())->method('findByUrl');
		$this->bookmarkService->expects($this->once())
			->method('findById')
			->with(7)
			->willReturn($this->bookmark(7, 'https://example.org/seven'));
		$this->authorizer->expects($this->once())
			->method('getUserPermissionsForBookmark')
			->with('alice', 7)
			->willReturn(Authorizer::PERM_ALL);

		$this->assertTrue($this->provider->matchReference($link));
		$reference = $this->provider->resolveReference($link);

		$this->assertNotNull($reference);
		$this->assertSame(7, $reference->getRichObject()['id']);
	}
}
