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
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class ScreenshotMachineBookmarkPreviewer implements IBookmarkPreviewer {
	public const CACHE_PREFIX = 'bookmarks.ScreenshotMachinePreviewService';

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


	public function __construct(IConfig $config, IClientService $clientService, LoggerInterface $logger) {
		$this->apiKey = $config->getAppValue('bookmarks', 'previews.screenshotmachine.key', '');
		$this->client = $clientService->newClient();
		$this->logger = $logger;
	}

	/**
	 * @param Bookmark|null $bookmark
	 *
	 * @return Image|null
	 */
	public function getImage($bookmark): ?IImage {
		if (!isset($bookmark)) {
			return null;
		}
		if ($this->apiKey === '') {
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
			// get it
			$response = $this->client->get(
				"https://api.screenshotmachine.com/?key=". $this->apiKey . "&dimension=" . $this->width . "x" . $this->height . "&device=desktop&delay=2000&format=jpg&url=" . $url,
				[
					'timeout' => self::HTTP_TIMEOUT,
				]
			);
			// Some HTPP Error occured :/
			if (200 !== $response->getStatusCode()) {
				return null;
			}
			$body = $response->getBody();
		} catch (Exception $e) {
			$this->logger->warning($e->getMessage(), ['app' => 'bookmarks']);
			return null;
		}

		return new Image('image/jpeg', $body);
	}
}
