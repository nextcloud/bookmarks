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
use OCA\Bookmarks\Service\FileCache;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\ILogger;

class WebshotBookmarkPreviewer implements IBookmarkPreviewer {
	public const CACHE_PREFIX = 'bookmarks.WebshotPreviewService';

	public const HTTP_TIMEOUT = 10 * 1000;

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
			$data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);

			// get it
			$response = $this->client->get($this->apiUrl . $data->id);
			// Some HTPP Error occured :/
			if (200 !== $response->getStatusCode()) {
				return null;
			}
			$body = $response->getBody();
		} catch (Exception $e) {
			$this->logger->logException($e, ['app' => 'bookmarks']);
			return null;
		}



		return new Image('image/jpeg', $body);
	}
}
