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

namespace OCA\Bookmarks\Previews;

use OCP\ICache;
use OCP\ILogger;
use OCP\Http\Client\IClientService;
use OCA\Bookmarks\LinkExplorer;

class FaviconPreviewService extends DefaultPreviewService {
	/**
	 * @param ICacheFactory $cacheFactory
	 * @param LinkExplorer $linkExplorer
	 */
	public function __construct(ICache $cache, LinkExplorer $linkExplorer, IClientService $clientService, ILogger $logger) {
		parent::__construct($cache, $linkExplorer, $clientService, $logger);
	}

	public function getImage($bookmark) {
		if (!isset($bookmark)) {
			return null;
		}
		$url = $bookmark['url'];
		$site = $this->scrapeUrl($url);

		if (isset($site['favicon'])) {
			$image = $this->getOrFetchImageUrl($site['favicon']);
			if (!is_null($image)) {
				return $image;
			}
		}

		$url_parts = parse_url($bookmark['url']);

		return $this->getOrFetchImageUrl(
			$url_parts['scheme'] . '://' . $url_parts['host'] . '/favicon.ico'
		);
	}
}
