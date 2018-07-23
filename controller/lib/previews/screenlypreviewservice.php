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

use Wnx\ScreeenlyClient\Screenshot;
use OCP\ICache;
use OCP\IConfig;
use OCP\ICacheFactory;

class ScreenlyPreviewService implements IPreviewService {
	// Cache for one month
	const CACHE_TTL = 4 * 7 * 24 * 60 * 60;

	private $apiKey;

	/** @var IConfig */
	private $config;

	/** @var ICache */
	private $cache;

	private $width = 800;

	private $height = 800;

	/**
	 * @param ICacheFactory $cacheFactory
	 */
	public function __construct(ICacheFactory $cacheFactory, IConfig $config) {
		$this->config = $config;
		$this->apiUrl = $config->getAppValue('bookmarks', 'previews.screenly.url', 'http://screeenly.com/api/v1/fullsize');
		$this->apiKey = $config->getAppValue('bookmarks', 'previews.screenly.token', '');
		$this->cache = $cacheFactory->create('bookmarks.ScreenlyPreviewService');
	}

	private function buildKey($url) {
		return base64_encode($url);
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

		try {
			// Fetch image from remote server
			$image = $this->fetchScreenshot($url);
		} catch (\Exception $e) {
			\OCP\Util::writeLog('bookmarks', $e, \OCP\Util::WARN);
			// TODO: We could return an error image here
			return null;
		}

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
			$client = new \GuzzleHTTP\Client();
			$request = $client->post($this->apiUrl, ['body' => [
					'key'    => $this->apiKey,
					'url'    => $url,
					'width'  => $this->width,
					'height' => $this->height
				],
				'timeout' => 4
		  ]);
			$body = $request->json();
		} catch (\GuzzleHttp\Exception\RequestException $e) {
			\OCP\Util::writeLog('bookmarks', $e, \OCP\Util::WARN);
			if ($e->hasResponse()) {
				if ($e->getResponse()->getStatusCode() === 404) {
					return null;
				}
			}
			throw $e;
		}

		// Some HTPP Error occured :/
		if (200 != $request->getStatusCode()) {
			return null;
		}

		return [
			'contentType' => 'image/jpeg',
			'data' => base64_decode($body['base64_raw'])
		];
	}
}
