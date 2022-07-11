<?php

/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Controller;

use OCA\Bookmarks\AugmentedTemplateResponse;
use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\PublicFolder;
use OCA\Bookmarks\Db\PublicFolderMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\StreamResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;

class WebViewController extends Controller {

	/** @var string */
	private $userId;

	/**
	 * @var IL10N
	 */
	private $l;

	/**
	 * @var PublicFolderMapper
	 */
	private $publicFolderMapper;

	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var FolderMapper
	 */
	private $folderMapper;
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;
	/**
	 * @var \OCP\IInitialStateService
	 */
	private $initialState;
	/**
	 * @var InternalFoldersController
	 */
	private $folderController;
	/**
	 * @var IConfig
	 */
	private $config;


	/**
	 * WebViewController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param $userId
	 * @param IL10N $l
	 * @param PublicFolderMapper $publicFolderMapper
	 * @param IUserManager $userManager
	 * @param FolderMapper $folderMapper
	 * @param IURLGenerator $urlGenerator
	 * @param \OCP\IInitialStateService $initialState
	 * @param InternalFoldersController $folderController
	 * @param IConfig $config
	 */
	public function __construct($appName, $request, $userId, IL10N $l, PublicFolderMapper $publicFolderMapper, IUserManager $userManager, FolderMapper $folderMapper, IURLGenerator $urlGenerator, \OCP\IInitialStateService $initialState, \OCA\Bookmarks\Controller\InternalFoldersController $folderController, IConfig $config) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->l = $l;
		$this->publicFolderMapper = $publicFolderMapper;
		$this->userManager = $userManager;
		$this->folderMapper = $folderMapper;
		$this->urlGenerator = $urlGenerator;
		$this->initialState = $initialState;
		$this->folderController = $folderController;
		$this->config = $config;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @NoCSRFRequired
	 *
	 * @return AugmentedTemplateResponse
	 */
	public function index(): AugmentedTemplateResponse {
		$res = new AugmentedTemplateResponse($this->appName, 'main', ['url' => $this->urlGenerator]);

		$policy = new ContentSecurityPolicy();
		$policy->addAllowedWorkerSrcDomain("'self'");
		$policy->addAllowedScriptDomain("'self'");
		$policy->addAllowedConnectDomain("'self'");
		$policy->addAllowedFrameDomain("'self'");
		$res->setContentSecurityPolicy($policy);

		// Provide complete folder hierarchy
		$this->initialState->provideInitialState($this->appName, 'folders', $this->folderController->getFolders()->getData()['data']);

		$settings = [];
		foreach (['sorting', 'viewMode'] as $setting) {
			$settings[$setting] = $this->config->getUserValue($this->userId, $this->appName, $setting);
		}
		$this->initialState->provideInitialState($this->appName, 'settings', $settings);

		return $res;
	}

	/**
	 * @param string $token
	 *
	 * @return NotFoundResponse|PublicTemplateResponse
	 *
	 * @NoAdminRequired
	 *
	 * @NoCSRFRequired
	 *
	 * @PublicPage
	 */
	public function link(string $token) {
		$title = 'No title found';
		$userName = 'Unknown';
		try {
			/**
			 * @var $publicFolder PublicFolder
			 */
			$publicFolder = $this->publicFolderMapper->find($token);
			/**
			 * @var $folder Folder
			 */
			$folder = $this->folderMapper->find($publicFolder->getFolderId());
			$title = $folder->getTitle();
			$user = $this->userManager->get($folder->getUserId());
			if ($user !== null) {
				$userName = $user->getDisplayName();
			}
		} catch (DoesNotExistException $e) {
			return new NotFoundResponse();
		} catch (MultipleObjectsReturnedException $e) {
			return new NotFoundResponse();
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
		$response = new StreamResponse(__DIR__.'/../../js/bookmarks-service-worker.js');
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
			'icons' =>
				[
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
