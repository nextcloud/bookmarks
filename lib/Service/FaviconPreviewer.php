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

use OCA\Bookmarks\Contract\IImage;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Image;
use OCA\Bookmarks\Service\Previewers\DefaultBookmarkPreviewer;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

class FaviconPreviewer extends DefaultBookmarkPreviewer {
	public const CACHE_TTL = 4 * 4 * 7 * 24 * 60 * 60; // cache for one month
    public const CACHE_PREFIX = 'bookmarks.FaviconPreviewer';

	/**
	 * @param Bookmark $bookmark
	 * @return IImage|null
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getImage($bookmark): ?IImage {
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
}
