<?php
/**
 * @author Marcel Klehr
 * @author Marius David Wieschollek
 * @copyright 2020 Marcel Klehr mklehr@gmx.net
 * @copyright Marius David Wieschollek
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Bookmarks\Service\Previewers;

use OCA\Bookmarks\Contract\IBookmarkPreviewer;
use OCA\Bookmarks\Contract\IImage;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Image;
use OCP\ILogger;
use OCP\ITempManager;

class PageresBookmarkPreviewer implements IBookmarkPreviewer {
	public const CACHE_PREFIX = 'bookmarks.WebshotPreviewService';
	public const CAPTURE_MAX_RETRIES = 3;

	/** @var ILogger */
	private $logger;

	/**
	 * @var ITempManager
	 */
	private $tempManager;

	public function __construct(ITempManager $tempManager, ILogger $logger) {
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
	 * @throws \Exception
	 */
	protected function fetchImage(string $serverPath, string $url): ?Image {
		$tempPath = $this->tempManager->getTemporaryFile('.png');
		$tempDir = dirname($tempPath);
		$tempFile = basename($tempPath);
		$command = $serverPath;
		$escapedUrl = escapeshellarg($url);

		$cmd = "cd {$tempDir} && {$command} {$escapedUrl} desktop" .
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

		throw new \Exception("Pageres Error\nCommand: {$cmd}\nOutput: " . implode(' ' . PHP_EOL, $output) . PHP_EOL);
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
