<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Controller;

use OCA\Bookmarks\Service\UserSettingsService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class SettingsController extends ApiController {

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param UserSettingsService $userSettingsService
	 */
	public function __construct(
		$appName, $request,
		private UserSettingsService $userSettingsService,
	) {
		parent::__construct($appName, $request);
	}

	public function getSetting(string $key): JSONResponse {
		try {
			$value = $this->userSettingsService->get($key);
		} catch (\UnexpectedValueException) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse([$key => $value], Http::STATUS_OK);
	}

	public function setSetting(string $key, string $value): JSONResponse {
		try {
			$this->userSettingsService->set($key, $value);
		} catch (\ValueError) {
			return new JSONResponse(['status' => 'error'], Http::STATUS_BAD_REQUEST);
		} catch (\UnexpectedValueException) {
			return new JSONResponse(['status' => 'error'], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(['status' => 'success'], Http::STATUS_OK);
	}

}
