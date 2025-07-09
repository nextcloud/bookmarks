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
use OCA\Bookmarks\Image;
use OCA\Bookmarks\Service\Previewers\DefaultBookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\GenericUrlBookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\PageresBookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\ScreeenlyBookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\ScreenshotMachineBookmarkPreviewer;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;

class BookmarkPreviewer implements IBookmarkPreviewer {
	// Cache for one month
	public const CACHE_TTL = 4 * 4 * 7 * 24 * 60 * 60;
	private string $enabled;
	private FileCache $cache;
	private DefaultBookmarkPreviewer $defaultPreviewer;
	private ScreeenlyBookmarkPreviewer $screeenlyPreviewer;
	private ScreenshotMachineBookmarkPreviewer $screenshotMachinePreviewer;
	private PageresBookmarkPreviewer $pageresPreviewer;
	private Previewers\GenericUrlBookmarkPreviewer $genericUrlPreviewer;

	public function __construct(
		IConfig $config,
		ScreeenlyBookmarkPreviewer $screeenlyPreviewer,
		DefaultBookmarkPreviewer $defaultPreviewer,
		FileCache $cache,
		ScreenshotMachineBookmarkPreviewer $screenshotMachinePreviewer,
		PageresBookmarkPreviewer $pageresPreviewer,
		GenericUrlBookmarkPreviewer $genericUrlPreviewer,
	) {
		$this->screeenlyPreviewer = $screeenlyPreviewer;
		$this->defaultPreviewer = $defaultPreviewer;
		$this->screenshotMachinePreviewer = $screenshotMachinePreviewer;
		$this->pageresPreviewer = $pageresPreviewer;
		$this->genericUrlPreviewer = $genericUrlPreviewer;

		$this->enabled = $config->getAppValue('bookmarks', 'privacy.enableScraping', 'false');
		$this->cache = $cache;
	}

	/**
	 * @param Bookmark $bookmark
	 * @return IImage
	 */
	public function getImage($bookmark, $cacheOnly = false): ?IImage {
		if ($this->enabled === 'false') {
			return null;
		}

		if (!isset($bookmark)) {
			return null;
		}

		if (!$bookmark->isWebLink()) {
			return null;
		}

		$previewers = [
			$this->screeenlyPreviewer,
			$this->screenshotMachinePreviewer,
			$this->pageresPreviewer,
			$this->genericUrlPreviewer,
			$this->defaultPreviewer,
		];
		foreach ($previewers as $previewer) {
			$key = $previewer::CACHE_PREFIX . '-' . md5($bookmark->getUrl());
			// Try cache first
			try {
				if ($image = $this->cache->get($key)) {
					if ($image === 'null') {
						continue;
					}
					return Image::deserialize($image);
				}
			} catch (NotFoundException $e) {
			} catch (NotPermittedException $e) {
			}
			$image = $previewer->getImage($bookmark, $cacheOnly);
			if (isset($image)) {
				$this->cache->set($key, $image->serialize(), self::CACHE_TTL);
				return $image;
			}

			$this->cache->set($key, 'null', self::CACHE_TTL);
		}

		return null;
	}
}
