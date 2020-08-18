<?php

namespace OCA\Bookmarks\Service;

use Mimey\MimeTypes;
use OC\User\NoUserException;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Exception\UrlParseError;
use OCP\Files\Folder;
use OCP\Files\GenericFileException;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\Lock\LockedException;

class CrawlService {
	/**
	 * @var BookmarkMapper
	 */
	private $bookmarkMapper;
	/**
	 * @var BookmarkPreviewer
	 */
	private $bookmarkPreviewer;
	/**
	 * @var FaviconPreviewer
	 */
	private $faviconPreviewer;
	/**
	 * @var IClientService
	 */
	private $clientService;
	/**
	 * @var IConfig
	 */
	private $config;
	private $path;
	private $rootFolder;
	/**
	 * @var \OCP\IL10N
	 */
	private $l;

	public function __construct(BookmarkMapper $bookmarkMapper, BookmarkPreviewer $bookmarkPreviewer, FaviconPreviewer $faviconPreviewer, IClientService $clientService, IConfig $config, IRootFolder $rootFolder, \OCP\IL10N $l) {
		$this->bookmarkMapper = $bookmarkMapper;
		$this->bookmarkPreviewer = $bookmarkPreviewer;
		$this->faviconPreviewer = $faviconPreviewer;
		$this->clientService = $clientService;
		$this->client = $this->clientService->newClient();
		$this->config = $config;
		$this->rootFolder = $rootFolder;
		$this->l = $l;
		$this->mimey = new MimeTypes;
	}

	/**
	 * @param Bookmark $bookmark
	 * @throws \OCA\Bookmarks\Exception\UrlParseError
	 */
	public function crawl(Bookmark $bookmark): void {
		try {
			/** @var IResponse $resp */
			$resp = $this->client->get($bookmark->getUrl());
			$available = $resp->getStatusCode() !== 404;
		} catch (\Exception $e) {
			$available = false;
		}

		if ($available) {
			$this->archiveFile($bookmark, $resp);
			$this->bookmarkPreviewer->getImage($bookmark);
			$this->faviconPreviewer->getImage($bookmark);
		}
		$bookmark->markPreviewCreated();
		$bookmark->setAvailable($available);
		$this->bookmarkMapper->update($bookmark);
	}

	private function archiveFile(Bookmark $bookmark, IResponse $resp) :void {
		$contentType = $resp->getHeader('Content-type');
		if ((bool)preg_match('#text/html#i', $contentType) === false && $bookmark->getArchivedFile() === null) {
			try {
				$userFolder = $this->rootFolder->getUserFolder($bookmark->getUserId());
				$folderPath = $this->getArchivePath($bookmark, $userFolder);
				$name = $bookmark->slugify('title');
				$path = $folderPath . '/' . $name . '.' . $this->mimey->getExtension($contentType);
				$i = 0;
				while ($userFolder->nodeExists($path)) {
					$path = $folderPath . '/' .$name . '_' . $i . '.' . $this->mimey->getExtension($contentType);
					$i++;
				}
				$file = $userFolder->newFile($path);
				$file->putContent($resp->getBody());
				$bookmark->setArchivedFile($file->getId());
				$this->bookmarkMapper->update($bookmark);
			} catch (NotPermittedException $e) {
			} catch (NoUserException $e) {
			} catch (GenericFileException $e) {
			} catch (LockedException $e) {
			} catch (UrlParseError $e) {
			} catch (InvalidPathException $e) {
			} catch (NotFoundException $e) {
			}
		}
	}

	private function getArchivePath(Bookmark $bookmark, Folder $userFolder): string {
		$folderPath = $this->config->getUserValue($bookmark->getUserId(), 'bookmarks', 'archive.filePath', $this->l->t('Bookmarks'));
		$this->getOrCreateFolder($userFolder, $folderPath);
		return $folderPath;
	}

	public function getOrCreateFolder($userFolder, string $path) : ?Folder {
		if ($path === '/') {
			return $userFolder;
		}
		if ($userFolder->nodeExists($path)) {
			$folder = $userFolder->get($path);
		} else {
			$folder = $userFolder->newFolder($path);
		}
		if (!($folder instanceof Folder)) {
			return null;
		}
		return $folder;
	}
}
