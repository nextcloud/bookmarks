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
use GuzzleHttp\Psr7;
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
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Lock\LockedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class CrawlService {
	public const MAX_BODY_LENGTH = 92160000; // 90 MB
	public const TIMEOUT = 60;
	public const CONNECT_TIMEOUT = 30;
	public const READ_TIMEOUT = 30;
	public const UA_FIREFOX = 'Mozilla/5.0 (X11; Linux x86_64; rv:150.0) Gecko/20100101 Firefox/150.0';

	private IClient $client;
	private MimeTypes $mimey;

	public function __construct(
		private BookmarkMapper $bookmarkMapper,
		private BookmarkPreviewer $bookmarkPreviewer,
		private FaviconPreviewer $faviconPreviewer,
		IClientService $clientService,
		private IConfig $config,
		private IRootFolder $rootFolder,
		private IL10N $l,
		private LoggerInterface $logger,
		private UserSettingsService $userSettingsService,
	) {
		$this->client = $clientService->newClient();
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
		// The body may come without (truthful) Content-Length, so we abort the download
		// as soon as more than MAX_BODY_LENGTH bytes arrive (curl aborts when the sink accepts fewer bytes than offered)
		$tooLarge = false;
		$status = null;
		$buffer = Psr7\Utils::streamFor(Psr7\Utils::tryFopen('php://temp', 'w+'));
		$sink = Psr7\FnStream::decorate($buffer, [
			'write' => static function (string $data) use ($buffer, &$tooLarge): int {
				if ($buffer->getSize() + strlen($data) > self::MAX_BODY_LENGTH) {
					$tooLarge = true;
					return 0;
				}
				return $buffer->write($data);
			},
		]);

		$resp = null;
		try {
			$resp = $this->client->get($bookmark->getUrl(), [
				'headers' => [
					'User-Agent' => self::UA_FIREFOX,
				],
				'connect_timeout' => self::CONNECT_TIMEOUT,
				'timeout' => self::TIMEOUT,
				'read_timeout' => self::READ_TIMEOUT,
				'http_errors' => false,
				'sink' => $sink,
				'on_headers' => static function (ResponseInterface $response) use (&$tooLarge, &$status): void {
					$status = $response->getStatusCode();
					if ((int)$response->getHeaderLine('Content-Length') > self::MAX_BODY_LENGTH) {
						$tooLarge = true;
						throw new Exception('Response body exceeds ' . self::MAX_BODY_LENGTH . ' bytes');
					}
				},
			]);
			$available = $resp->getStatusCode() !== 404;
		} catch (Exception $e) {
			// An aborted oversized download still means the resource is there, we just won't archive it
			$available = $tooLarge && $status !== 404;
			if (!$tooLarge) {
				$this->logger->warning($e->getMessage());
			}
		}

		if ($available) {
			$this->userSettingsService->setUserId($bookmark->getUserId());
			if ($resp !== null && $this->userSettingsService->get('archive.enabled') === 'true') {
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

	private function archiveContent(Bookmark $bookmark, IResponse $resp): void {
		$contentType = $resp->getHeader('Content-Type');

		if ($contentType === '') {
			return;
		}

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

	private function archiveFile(Bookmark $bookmark, IResponse $resp): void {
		$contentType = $resp->getHeader('Content-Type');

		if ($contentType === '') {
			return;
		}

		if ($contentType !== null && !str_contains($contentType, 'text/html') && $bookmark->getArchivedFile() === null) {
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
