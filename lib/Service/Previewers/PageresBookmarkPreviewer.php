<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service\Previewers;

use Exception;
use OCA\Bookmarks\Contract\IBookmarkPreviewer;
use OCA\Bookmarks\Contract\IImage;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Image;
use Psr\Log\LoggerInterface;
use OCP\ITempManager;

class PageresBookmarkPreviewer implements IBookmarkPreviewer {
	public const CACHE_PREFIX = 'bookmarks.WebshotPreviewService';
	public const CAPTURE_MAX_RETRIES = 3;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * @var ITempManager
	 */
	private $tempManager;

	public function __construct(ITempManager $tempManager, LoggerInterface $logger) {
		$this->tempManager = $tempManager;
		$this->logger = $logger;
	}

	/**
	 * @param Bookmark $bookmark
	 * @return IImage|null
	 */
	public function getImage($bookmark): ?IImage {
		if (!isset($bookmark)) {
			return null;
		}

		$serverPath = self::getPageresPath();
		if ($serverPath === null) {
			return null;
		}

		$url = $bookmark->getUrl();

		// Fetch image from remote server
		return $this->fetchImage($serverPath, $url);
	}

	/**
	 * @param string $serverPath
	 * @param string $url
	 * @return Image|null
	 * @throws Exception
	 */
	protected function fetchImage(string $serverPath, string $url): ?Image {
		$tempPath = $this->tempManager->getTemporaryFile('.png');
		$tempDir = dirname($tempPath);
		$tempFile = basename($tempPath, '.png');
		$command = $serverPath;
		$escapedUrl = escapeshellarg($url);

		$cmd = "cd {$tempDir} && {$command} {$escapedUrl}" .
			' --delay=4 --filename=' . escapeshellarg($tempFile) . ' --overwrite 2>&1';

		$retries = 0;
		$output = [];
		while ($retries < self::CAPTURE_MAX_RETRIES) {
			$output = [];
			@exec($cmd, $output, $returnCode);

			if ($returnCode === 0 && is_file($tempPath)) {
				$content = file_get_contents($tempPath);
				unlink($tempPath);

				return new Image('image/png', $content);
			}

			$retries++;
		}

		throw new Exception("Pageres Error\nCommand: {$cmd}\nOutput: " . implode(' ' . PHP_EOL, $output) . PHP_EOL);
	}

	/**
	 * @return null|string
	 */
	public static function getPageresPath(): ?string {
		$serverPath = @exec('which pageres');
		if (!empty($serverPath) && is_readable($serverPath)) {
			return $serverPath;
		}

		return null;
	}
}
