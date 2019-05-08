<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sebastian Wessalowski <sebastian@wessalowski.org>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\Bookmarks;

use OCP\Files\IAppData;
use OCP\ICache;

class FileCache implements ICache {
	const TIMEOUT = 60*60*24*30*2; // two months

	protected $storage;

	public function __construct(IAppData $appData) {
		try {
			$this->storage = $appData->getFolder('cache');
		} catch (\OCP\Files\NotFoundException $e) {
			$appData->newFolder('cache');
			$this->storage = $appData->getFolder('cache');
		}
	}

	/**
	 * @param string $key
	 * @return mixed|null
	 * @throws \OC\ForbiddenException
	 */
	public function get($key) {
		$result = null;
		if ($this->hasKey($key)) {
			$result = $this->storage->getFile($key)->getContent();
		}
		return $result;
	}
	/**
	 * Returns the size of the stored/cached data
	 *
	 * @param string $key
	 * @return int
	 */
	public function size($key) {
		$result = 0;
		if ($this->hasKey($key)) {
			$result = $this->storage->getFile($key)->getSize();
		}
		return $result;
	}
	/**
	 * @param string $key
	 * @param mixed $value
	 * @param int $ttl
	 * @return bool|mixed
	 * @throws \OC\ForbiddenException
	 */
	public function set($key, $value, $ttl = 0) {
		$file = $this->storage->newFile($key);
		$file->putContent($value);
		return true;
	}
	/**
	 * @param string $key
	 * @return bool
	 * @throws \OC\ForbiddenException
	 */
	public function hasKey($key) {
		if ($this->storage->fileExists($key)) {
			return true;
		}
		return false;
	}
	/**
	 * @param string $key
	 * @return bool|mixed
	 * @throws \OC\ForbiddenException
	 */
	public function remove($key) {
		return $this->storage->getFile($key)->delete();
	}
	/**
	 * @param string $prefix
	 * @return bool
	 * @throws \OC\ForbiddenException
	 */
	public function clear($prefix = '') {
		$this->storage->delete();
	}
	/**
	 * Runs GC
	 * @throws \OC\ForbiddenException
	 */
	public function gc() {
		foreach ($this->storage->getDirectoryListing() as $file) {
			if (time() - self::TIMEOUT > $file->getMTime()) {
				$file->delete();
			}
		}
	}
}
