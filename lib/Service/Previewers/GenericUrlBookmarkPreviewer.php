<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service\Previewers;

use Exception;
use OCA\Bookmarks\Contract\IBookmarkPreviewer;
use OCA\Bookmarks\Contract\IImage;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Image;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class GenericUrlBookmarkPreviewer implements IBookmarkPreviewer {
	public const CACHE_PREFIX = 'bookmarks.GenericPreviewService';

	public const HTTP_TIMEOUT = 10 * 1000;

	/**
	 * @var string
	 */
	private $apiKey;

	/**
	 * @var IClient
	 */
	private $client;


	/** @var LoggerInterface */
	private $logger;

	/**
	 * @var int
	 */
	private $width = 800;

	/**
	 * @var int
	 */
	private $height = 800;

	/**
	 * @var string
	 */
	private $apiUrl;

	public function __construct(IConfig $config, IClientService $clientService, LoggerInterface $logger) {
		$this->apiUrl = $config->getAppValue('bookmarks', 'previews.generic.url', '');
		$this->client = $clientService->newClient();
		$this->logger = $logger;
	}

	/**
	 * @param Bookmark|null $bookmark
	 *
	 * @return Image|null
	 */
	public function getImage($bookmark, $cacheOnly = false): ?IImage {
		if (!isset($bookmark)) {
			return null;
		}
		if ($this->apiUrl === '' || $cacheOnly) {
			return null;
		}
		$url = $bookmark->getUrl();

		// Fetch image from remote server
		return $this->fetchImage($url);
	}

	/**
	 * @param string $url
	 * @return Image|null
	 */
	public function fetchImage($url): ?Image {
		try {
			$response = $this->client->get(str_replace('{url}', urlencode($url), $this->apiUrl), [
				'timeout' => self::HTTP_TIMEOUT,
			]);
			$body = $response->getBody();
		} catch (Exception $e) {
			$this->logger->debug($e->getMessage(), ['app' => 'bookmarks']);
			return null;
		}

		// Some HTPP Error occured :/
		if ($response->getStatusCode() !== 200) {
			return null;
		}

		return new Image('image/jpeg', $body);
	}
}
