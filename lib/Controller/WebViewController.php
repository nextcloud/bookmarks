<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Controller;

use OCA\Bookmarks\AugmentedTemplateResponse;
use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\PublicFolder;
use OCA\Bookmarks\Db\PublicFolderMapper;
use OCA\Bookmarks\Service\SettingsService;
use OCA\Bookmarks\Service\UserSettingsService;
use OCA\Viewer\Event\LoadViewer;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\StreamResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;

class WebViewController extends Controller {
	private ?string $userId;

	/**
	 * WebViewController constructor.
	 */
	public function __construct(
		$appName,
		IRequest $request,
		?string $userId,
		private IL10N $l,
		private PublicFolderMapper $publicFolderMapper,
		private IUserManager $userManager,
		private FolderMapper $folderMapper,
		private IURLGenerator $urlGenerator,
		private \OCP\IInitialStateService $initialState,
		private \OCA\Bookmarks\Controller\InternalFoldersController $folderController,
		private \OCA\Bookmarks\Controller\InternalBookmarkController $bookmarkController,
		private \OCA\Bookmarks\Controller\InternalTagsController $tagsController,
		private UserSettingsService $userSettingsService,
		private SettingsService $settings,
		private IAppManager $appManager,
		private IConfig $config,
		private IEventDispatcher $eventDispatcher,
	) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @NoCSRFRequired
	 */
	public function index(): AugmentedTemplateResponse {
		if (class_exists(LoadViewer::class)) {
			$this->eventDispatcher->dispatchTyped(new LoadViewer());
		}
		$res = new AugmentedTemplateResponse($this->appName, 'main', ['url' => $this->urlGenerator]);

		$policy = new ContentSecurityPolicy();
		$policy->addAllowedWorkerSrcDomain("'self'");
		$policy->addAllowedScriptDomain("'self'");
		$policy->addAllowedConnectDomain("'self'");
		$policy->addAllowedFrameDomain("'self'");
		$res->setContentSecurityPolicy($policy);

		$this->initialState->provideInitialState($this->appName, 'folders', $this->folderController->getFolders()->getData()['data']);
		$this->initialState->provideInitialState($this->appName, 'deletedFolders', $this->folderController->getDeletedFolders()->getData()['data']);
		$this->initialState->provideInitialState($this->appName, 'archivedCount', $this->bookmarkController->countArchived()->getData()['item']);
		$this->initialState->provideInitialState($this->appName, 'deletedCount', $this->bookmarkController->countDeleted()->getData()['item']);
		$this->initialState->provideInitialState($this->appName, 'duplicatedCount', $this->bookmarkController->countDuplicated()->getData()['item']);
		$this->initialState->provideInitialState($this->appName, 'unavailableCount', $this->bookmarkController->countUnavailable()->getData()['item']);
		$this->initialState->provideInitialState($this->appName, 'allCount', $this->bookmarkController->countBookmarks(-1)->getData()['item']);
		$this->initialState->provideInitialState($this->appName, 'allClicksCount', $this->bookmarkController->countAllClicks()->getData()['item']);
		$this->initialState->provideInitialState($this->appName, 'withClicksCount', $this->bookmarkController->countWithClicks()->getData()['item']);
		$this->initialState->provideInitialState($this->appName, 'tags', $this->tagsController->fullTags(true)->getData());
		$this->initialState->provideInitialState($this->appName, 'contextChatInstalled', $this->appManager->isEnabledForUser('context_chat'));
		$this->initialState->provideInitialState($this->appName, 'appStoreEnabled', $this->config->getSystemValueBool('appstoreenabled', true));

		$settings = $this->userSettingsService->toArray();
		$settings['shareapi_allow_links'] = $this->settings->getLinkSharingAllowed();
		$this->initialState->provideInitialState($this->appName, 'settings', $settings);

		return $res;
	}

	/**
	 * @param string $token
	 *
	 * @return NotFoundResponse|PublicTemplateResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection
	 * @PublicPage
	 */
	public function link(string $token) {
		$title = 'No title found';
		$userName = 'Unknown';
		try {
			/**
			 * @var PublicFolder $publicFolder
			 */
			$publicFolder = $this->publicFolderMapper->find($token);
			/**
			 * @var Folder $folder
			 */
			$folder = $this->folderMapper->find($publicFolder->getFolderId());
			$title = $folder->getTitle();
			$user = $this->userManager->get($folder->getUserId());
			if ($user !== null) {
				$userName = $user->getDisplayName();
			}
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			$res = new NotFoundResponse();
			$res->throttle();
			return $res;
		}

		$res = new PublicTemplateResponse($this->appName, 'main', []);
		$res->setHeaderTitle($title);
		$res->setHeaderDetails($this->l->t('Bookmarks shared by %s', [$userName]));
		$res->setFooterVisible(false);
		return $res;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @NoCSRFRequired
	 *
	 * @return StreamResponse
	 */
	public function serviceWorker(): StreamResponse {
		$response = new StreamResponse(__DIR__ . '/../../js/bookmarks-service-worker.js');
		$response->setHeaders(['Content-Type' => 'application/javascript']);
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedWorkerSrcDomain("'self'");
		$policy->addAllowedScriptDomain("'self'");
		$policy->addAllowedConnectDomain("'self'");
		$response->setContentSecurityPolicy($policy);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @NoCSRFRequired
	 *
	 * @PublicPage
	 *
	 * @return JSONResponse
	 */
	public function manifest(): JSONResponse {
		$responseJS = [
			'name' => $this->l->t('Bookmarks'),
			'short_name' => $this->l->t('Bookmarks'),
			'start_url' => $this->urlGenerator->linkToRouteAbsolute('bookmarks.web_view.index'),
			'icons'
				=> [
					[
						'src' => $this->urlGenerator->linkToRoute('theming.Icon.getTouchIcon',
							['app' => 'bookmarks']),
						'type' => 'image/png',
						'sizes' => '512x512'
					],
					[
						'src' => $this->urlGenerator->linkToRoute('theming.Icon.getFavicon',
							['app' => 'bookmark']),
						'type' => 'image/svg+xml',
						'sizes' => '128x128'
					]
				],
			'display' => 'standalone'
		];
		$response = new JSONResponse($responseJS);
		$response->setHeaders(['Content-Type' => 'application/manifest+json']);
		//$response->cacheFor(3600);
		return $response;
	}
}
