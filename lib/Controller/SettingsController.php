<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Controller;

use OCA\Bookmarks\Service\UserSettingsService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class SettingsController extends ApiController {
	public function __construct(
		string $appName,
		IRequest $request,
		private UserSettingsService $userSettingsService,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/settings/{key}')]
	public function getSetting(string $key): JSONResponse {
		try {
			$value = $this->userSettingsService->get($key);
		} catch (\UnexpectedValueException) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse([$key => $value], Http::STATUS_OK);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/settings/{key}')]
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
