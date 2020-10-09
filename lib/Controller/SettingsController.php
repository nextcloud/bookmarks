<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Controller;

use Exception;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\Util;

class SettingsController extends ApiController {

	/** @var IConfig */
	private $config;

	/** @var string */
	private $userId;
	/**
	 * @var IL10N
	 */
	private $l;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param string $userId
	 * @param IConfig $config
	 * @param IL10N $l
	 */
	public function __construct(
		$appName, $request, $userId, IConfig $config, IL10N $l
	) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->userId = $userId;
		$this->l = $l;
	}

	private function getSetting(string $key, string $name, $default): JSONResponse {
		try {
			$userValue = $this->config->getUserValue(
				$this->userId,
				$this->appName,
				$key,
				$default
			);
		} catch (Exception $e) {
			Util::writeLog('bookmarks', $e->getMessage(), Util::ERROR);
			return new JSONResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new JSONResponse([$name => $userValue], Http::STATUS_OK);
	}

	private function setSetting($key, $value): JSONResponse {
		try {
			$this->config->setUserValue(
				$this->userId,
				$this->appName,
				$key,
				$value
			);
		} catch (Exception $e) {
			return new JSONResponse(['status' => 'error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new JSONResponse(['status' => 'success'], Http::STATUS_OK);
	}

	/**
	 * get sorting option config value
	 *
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function getSorting(): JSONResponse {
		return $this->getSetting('sorting', 'sorting', 'lastmodified');
	}

	/**
	 * set sorting option config value
	 *
	 * @param string $sorting
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function setSorting($sorting = ""): JSONResponse {
		$legalArguments = ['title', 'added', 'clickcount', 'lastmodified', 'index'];
		if (!in_array($sorting, $legalArguments)) {
			return new JSONResponse(['status' => 'error'], Http::STATUS_BAD_REQUEST);
		}
		return $this->setSetting(
				'sorting',
				$sorting
			);
	}

	/**
	 * get view mode option config value
	 *
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function getViewMode(): JSONResponse {
		return $this->getSetting('viewMode', 'viewMode', 'grid');
	}

	/**
	 * set sorting option config value
	 *
	 * @param string $viewMode
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function setViewMode($viewMode = ""): JSONResponse {
		$legalArguments = ['grid', 'list'];
		if (!in_array($viewMode, $legalArguments)) {
			return new JSONResponse(['status' => 'error'], Http::STATUS_BAD_REQUEST);
		}
		return $this->setSetting(
				'viewMode',
				$viewMode
			);
	}

	/**
	 * get per-user bookmarks limit
	 *
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function getLimit(): JSONResponse {
		$limit = (int)$this->config->getAppValue('bookmarks', 'performance.maxBookmarksperAccount', 0);
		return new JSONResponse(['limit' => $limit], Http::STATUS_OK);
	}

	/**
	 * get user-defined archive path
	 *
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function getArchivePath(): JSONResponse {
		return $this->getSetting(
			'archive.filePath',
			'archivePath',
			$this->l->t('Bookmarks')
		);
	}

	/**
	 * set user-defined archive path
	 *
	 * @param string $archivePath
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function setArchivePath(string $archivePath): JSONResponse {
		return $this->setSetting('archive.filePath', $archivePath);
	}
}
