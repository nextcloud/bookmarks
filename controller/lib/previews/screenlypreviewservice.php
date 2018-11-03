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
namespace OCA\Bookmarks\Controller\Lib\Previews;

use OCP\ICache;
use OCP\IConfig;
use OCP\Http\Client\IClientService;

class ScreenlyPreviewService implements IPreviewService {
	// Cache for one month
	const CACHE_TTL = 4 * 4 * 7 * 24 * 60 * 60;
	const CACHE_PREFIX = 'bookmarks.ScreenlyPreviewService';

	const HTTP_TIMEOUT = 10 * 1000;

	private $apiKey;

	private $client;

	/** @var IConfig */
	private $config;

	/** @var ICache */
	private $cache;

	private $width = 800;

	private $height = 800;

	/**
	 * @param ICacheFactory $cacheFactory
	 */
	public function __construct(ICache $cache, IConfig $config, IClientService $clientService) {
		$this->config = $config;
		$this->apiUrl = $config->getAppValue('bookmarks', 'previews.screenly.url', 'http://screeenly.com/api/v1/fullsize');
		$this->apiKey = $config->getAppValue('bookmarks', 'previews.screenly.token', '');
		$this->cache = $cache;
		$this->client = $clientService->newClient();
	}

	private function buildKey($url) {
		return self::CACHE_PREFIX.'-'.md5($url);
	}

	/**
	 * @param string $url
	 * @return string|null image data
	 */
	public function getImage($bookmark) {
		if (!isset($bookmark)) {
			return null;
		}
		if ('' === $this->apiKey) {
			return null;
		}
		$url = $bookmark['url'];

		$key = $this->buildKey($url);
		// Try cache first
		if ($image = $this->cache->get($key)) {
			$image = json_decode($image, true);
			if (is_null($image)) {
				return null;
			}
			return [
				'contentType' => $image['contentType'],
				'data' => base64_decode($image['data'])
			];
		}

		// Fetch image from remote server
		$image = $this->fetchScreenshot($url);

		if (is_null($image)) {
			$json = json_encode(null);
			$this->cache->set($key, $json, self::CACHE_TTL);
			return null;
		}

		// Store in cache for next time
		$json = json_encode([
			'contentType' => $image['contentType'],
			'data' => base64_encode($image['data'])
		]);
		$this->cache->set($key, $json, self::CACHE_TTL);

		return $image;
	}

	public function fetchScreenshot($url) {
		try {
			$response = $this->client->post($this->apiUrl, ['body' => [
					'key'    => $this->apiKey,
					'url'    => $url,
					'width'  => $this->width,
					'height' => $this->height
				],
				'timeout' => self::HTTP_TIMEOUT
		  ]);
			$body = json_decode($response->getBody(), true);
		} catch (\Exception $e) {
			\OCP\Util::writeLog('bookmarks', $e, \OCP\Util::DEBUG);
			return null;
		}

		// Some HTPP Error occured :/
		if (200 !== $response->getStatusCode()) {
			return null;
		}

		return [
			'contentType' => 'image/jpeg',
			'data' => base64_decode($body['base64_raw'])
		];
	}
}
