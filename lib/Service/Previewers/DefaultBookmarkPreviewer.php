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

namespace OCA\Bookmarks\Service\Previewers;

use Exception;
use OCA\Bookmarks\Contract\IBookmarkPreviewer;
use OCA\Bookmarks\Contract\IImage;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Image;
use OCA\Bookmarks\Service\LinkExplorer;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\ILogger;

class DefaultBookmarkPreviewer implements IBookmarkPreviewer {
	public const CACHE_PREFIX = 'bookmarks.DefaultPreviewService';
	public const HTTP_TIMEOUT = 10 * 1000;

	/** @var IClient */
	protected $client;

	/** @var LinkExplorer */
	protected $linkExplorer;

	/** @var ILogger */
	private $logger;

	/**
	 * @param LinkExplorer $linkExplorer
	 * @param IClientService $clientService
	 * @param ILogger $logger
	 */
	public function __construct(LinkExplorer $linkExplorer, IClientService $clientService, ILogger $logger) {
		$this->linkExplorer = $linkExplorer;
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
		$site = $this->scrapeUrl($bookmark->getUrl());
		$this->logger->debug('getImage for URL: ' . $bookmark->getUrl() . ' ' . var_export($site, true), ['app' => 'bookmarks']);
		if (isset($site['image']['small'])) {
			return $this->fetchImage($site['image']['small']);
		}
		if (isset($site['image']['large'])) {
			return $this->fetchImage($site['image']['large']);
		}
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
		} catch (Exception $e) {
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
