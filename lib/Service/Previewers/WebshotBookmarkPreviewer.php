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

class WebshotBookmarkPreviewer implements IBookmarkPreviewer {

	const CACHE_PREFIX = 'bookmarks.WebshotPreviewService';

	const HTTP_TIMEOUT = 10 * 1000;

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
		$this->apiUrl = $config->getAppValue('bookmarks', 'previews.webshot.url', '');
		$this->cache = $cache;
		$this->client = $clientService->newClient();
		$this->logger = $logger;
	}

	private function buildKey($url) {
		return self::CACHE_PREFIX . '-' . md5($url);
	}

	/**
	 * @param Bookmark $bookmark
	 * @return IImage|null
	 */
	public function getImage($bookmark): ?IImage {
		if (!isset($bookmark)) {
			return null;
		}
		if ($this->apiUrl === '') {
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
			// create screenshot
			$response = $this->client->post($this->apiUrl, ['body' => [
				'url' => $url,
				'view' => ['width' => $this->width],
			],
				'timeout' => self::HTTP_TIMEOUT,
			]);
			// Some HTPP Error occured :/
			if (200 !== $response->getStatusCode()) {
				return null;
			}
			$data = json_decode($response->getBody(), true);

			// get it
			$response = $this->client->get($this->apiUrl . $data->id);
			// Some HTPP Error occured :/
			if (200 !== $response->getStatusCode()) {
				return null;
			}
			$body = $response->getBody();
		} catch (\Exception $e) {
			$this->logger->logException($e, ['app' => 'bookmarks']);
			return null;
		}



		return new Image('image/jpeg', $body);
	}
}
