<?php

declare(strict_types=1);

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Controller;

use OCA\Bookmarks\Contract\IBookmarkPreviewer;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Service\Previewers\GenericUrlBookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\PageresBookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\ScreeenlyBookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\ScreenshotMachineBookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\WebshotBookmarkPreviewer;
use OCA\Bookmarks\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class AdminController extends Controller {

	/** @var array<string, IBookmarkPreviewer> */
	private array $previewers;

	public function __construct(
		string $appName,
		IRequest $request,
		private SettingsService $settingsService,
		GenericUrlBookmarkPreviewer $urlPreviewer,
		ScreeenlyBookmarkPreviewer $screeenlyPreviewer,
		ScreenshotMachineBookmarkPreviewer $screenshotMachinePreviewer,
		WebshotBookmarkPreviewer $webshotPreviewer,
		PageresBookmarkPreviewer $pageresPreviewer,
	) {
		parent::__construct($appName, $request);
		$this->previewers = [
			'url' => $urlPreviewer,
			'screeenly' => $screeenlyPreviewer,
			'screenshotmachine' => $screenshotMachinePreviewer,
			'webshot' => $webshotPreviewer,
			'pageres' => $pageresPreviewer,
		];
	}

	/**
	 * @param string $setting
	 * @param scalar $value
	 * @return JSONResponse
	 */
	public function setSetting(string $setting, float|bool|int|string $value): JSONResponse {
		try {
			$this->settingsService->setSetting($setting, (string)$value);
			return new JSONResponse([], Http::STATUS_OK);
		} catch (\Exception $e) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @param string $setting
	 * @return JSONResponse
	 */
	public function getSetting(string $setting): JSONResponse {
		return new JSONResponse(['value' => $this->settingsService->getSetting($setting)]);
	}

	/**
	 * @param string $previewer
	 * @return Http\Response
	 * @NoCSRFRequired
	 */
	public function checkPreviewer(string $previewer): Http\Response {
		if (!isset($this->previewers[$previewer])) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
		$previewer = $this->previewers[$previewer];
		$test = new Bookmark();
		$test->setUrl('https://nextcloud.com/');
		return new Http\DataDisplayResponse($previewer->getImage($test)?->getData() ?? '');
	}
}
