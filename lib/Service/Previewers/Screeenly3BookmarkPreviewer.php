<?php

/*
 * Copyright (c) 2026. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service\Previewers;

use Exception;
use OCA\Bookmarks\Contract\IBookmarkPreviewer;
use OCA\Bookmarks\Contract\IImage;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Exception\PreviewTemporarilyUnavailableError;
use OCA\Bookmarks\Image;
use OCP\AppFramework\Http;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Uses version 3 of screeenly (https://3.screeenly.com).
 * The API returns a URL to the screenshot, which is downloaded in a second request.
 */
class Screeenly3BookmarkPreviewer implements IBookmarkPreviewer {
	public const CACHE_PREFIX = 'bookmarks.Screeenly3PreviewService';

	public const API_URL = 'https://3.screeenly.com/api/v1/shots';

	// In seconds
	public const HTTP_TIMEOUT = 30;

	// In seconds
	public const RETRY_AFTER_RATE_LIMITED = 5 * 60;

	// In seconds
	public const RETRY_AFTER_QUOTA_USED_UP = 24 * 60 * 60;

	// In seconds
	public const RETRY_AFTER_FORBIDDEN = 24 * 60 * 60;

	private string $apiKey;

	private IClient $client;

	private int $width = 800;

	private int $height = 800;

	public function __construct(
		IConfig $config,
		IClientService $clientService,
		private LoggerInterface $logger,
	) {
		$this->apiKey = $config->getAppValue('bookmarks', 'previews.screeenly3.token', '');
		$this->client = $clientService->newClient();
	}

	/**
	 * @param Bookmark|null $bookmark
	 *
	 * @return Image|null
	 * @throws PreviewTemporarilyUnavailableError
	 */
	public function getImage($bookmark, $cacheOnly = false): ?IImage {
		if (!isset($bookmark) || $cacheOnly) {
			return null;
		}
		if ($this->apiKey === '') {
			return null;
		}

		return $this->fetchImage($bookmark->getUrl());
	}

	/**
	 * @throws PreviewTemporarilyUnavailableError
	 */
	public function fetchImage(string $url): ?Image {
		try {
			$response = $this->client->post(self::API_URL, [
				'headers' => [
					'Authorization' => 'Bearer ' . $this->apiKey,
					'Accept' => 'application/json',
				],
				'json' => [
					'url' => $url,
					'file_type' => 'jpg',
					'window_width' => $this->width,
					'window_height' => $this->height,
				],
				'timeout' => self::HTTP_TIMEOUT,
				'http_errors' => false,
			]);
		} catch (Exception $e) {
			$this->logger->debug($e->getMessage(), ['app' => 'bookmarks']);
			return null;
		}

		$this->ensureAccountCanCreateShots($response);

		try {
			// 201 for a new shot, 200 for a cached one
			if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 201) {
				return null;
			}
			$body = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
			if (!isset($body['data']['shot_url'])) {
				return null;
			}

			$imageResponse = $this->client->get($body['data']['shot_url'], [
				'timeout' => self::HTTP_TIMEOUT,
			]);
			if ($imageResponse->getStatusCode() !== 200) {
				return null;
			}
		} catch (Exception $e) {
			$this->logger->debug($e->getMessage(), ['app' => 'bookmarks']);
			return null;
		}

		return new Image('image/jpeg', $imageResponse->getBody());
	}

	/**
	 * screeenly responds with 429 when the account sends too many requests per minute or at once,
	 * with 402 when the monthly quota and the credits are used up, with 401 when the token is
	 * invalid, revoked or expired, and with 403 when the account is blocked, its email address
	 * isn't verified or the token can't create shots.
	 *
	 * @throws PreviewTemporarilyUnavailableError
	 */
	private function ensureAccountCanCreateShots(IResponse $response): void {
		$statusCode = $response->getStatusCode();

		if ($statusCode === Http::STATUS_TOO_MANY_REQUESTS) {
			$retryAfter = (int)$response->getHeader('Retry-After');
			throw new PreviewTemporarilyUnavailableError(
				'screeenly rate limit reached',
				max($retryAfter, self::RETRY_AFTER_RATE_LIMITED),
			);
		}

		if ($statusCode === Http::STATUS_PAYMENT_REQUIRED) {
			throw new PreviewTemporarilyUnavailableError(
				'screeenly monthly quota and credits are used up',
				self::RETRY_AFTER_QUOTA_USED_UP,
			);
		}

		if ($statusCode === Http::STATUS_UNAUTHORIZED || $statusCode === Http::STATUS_FORBIDDEN) {
			throw new PreviewTemporarilyUnavailableError(
				'screeenly token or account can\'t create screenshots',
				self::RETRY_AFTER_FORBIDDEN,
			);
		}
	}
}
