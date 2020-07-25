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
use OCA\Bookmarks\Service\Previewers\DefaultBookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\ScreeenlyBookmarkPreviewer;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;

class BookmarkPreviewer implements IBookmarkPreviewer {
	// Cache for one month
	const CACHE_TTL = 4 * 4 * 7 * 24 * 60 * 60;

	/** @var IConfig */
	private $config;
	/**
	 * @var string
	 */
	private $enabled;
	/**
	 * @var DefaultBookmarkPreviewer
	 */
	private $defaultPreviewer;
	/**
	 * @var ScreeenlyBookmarkPreviewer
	 */
	private $screeenlyPreviewer;

	/**
	 * @var FileCache
	 */
	private $cache;

	/**
	 * @param IConfig $config
	 * @param ScreeenlyBookmarkPreviewer $screeenlyPreviewer
	 * @param DefaultBookmarkPreviewer $defaultPreviewer
	 * @param FileCache $cache
	 */
	public function __construct(IConfig $config, ScreeenlyBookmarkPreviewer $screeenlyPreviewer, DefaultBookmarkPreviewer $defaultPreviewer, \OCA\Bookmarks\Service\FileCache $cache) {
		$this->config = $config;
		$this->screeenlyPreviewer = $screeenlyPreviewer;
		$this->defaultPreviewer = $defaultPreviewer;

		$this->enabled = $config->getAppValue('bookmarks', 'privacy.enableScraping', false);
		$this->cache = $cache;
	}

	/**
	 * @param Bookmark $bookmark
	 * @return IImage
	 */
	public function getImage($bookmark): ?IImage {
		if ($this->enabled === 'false') {
			return null;
		}

		if (!isset($bookmark)) {
			return null;
		}

		$previewers = [$this->screeenlyPreviewer, $this->defaultPreviewer];
		foreach ($previewers as $previewer) {
			$key = $previewer->buildKey($bookmark->getUrl());
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
			$image = $previewer->getImage($bookmark);
			if (isset($image)) {
				$this->cache->set($key, $image->serialize(), self::CACHE_TTL);
				return $image;
			}

			$this->cache->set($key, 'null', self::CACHE_TTL);
		}

		return null;
	}
}
