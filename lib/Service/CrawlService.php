<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use marcelklehr\Readability\Configuration;
use marcelklehr\Readability\ParseException;
use marcelklehr\Readability\Readability;
use Exception;
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
	public const MAX_BODY_LENGTH = 92160; // 90 MiB
	public const TIMEOUT = 10;
	public const CONNECT_TIMEOUT = 10;
	public const READ_TIMEOUT = 10;
	public const UA_FIREFOX = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:87.0) Gecko/20100101 Firefox/87.0';

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
	 * @var IConfig
	 */
	private $config;
	private $path;
	private $rootFolder;
	/**
	 * @var IL10N
	 */
	private $l;
	/**
	 * @var MimeTypes
	 */
	private $mimey;
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct(BookmarkMapper $bookmarkMapper, BookmarkPreviewer $bookmarkPreviewer, FaviconPreviewer $faviconPreviewer, IConfig $config, IRootFolder $rootFolder, IL10N $l, LoggerInterface $logger) {
		$this->bookmarkMapper = $bookmarkMapper;
		$this->bookmarkPreviewer = $bookmarkPreviewer;
		$this->faviconPreviewer = $faviconPreviewer;
		$this->config = $config;
		$this->rootFolder = $rootFolder;
		$this->l = $l;
		$this->mimey = new MimeTypes;
		$this->logger = $logger;
	}

	/**
	 * @param Bookmark $bookmark
	 * @throws UrlParseError
	 */
	public function crawl(Bookmark $bookmark): void {
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
			$this->archiveFile($bookmark, $resp);
			$this->archiveContent($bookmark, $resp);
			$this->bookmarkPreviewer->getImage($bookmark);
			$this->faviconPreviewer->getImage($bookmark);
		}
		$bookmark->markPreviewCreated();
		$bookmark->setAvailable($available);
		$this->bookmarkMapper->update($bookmark);
	}

	private function archiveContent(Bookmark $bookmark, Response $resp) : void {
		$contentType = $resp->getHeader('Content-type')[0];
		if ((bool)preg_match('#text/html#i', $contentType) === true && ($bookmark->getHtmlContent() === null || $bookmark->getHtmlContent() === '')) {
			$config = new Configuration();
			$config
				->setFixRelativeURLs(true)
				->setOriginalURL($bookmark->getUrl())
				->setSubstituteEntities(true);
			$readability = new Readability($config);
			try {
				$readability->parse($resp->getBody());
			} catch (ParseException $e) {
				$this->logger->debug(get_class($e)." ".$e->getMessage()."\r\n".$e->getTraceAsString());
			}
			$bookmark->setHtmlContent($readability->getContent());
			$bookmark->setTextContent(strip_tags($readability->getContent()));
		}
	}

	private function archiveFile(Bookmark $bookmark, Response $resp) :void {
		$contentType = $resp->getHeader('Content-type')[0];
		if ((bool)preg_match('#text/html#i', $contentType) === false && $bookmark->getArchivedFile() === null && (int)$resp->getHeader('Content-length')[0] < self::MAX_BODY_LENGTH) {
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
			} catch (NotPermittedException | NoUserException | GenericFileException | LockedException | UrlParseError | InvalidPathException | NotFoundException $e) {
				$this->logger->debug(get_class($e)." ".$e->getMessage()."\r\n".$e->getTraceAsString());
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
