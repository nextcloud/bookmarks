<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\ICache;

class FileCache implements ICache {
	public const TIMEOUT = 60 * 60 * 24 * 30 * 2; // two months

	protected $storage;

	/**
	 * @var ITimeFactory
	 */
	private $timeFactory;

	/**
	 * @var IAppData
	 */
	private $appData;

	public function __construct(IAppData $appData, ITimeFactory $timeFactory) {
		$this->appData = $appData;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @return ISimpleFolder
	 * @throws NotPermittedException
	 */
	private function getStorage(): ISimpleFolder {
		if ($this->storage !== null) {
			return $this->storage;
		}
		try {
			$this->storage = $this->appData->getFolder('cache');
		} catch (NotFoundException $e) {
			// noop
		}
		if ($this->storage === null || !$this->storage->fileExists('/')) {
			$this->storage = $this->appData->newFolder('cache');
		}
		if (!$this->storage->fileExists('CACHEDIR.TAG')) {
			try {
				$this->storage->newFile('CACHEDIR.TAG',
					'Signature: 8a477f597d28d172789f06886806bc55' . "\r\n" .
					'# This file is a cache directory tag created by the nextcloud bookmarks app.' . "\r\n" .
					'# For information about cache directory tags, see:' . "\r\n" .
					'#       http://www.brynosaurus.com/cachedir/)' . "\r\n"
				);
			} catch (NotPermittedException $e) {
				// No op
			}
		}
		return $this->storage;
	}

	/**
	 * @param string $key
	 * @return mixed|null
	 */
	public function get($key) {
		$result = null;
		try {
			$result = $this->getStorage()->getFile($key)->getContent();
		} catch (\Exception $e) {
			// noop
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
			$result = $this->getStorage()->getFile($key)->getSize();
		}
		return $result;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @param int $ttl
	 * @return bool
	 */
	public function set($key, $value, $ttl = 0) {
		try {
			$this->getStorage()->newFile($key, $value);
		} catch (NotPermittedException $e) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function hasKey($key) {
		if ($this->getStorage()->fileExists($key)) {
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
		return (boolean) $this->getStorage()->getFile($key)->delete();
	}

	/**
	 * @param string $prefix
	 * @return void
	 * @throws NotPermittedException
	 */
	public function clear($prefix = '') {
		foreach ($this->getStorage()->getDirectoryListing() as $file) {
			$file->delete();
		}
	}

	/**
	 * Runs GC
	 *
	 * @throws NotPermittedException
	 */
	public function gc(): void {
		foreach ($this->getStorage()->getDirectoryListing() as $file) {
			if ($this->timeFactory->getTime() - self::TIMEOUT > $file->getMTime()) {
				$file->delete();
			}
		}
	}
}
