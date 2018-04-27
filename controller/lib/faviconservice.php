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

namespace OCA\Bookmarks\Controller\Lib;

use OCP\ICache;
use OCP\ICacheFactory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;

class FaviconService extends ImageService {
	/**
	 * @param ICacheFactory $cacheFactory
	 */
	public function __construct(ICacheFactory $cacheFactory) {
		parent::__construct($cacheFactory);
		$this->cache = $cacheFactory->create('bookmarks.favicons');
	}
}
