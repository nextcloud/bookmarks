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

use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Service\Previewers\DefaultBookmarkPreviewer;
use OCP\ILogger;
use OCP\Http\Client\IClientService;

class FaviconPreviewer extends DefaultBookmarkPreviewer {
	/**
	 * @param FileCache $cache
	 * @param LinkExplorer $linkExplorer
	 * @param IClientService $clientService
	 * @param ILogger $logger
	 */
	public function __construct(FileCache $cache, LinkExplorer $linkExplorer, IClientService $clientService, ILogger $logger) {
		parent::__construct($cache, $linkExplorer, $clientService, $logger);
	}

	/**
	 * @param Bookmark $bookmark
	 * @return array|mixed|null
	 */
	public function getImage($bookmark) {
		if (!isset($bookmark)) {
			return null;
		}
		$url = $bookmark->getUrl();
		$site = $this->scrapeUrl($url);

		if (isset($site['favicon'])) {
			$image = $this->getOrFetchImageUrl($site['favicon']);
			if (!is_null($image)) {
				return $image;
			}
		}

		$url_parts = parse_url($bookmark->getUrl());

		if (isset($url_parts['scheme'], $url_parts['host'])) {
			return $this->getOrFetchImageUrl(
				$url_parts['scheme'] . '://' . $url_parts['host'] . '/favicon.ico'
			);
		}
		return null;
	}
}
