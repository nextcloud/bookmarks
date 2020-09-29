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

namespace OCA\Bookmarks\Service;

use OCA\Bookmarks\Contract\IBookmarkPreviewer;
use OCA\Bookmarks\Contract\IImage;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Image;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\ILogger;

class FaviconPreviewer implements IBookmarkPreviewer {
	public const CACHE_TTL = 4 * 4 * 7 * 24 * 60 * 60; // cache for one month
	public const HTTP_TIMEOUT = 10 * 1000;
	public const CACHE_PREFIX = 'bookmarks.FaviconPreviewer';

	/**
	 * @var FileCache
	 */
	private $cache;

	/**
	 * @var LinkExplorer
	 */
	private $linkExplorer;

	/**
	 * @var ILogger
	 */
	private $logger;

	/**
	 * @var IClient
	 */
	private $client;
	/**
	 * @var \OCP\IConfig
	 */
	private $config;
	/**
	 * @var string
	 */
	private $enabled;

	public function __construct(FileCache $cache, LinkExplorer $linkExplorer, ILogger $logger, IClientService $clientService, \OCP\IConfig $config) {
		$this->cache = $cache;
		$this->linkExplorer = $linkExplorer;
		$this->logger = $logger;
		$this->client = $clientService->newClient();

		$this->enabled = $config->getAppValue('bookmarks', 'privacy.enableScraping', 'false');
	}

	/**
	 * @param Bookmark $bookmark
	 * @return IImage|null
	 */
	public function getImage($bookmark): ?IImage {
		if ($this->enabled === 'false') {
			return null;
		}
		if (!isset($bookmark)) {
			return null;
		}
		$key = self::CACHE_PREFIX . '-' . md5($bookmark->getUrl());
		// Try cache first
		try {
			if ($image = $this->cache->get($key)) {
				if ($image === 'null') {
					return null;
				}
				return Image::deserialize($image);
			}
		} catch (NotFoundException $e) {
		} catch (NotPermittedException $e) {
		}

		$url = $bookmark->getUrl();
		$site = $this->scrapeUrl($url);

		if (isset($site['favicon'])) {
			$image = $this->fetchImage($site['favicon']);
			if ($image !== null) {
				$this->cache->set($key, $image->serialize(), self::CACHE_TTL);
				return $image;
			}
		}

		$url_parts = parse_url($bookmark->getUrl());

		if (isset($url_parts['scheme'], $url_parts['host'])) {
			$image = $this->fetchImage(
				$url_parts['scheme'] . '://' . $url_parts['host'] . '/favicon.ico'
			);
			if ($image !== null) {
				$this->cache->set($key, $image->serialize(), self::CACHE_TTL);
				return $image;
			}
		}

		$this->cache->set($key, 'null', self::CACHE_TTL);
		return null;
	}

	public function scrapeUrl($url) {
		return $this->linkExplorer->get($url);
	}

	/**
	 * @param $url
	 * @return Image|null
	 */
	protected function fetchImage($url): ?Image {
		try {
			$response = $this->client->get($url, ['timeout' => self::HTTP_TIMEOUT]);
		} catch (\Exception $e) {
			$this->logger->debug($e, ['app' => 'bookmarks']);
			return null;
		}
		$body = $response->getBody();
		$contentType = $response->getHeader('Content-Type');

		// Some HTPP Error occured :/
		if (200 !== $response->getStatusCode()) {
			return null;
		}

		// It's not actually an image, doh.
		if (!isset($contentType) || stripos($contentType, 'image') !== 0) {
			return null;
		}

		return new Image($contentType, $body);
	}
}
