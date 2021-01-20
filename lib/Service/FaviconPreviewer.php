<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use Exception;
use OCA\Bookmarks\Contract\IBookmarkPreviewer;
use OCA\Bookmarks\Contract\IImage;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Image;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

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
	 * @var LoggerInterface
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

	public function __construct(FileCache $cache, LinkExplorer $linkExplorer, LoggerInterface $logger, IClientService $clientService, \OCP\IConfig $config) {
		$this->cache = $cache;
		$this->linkExplorer = $linkExplorer;
		$this->logger = $logger;
		$this->client = $clientService->newClient();

		$this->enabled = $config->getAppValue('bookmarks', 'privacy.enableScraping', 'false');
	}

	/**
	 * @param Bookmark $bookmark
	 *
	 * @return Image|null
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

	public function scrapeUrl($url): array {
		return $this->linkExplorer->get($url);
	}

	/**
	 * @param $url
	 * @return Image|null
	 */
	protected function fetchImage(string $url): ?Image {
		try {
			$response = $this->client->get($url, ['timeout' => self::HTTP_TIMEOUT]);
		} catch (Exception $e) {
			$this->logger->debug($e->getMessage(), ['app' => 'bookmarks']);
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
