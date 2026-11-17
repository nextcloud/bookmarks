<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use OCA\Bookmarks\Contract\IBookmarkPreviewer;
use OCA\Bookmarks\Contract\IImage;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Exception\PreviewTemporarilyUnavailableError;
use OCA\Bookmarks\Image;
use OCA\Bookmarks\Service\Previewers\DefaultBookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\GenericUrlBookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\PageresBookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\Screeenly3BookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\ScreeenlyBookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\ScreenshotMachineBookmarkPreviewer;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;

class BookmarkPreviewer implements IBookmarkPreviewer {
	// Cache for one month
	public const CACHE_TTL = 4 * 4 * 7 * 24 * 60 * 60;
	private FileCache $cache;
	private DefaultBookmarkPreviewer $defaultPreviewer;
	private Screeenly3BookmarkPreviewer $screeenly3Previewer;
	private ScreeenlyBookmarkPreviewer $screeenlyPreviewer;
	private ScreenshotMachineBookmarkPreviewer $screenshotMachinePreviewer;
	private PageresBookmarkPreviewer $pageresPreviewer;
	private Previewers\GenericUrlBookmarkPreviewer $genericUrlPreviewer;

	public function __construct(
		private IConfig $config,
		Screeenly3BookmarkPreviewer $screeenly3Previewer,
		ScreeenlyBookmarkPreviewer $screeenlyPreviewer,
		DefaultBookmarkPreviewer $defaultPreviewer,
		FileCache $cache,
		ScreenshotMachineBookmarkPreviewer $screenshotMachinePreviewer,
		PageresBookmarkPreviewer $pageresPreviewer,
		GenericUrlBookmarkPreviewer $genericUrlPreviewer,
	) {
		$this->screeenly3Previewer = $screeenly3Previewer;
		$this->screeenlyPreviewer = $screeenlyPreviewer;
		$this->defaultPreviewer = $defaultPreviewer;
		$this->screenshotMachinePreviewer = $screenshotMachinePreviewer;
		$this->pageresPreviewer = $pageresPreviewer;
		$this->genericUrlPreviewer = $genericUrlPreviewer;

		$this->cache = $cache;
	}

	/**
	 * When a previewer is temporarily unavailable, the next previewers are tried as usual.
	 * Its result isn't cached, and the exception is rethrown after the other previewers ran,
	 * so the caller can try again later. An image from a later previewer is cached before that.
	 *
	 * @param Bookmark $bookmark
	 * @return IImage
	 * @throws PreviewTemporarilyUnavailableError
	 */
	public function getImage($bookmark, $cacheOnly = false): ?IImage {
		if ($this->config->getAppValue('bookmarks', 'privacy.enableScraping', 'false') === 'false') {
			return null;
		}

		if (!isset($bookmark)) {
			return null;
		}

		if (!$bookmark->isWebLink()) {
			return null;
		}

		$previewers = [
			$this->screeenly3Previewer,
			$this->screeenlyPreviewer,
			$this->screenshotMachinePreviewer,
			$this->pageresPreviewer,
			$this->genericUrlPreviewer,
			$this->defaultPreviewer,
		];
		$result = null;
		$temporaryError = null;
		foreach ($previewers as $previewer) {
			$key = $previewer::CACHE_PREFIX . '-' . md5($bookmark->getUrl());
			// Try cache first
			try {
				if ($image = $this->cache->get($key)) {
					if ($image === 'null') {
						continue;
					}
					$result = Image::deserialize($image);
					break;
				}
			} catch (NotFoundException $e) {
			} catch (NotPermittedException $e) {
			}
			if ($cacheOnly) {
				continue;
			}
			try {
				$image = $previewer->getImage($bookmark, $cacheOnly);
			} catch (PreviewTemporarilyUnavailableError $e) {
				$temporaryError ??= $e;
				continue;
			}
			if (isset($image)) {
				$this->cache->set($key, $image->serialize(), self::CACHE_TTL);
				$result = $image;
				break;
			}
			$this->cache->set($key, 'null', self::CACHE_TTL);
		}

		if ($temporaryError !== null) {
			throw $temporaryError;
		}

		return $result;
	}
}
