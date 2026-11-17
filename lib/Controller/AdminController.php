<?php

declare(strict_types=1);

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Controller;

use OCA\Bookmarks\Contract\IBookmarkPreviewer;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Exception\PreviewTemporarilyUnavailableError;
use OCA\Bookmarks\Service\Previewers\GenericUrlBookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\PageresBookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\Screeenly3BookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\ScreeenlyBookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\ScreenshotMachineBookmarkPreviewer;
use OCA\Bookmarks\Service\Previewers\WebshotBookmarkPreviewer;
use OCA\Bookmarks\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
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
		Screeenly3BookmarkPreviewer $screeenly3Previewer,
		ScreeenlyBookmarkPreviewer $screeenlyPreviewer,
		ScreenshotMachineBookmarkPreviewer $screenshotMachinePreviewer,
		WebshotBookmarkPreviewer $webshotPreviewer,
		PageresBookmarkPreviewer $pageresPreviewer,
	) {
		parent::__construct($appName, $request);
		$this->previewers = [
			'url' => $urlPreviewer,
			'screeenly3' => $screeenly3Previewer,
			'screeenly' => $screeenlyPreviewer,
			'screenshotmachine' => $screenshotMachinePreviewer,
			'webshot' => $webshotPreviewer,
			'pageres' => $pageresPreviewer,
		];
	}

	#[FrontpageRoute(verb: 'PUT', url: '/admin/settings/{setting}')]
	public function setSetting(string $setting, float|bool|int|string $value): JSONResponse {
		try {
			$this->settingsService->setSetting($setting, (string)$value);
			return new JSONResponse([], Http::STATUS_OK);
		} catch (\Exception $e) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
	}

	#[FrontpageRoute(verb: 'GET', url: '/admin/settings/{setting}')]
	public function getSetting(string $setting): JSONResponse {
		try {
			return new JSONResponse(['value' => $this->settingsService->getSetting($setting)]);
		} catch (\Exception $e) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
	}

	#[Http\Attribute\NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/admin/previewers/{previewer}')]
	public function checkPreviewer(string $previewer): Http\Response {
		if (!isset($this->previewers[$previewer])) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
		$previewerImp = $this->previewers[$previewer];
		$test = new Bookmark();
		$test->setUrl('https://nextcloud.com/');
		try {
			$image = $previewerImp->getImage($test);
		} catch (PreviewTemporarilyUnavailableError $e) {
			$image = null;
		}
		return new Http\DataDisplayResponse($image?->getData() ?? '');
	}
}
