<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {
	/** @var IConfig */
	private $config;

	/** @var IL10N */
	private $l;
	/**
	 * @var IInitialStateService
	 */
	private $initialState;

	/**
	 * @var string
	 */
	private $appName;

	public const SETTINGS = [
		'previews.screenly.url',
		'previews.screenly.token',
		'previews.webshot.url',
		'previews.screenshotmachine.key',
		'privacy.enableScraping',
		'performance.maxBookmarksperAccount',
	];

	/**
	 * Admin constructor.
	 *
	 * @param IConfig $config
	 * @param IL10N $l
	 * @param IInitialStateService $initialState
	 */
	public function __construct(
		$appName,
		IConfig $config,
		IL10N   $l, IInitialStateService $initialState
	) {
		$this->appName = $appName;
		$this->config = $config;
		$this->l = $l;
		$this->initialState = $initialState;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm() {
		$settings = [];

		foreach (self::SETTINGS as $settingId) {
			$settings[$settingId] = $this->config->getAppValue('bookmarks', $settingId);
		}

		$this->initialState->provideInitialState($this->appName, 'adminSettings', $settings);

		return new TemplateResponse('bookmarks', 'admin');
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection() {
		return 'bookmarks';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of the admin section. The forms are arranged in ascending order of the priority values. It is required to return a value between 0 and 100.
	 */
	public function getPriority() {
		return 50;
	}
}
