<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\ICache;

class FileCache implements ICache {
	public const TIMEOUT = 60 * 60 * 24 * 30 * 2; // two months

	protected $storage;

	public function __construct(IAppData $appData) {
		try {
			$this->storage = $appData->getFolder('cache');
		} catch (NotFoundException $e) {
			$appData->newFolder('cache');
			$this->storage = $appData->getFolder('cache');
		}
	}

	/**
	 * @param string $key
	 * @return mixed|null
	 * @throws NotFoundException
	 * @throws NotPermittedException
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
	 * @throws NotFoundException
	 */
	public function size($key): int {
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
	 * @return bool
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function set($key, $value, $ttl = 0) {
		$file = $this->storage->newFile($key);
		$file->putContent($value);
		return true;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function hasKey($key) {
		if ($this->storage->fileExists($key)) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $key
	 * @return bool
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function remove($key) {
		return (boolean) $this->storage->getFile($key)->delete();
	}

	/**
	 * @param string $prefix
	 * @return void
	 * @throws NotPermittedException
	 */
	public function clear($prefix = '') {
		$this->storage->delete();
	}

	/**
	 * Runs GC
	 *
	 * @throws NotPermittedException
	 */
	public function gc(): void {
		foreach ($this->storage->getDirectoryListing() as $file) {
			if (time() - self::TIMEOUT > $file->getMTime()) {
				$file->delete();
			}
		}
	}
}
