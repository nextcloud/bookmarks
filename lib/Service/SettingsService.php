<?php

declare(strict_types=1);

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use OCP\AppFramework\Services\IAppConfig;

class SettingsService {
	/** @var array<string,string> */
	public const DEFAULTS = [
		'previews.screenly.url' => '',
		'previews.screenly.token' => '',
		'previews.webshot.url' => '',
		'previews.screenshotmachine.key' => '',
		'previews.pageres.env' => '',
		'previews.generic.url' => '',
		'privacy.enableScraping' => 'false',
		'performance.maxBookmarksperAccount' => '0',
	];

	public function __construct(
		private IAppConfig $config,
	) {
	}

	/**
	 * @param string $key
	 * @return string
	 */
	public function getSetting(string $key): string {
		return $this->config->getAppValue($key, self::DEFAULTS[$key]);
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @return void
	 */
	public function setSetting(string $key, string $value): void {
		if (!array_key_exists($key, self::DEFAULTS)) {
			throw new \Exception('Unknown settings key ' . $key);
		}
		$this->config->setAppValue($key, $value);
	}

	/**
	 * @return array
	 */
	public function getAll(): array {
		$settings = [];
		foreach (array_keys(self::DEFAULTS) as $key) {
			$settings[$key] = $this->getSetting($key);
		}
		return $settings;
	}
}
