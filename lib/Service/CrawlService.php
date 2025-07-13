<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use Exception;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\Readability;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
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
use OCP\IConfig;
use OCP\IL10N;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;

class CrawlService {
	public const MAX_BODY_LENGTH = 92160000; // 90 MB
	public const TIMEOUT = 60;
	public const CONNECT_TIMEOUT = 30;
	public const READ_TIMEOUT = 30;
	public const UA_FIREFOX = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:87.0) Gecko/20100101 Firefox/87.0';

	private MimeTypes $mimey;

	public function __construct(
		private BookmarkMapper $bookmarkMapper,
		private BookmarkPreviewer $bookmarkPreviewer,
		private FaviconPreviewer $faviconPreviewer,
		private IConfig $config,
		private IRootFolder $rootFolder,
		private IL10N $l,
		private LoggerInterface $logger,
		private UserSettingsService $userSettingsService,
	) {
		$this->mimey = new MimeTypes;
	}

	/**
	 * @param Bookmark $bookmark
	 * @throws UrlParseError
	 */
	public function crawl(Bookmark $bookmark): void {
		if (!$bookmark->isWebLink()) {
			return;
		}
		try {
			$client = new Client();
			/** @var Response $resp */
			$resp = $client->get($bookmark->getUrl(), [
				'headers' => [
					'User-Agent' => self::UA_FIREFOX,
				],
				'connect_timeout' => self::CONNECT_TIMEOUT,
				'timeout' => self::TIMEOUT,
				'read_timeout' => self::READ_TIMEOUT,
				'http_errors' => false
			]);
			$available = $resp ? $resp->getStatusCode() !== 404 : false;
		} catch (Exception $e) {
			$this->logger->warning($e->getMessage());
			$available = false;
		}

		if ($available) {
			$this->userSettingsService->setUserId($bookmark->getUserId());
			if ($this->userSettingsService->get('archive.enabled') === 'true') {
				$this->archiveFile($bookmark, $resp);
				$this->archiveContent($bookmark, $resp);
			}
			$this->bookmarkPreviewer->getImage($bookmark);
			$this->faviconPreviewer->getImage($bookmark);
		}
		$bookmark->markPreviewCreated();
		$bookmark->setAvailable($available);
		$this->bookmarkMapper->update($bookmark);
	}

	private function archiveContent(Bookmark $bookmark, Response $resp): void {
		$header = $resp->getHeader('Content-Type');

		if (empty($header)) {
			return;
		}

		$contentType = $header[0] ?? null;

		if ($contentType !== null && str_contains($contentType, 'text/html')) {
			if ($bookmark->getHtmlContent() === null || $bookmark->getHtmlContent() === '') {
				$config = new Configuration();
				$config
					->setFixRelativeURLs(true)
					->setOriginalURL($bookmark->getUrl())
					->setSubstituteEntities(true);
				$readability = new Readability($config);

				try {
					$readability->parse($resp->getBody());
					$bookmark->setHtmlContent($readability->getContent());
					$bookmark->setTextContent(strip_tags($readability->getContent()));
				} catch (\Throwable $e) {
					$this->logger->debug(get_class($e) . ' ' . $e->getMessage() . "\r\n" . $e->getTraceAsString());
				}
			}
		}
	}

	private function archiveFile(Bookmark $bookmark, Response $resp): void {
		$header = $resp->getHeader('Content-Type');

		if (empty($header)) {
			return;
		}

		$contentType = $header[0] ?? null;

		if ($contentType !== null && !str_contains($contentType, 'text/html') && $bookmark->getArchivedFile() === null) {
			$contentLengthHeader = $resp->getHeader('Content-Length');
			$contentLength = isset($contentLengthHeader[0]) ? (int)$contentLengthHeader[0] : 0;

			if ($contentLength < self::MAX_BODY_LENGTH) {
				try {
					$userFolder = $this->rootFolder->getUserFolder($bookmark->getUserId());
					$folderPath = $this->getArchivePath($bookmark, $userFolder);
					$name = $bookmark->slugify('title');
					$extension = $this->mimey->getExtension($contentType) ?? 'txt';

					$i = 0;
					do {
						$path = $folderPath . '/' . $name . ($i > 0 ? '_' . $i : '') . '.' . $extension;
						$i++;
					} while ($userFolder->nodeExists($path));

					$file = $userFolder->newFile($path);
					$file->putContent($resp->getBody());
					$bookmark->setArchivedFile($file->getId());
					$this->bookmarkMapper->update($bookmark);
				} catch (NotPermittedException|NoUserException|GenericFileException|LockedException|UrlParseError|InvalidPathException|NotFoundException $e) {
					$this->logger->debug(get_class($e) . ' ' . $e->getMessage() . "\r\n" . $e->getTraceAsString());
				}
			}
		}
	}

	private function getArchivePath(Bookmark $bookmark, Folder $userFolder): string {
		$folderPath = $this->config->getUserValue($bookmark->getUserId(), 'bookmarks', 'archive.filePath', $this->l->t('Bookmarks'));
		$this->getOrCreateFolder($userFolder, $folderPath);
		return $folderPath;
	}

	public function getOrCreateFolder(Folder $userFolder, string $path) : ?Folder {
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
