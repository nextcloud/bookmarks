<?php
/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service\Previewers;

use Exception;
use OCA\Bookmarks\Contract\IBookmarkPreviewer;
use OCA\Bookmarks\Contract\IImage;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Image;
use OCP\IConfig;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;

class PageresBookmarkPreviewer implements IBookmarkPreviewer {
	public const CACHE_PREFIX = 'bookmarks.PageresPreviewService';
	public const CAPTURE_MAX_RETRIES = 3;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * @var ITempManager
	 */
	private $tempManager;
	/**
	 * @var IConfig
	 */
	private $config;

	public function __construct(ITempManager $tempManager, LoggerInterface $logger, IConfig $config) {
		$this->tempManager = $tempManager;
		$this->logger = $logger;
		$this->config = $config;
	}

	/**
	 * @param Bookmark|null $bookmark
	 *
	 * @return Image|null
	 */
	public function getImage($bookmark, $cacheOnly = false): ?IImage {
		if (!isset($bookmark)) {
			return null;
		}

		$serverPath = self::getPageresPath();
		if ($serverPath === null || $cacheOnly) {
			return null;
		}

		$url = $bookmark->getUrl();

		// Fetch image from remote server
		return $this->fetchImage($serverPath, $url);
	}

	/**
	 * @param string $serverPath
	 * @param string $url
	 *
	 * @return Image
	 *
	 * @throws Exception
	 */
	protected function fetchImage(string $serverPath, string $url): Image {
		$tempPath = $this->tempManager->getTemporaryFile('.png');
		$tempDir = dirname($tempPath);
		$tempFile = basename($tempPath, '.png');
		$command = $serverPath;
		$escapedUrl = escapeshellarg($url);
		$env = $this->config->getAppValue('bookmarks', 'previews.pageres.env');

		$cmd = "cd {$tempDir} && {$env} {$command} {$escapedUrl} 1024x768" .
			' --delay=4 --filename=' . escapeshellarg($tempFile) . ' --crop --overwrite 2>&1';

		$retries = 0;
		$output = [];
		while ($retries < self::CAPTURE_MAX_RETRIES) {
			$output = [];
			@exec($cmd, $output, $returnCode);

			if ($returnCode === 0 && is_file($tempPath)) {
				$content = file_get_contents($tempPath);
				unlink($tempPath);

				return new Image('image/png', $content);
			} else {
				$this->logger->debug('Executing pageres failed');
				$this->logger->debug(implode("\n", $output));
			}

			$retries++;
		}

		throw new Exception("Pageres Error\nCommand: {$cmd}\nOutput: " . implode(' ' . PHP_EOL, $output) . PHP_EOL);
	}

	/**
	 * @return null|string
	 */
	public static function getPageresPath(): ?string {
		@exec('which pageres', $serverPath);
		$path = trim(implode("\n", $serverPath));
		if (!empty($path) && is_readable($path)) {
			return $path;
		}

		return null;
	}
}
