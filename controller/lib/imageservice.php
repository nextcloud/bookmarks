<?php
/**
 * @author Marcel Klehr
 * @copyright 2016 Marcel Klehr mklehr@gmx.net
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

namespace OCA\Bookmarks\Controller\Lib;

use OCP\ICache;
use OCP\ICacheFactory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;

class ImageService {

	// Cache for one month
	const CACHE_TTL = 4 * 7 * 24 * 60 * 60;

	/** @var ICache */
	private $cache;

	/**
	 * @param ICacheFactory $cacheFactory
	 */
	public function __construct(ICacheFactory $cacheFactory) {
		$this->cache = $cacheFactory->create('bookmarks.images');
	}

	private function buildKey($url) {
		return base64_encode($url);
	}

	/**
	 * @param string $url
	 * @return string|null image data
	 */
	public function getImage($url) {
		$key = $this->buildKey($url);
		// Try cache first
		if ($image = $this->cache->get($key)) {
			 $image = json_decode($image, true);
			 return [
			 	'contentType' => $image['contentType'],
				'data' => base64_decode($image['data'])
			];
		}

		// Fetch image from remote server
		$image = $this->fetchImage($url);

		if (is_null($image)) {
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
	
	/**
	 * @param string $url
	 * @return string|null fetched image data
	 */
	private function fetchImage($url) {	
		$body = $contentType = '';
		try {
			$client = new \GuzzleHTTP\Client();
			$request = $client->get($url);
			$body = $request->getBody();
			$contentType = $request->getHeader('Content-Type');
		} catch (\GuzzleHttp\Exception\RequestException $e) {
			return null;
		} catch (\Exception $e) {
			throw $e;
		}

		// Some HTPP Error occured :/
		if (200 != $request->getStatusCode()) {
			return null;
		}

		// It's not actually an image, doh.
		if (!$contentType || stripos($contentType, 'image') !== 0) {
			return null;
		}
		
		return [
			'contentType' => $contentType,
			'data' => $body
		];
	}
}

