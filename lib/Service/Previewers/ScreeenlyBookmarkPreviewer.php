<?php
/**
 * @author Marcel Klehr
 * @copyright 2018 Marcel Klehr mklehr@gmx.net
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
use OCA\Bookmarks\Service\FileCache;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\ILogger;

class ScreeenlyBookmarkPreviewer implements IBookmarkPreviewer {
	const CACHE_PREFIX = 'bookmarks.ScreenlyPreviewService';

	const HTTP_TIMEOUT = 10 * 1000;

	private $apiKey;

	private $client;

	/** @var IConfig */
	private $config;

	private $cache;

	/** @var ILogger */
	private $logger;

	private $width = 800;

	private $height = 800;
	/**
	 * @var string
	 */
	private $apiUrl;

	public function __construct(FileCache $cache, IConfig $config, IClientService $clientService, ILogger $logger) {
		$this->config = $config;
		$this->apiUrl = $config->getAppValue('bookmarks', 'previews.screenly.url', 'http://screeenly.com/api/v1/fullsize');
		$this->apiKey = $config->getAppValue('bookmarks', 'previews.screenly.token', '');
		$this->client = $clientService->newClient();
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
		if ('' === $this->apiKey) {
			return null;
		}
		$url = $bookmark->getUrl();

		// Fetch image from remote server
		return $this->fetchImage($url);
	}

	/**
	 * @param $url
	 * @return Image|null
	 */
	public function fetchImage($url): ?Image {
		try {
			$response = $this->client->post($this->apiUrl, ['body' => [
				'key' => $this->apiKey,
				'url' => $url,
				'width' => $this->width,
				'height' => $this->height,
			],
				'timeout' => self::HTTP_TIMEOUT,
			]);
			$body = json_decode($response->getBody(), true);
		} catch (\Exception $e) {
			$this->logger->logException($e, ['app' => 'bookmarks']);
			return null;
		}

		// Some HTPP Error occured :/
		if (200 !== $response->getStatusCode()) {
			return null;
		}

		return new Image('image/jpeg', base64_decode($body['base64_raw']));
	}
}
