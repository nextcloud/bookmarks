<?php
/*
 * @copyright Copyright (c) 2022 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @author The Nextcloud Bookmarks contributors
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Reference;

use OCA\Bookmarks\AppInfo\Application;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Service\Authorizer;
use OCA\Bookmarks\Service\BookmarkService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\Reference;
use OCP\IL10N;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class BookmarkReferenceProvider extends ADiscoverableReferenceProvider {
	private ?string $userId;
	private IL10N $l10n;
	private IURLGenerator $urlGenerator;
	private BookmarkService $bookmarkService;
	private Authorizer $authorizer;

	public function __construct(IL10N $l10n, ?string $userId, IURLGenerator $urlGenerator, BookmarkService $bookmarkService, Authorizer $authorizer) {
		$this->userId = $userId;
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->bookmarkService = $bookmarkService;
		$this->authorizer = $authorizer;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return Application::APP_ID . '-ref-bookmarks';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Bookmarks');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'bookmarks-black.svg')
		);
	}
	/**
	 * @inheritDoc
	 */
	public function matchReference(string $referenceText): bool {
		\OC::$server->get(LoggerInterface::class)->warning('MATCH REFERENCE');
		$start = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_ID);
		$startIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_ID);

		// link example: https://nextcloud.local/index.php/apps/deck/#/board/2/card/11
		$noIndexMatch = preg_match('/^' . preg_quote($start, '/') . '\/bookmarks\/[0-9]+$/', $referenceText) !== false;
		$indexMatch = preg_match('/^' . preg_quote($startIndex, '/') . '\/bookmarks\/[0-9]+$/', $referenceText) !== false;

		if ($noIndexMatch || $indexMatch) {
			return true;
		}

		try {
			$this->bookmarkService->findByUrl($this->userId, $referenceText);
			return true;
		} catch (UrlParseError|DoesNotExistException $e) {
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if (!$this->matchReference($referenceText)) {
			return null;
		}
		$id = $this->getBookmarkId($referenceText);
		if ($id === null) {
			try {
				$bookmark = $this->bookmarkService->findByUrl($this->userId, $referenceText);
			} catch (UrlParseError|DoesNotExistException $e) {
				return null;
			}
		}else{
			$bookmark = $this->bookmarkService->findById((int)$id);
		}
		if ($bookmark === null) {
			return null;
		}
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getUserPermissionsForBookmark($this->userId, (int)$id))) {
			return null;
		}

		/** @var IReference $reference */
		$reference = new Reference($referenceText);
		$reference->setRichObject(Application::APP_ID . '-bookmark', [
			'id' => $id,
			'bookmark' => $bookmark->toArray(),
		]);

		return $reference;
	}

	private function getBookmarkId(string $url): ?string {
		$start = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_ID);
		$startIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_ID);

		preg_match('/^' . preg_quote($start, '/') . '\/bookmarks\/([0-9]+)$/', $url, $matches);
		if ($matches && count($matches) > 1) {
			return $matches[1];
		}

		preg_match('/^' . preg_quote($startIndex, '/') . '\/bookmarks\/([0-9]+)$/', $url, $matches2);
		if ($matches2 && count($matches2) > 1) {
			return $matches2[1];
		}

		return null;
	}

	public function getCachePrefix(string $referenceId): string {
		$id = $this->getBookmarkId($referenceId);
		return $id ?? $referenceId;
	}

	public function getCacheKey(string $referenceId): ?string {
		return $this->userId ?? '';
	}
}
