<?php
/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Settings;

use OCA\Bookmarks\Service\SettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IInitialStateService;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {
	/**
	 * Admin constructor.
	 *
	 * @param $appName
	 * @param SettingsService $settingsService
	 * @param IInitialStateService $initialState
	 */
	public function __construct(
		private $appName,
		private SettingsService $settingsService,
		private IInitialStateService $initialState,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm() {
		$this->initialState->provideInitialState($this->appName, 'adminSettings', $this->settingsService->getAll());

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
