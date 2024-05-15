<?php
/*
 * Copyright (c) 2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use OCP\IConfig;
use OCP\IL10N;

class UserSettingsService {

	public const KEYS = ['hasSeenWhatsnew', 'viewMode', 'archive.enabled', 'archive.filePath', 'backup.enabled', 'backup.filePath', 'sorting', 'tagging.enabled'];

	public function __construct(
		private ?string $userId,
		private string $appName,
		private IConfig $config,
		private IL10N $l,
	) {

	}

	public function setUserId(?string $userId): void {
		$this->userId = $userId;
	}

	/**
	 * @param string $key
	 * @return string
	 */
	public function get(string $key): string {
		if ($key === 'sorting') {
			$default = 'lastmodified';
		}
		if ($key === 'hasSeenWhatsnew') {
			$default = '0';
		}
		if ($key === 'viewMode') {
			$default = 'grid';
		}
		if ($key === 'limit') {
			return $this->config->getAppValue('bookmarks', 'performance.maxBookmarksperAccount', 0);
		}
		if ($key === 'archive.enabled') {
			$default = (string) true;
		}
		if ($key === 'privacy.enableScraping') {
			return $this->config->getAppValue($this->appName, 'privacy.enableScraping', 'false');
		}
		if ($key === 'archive.filePath') {
			$default = $this->l->t('Bookmarks');
		}
		if ($key === 'backup.enabled') {
			$default = (string) false;
		}
		if ($key === 'backup.filePath') {
			$default = $this->l->t('Bookmarks Backups');
		}
		if ($key === 'tagging.enabled') {
			$default = (string) false;
		}
		return $this->config->getUserValue(
			$this->userId,
			$this->appName,
			$key,
			$default
		);
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @return void
	 * @throws \ValueError|\UnexpectedValueException
	 */
	public function set(string $key, string $value): void {
		if (!in_array($key, self::KEYS)) {
			throw new \UnexpectedValueException();
		}
		if ($key === 'viewMode' && !in_array($value, ['grid', 'list'], true)) {
			throw new \ValueError();
		}
		if ($key === 'sorting' && !in_array($value, ['title', 'added', 'clickcount', 'lastmodified', 'index', 'url'], true)) {
			throw new \ValueError();
		}
		$this->config->setUserValue(
			$this->userId,
			$this->appName,
			$key,
			$value
		);
	}

	/**
	 * @return array
	 */
	public function toArray(): array {
		$array = [];
		foreach(self::KEYS as $key) {
			$array[$key] = $this->get($key);
		}
		$array['limit'] = $this->get('limit');
		$array['privacy.enableScraping'] = $this->get('privacy.enableScraping');
		return $array;
	}
}
