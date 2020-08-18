<?php
/**
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Bookmarks\Controller;

use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

class SettingsController extends ApiController {

	/** @var IConfig */
	private $config;

	/** @var string */
	private $userId;
	/**
	 * @var \OCP\IL10N
	 */
	private $l;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param string $userId
	 * @param IConfig $config
	 * @param \OCP\IL10N $l
	 */
	public function __construct(
		$appName, $request, $userId, IConfig $config, \OCP\IL10N $l
	) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->userId = $userId;
		$this->l = $l;
	}

	private function getSetting(string $key, string $name, $default) {
		try {
			$userValue = $this->config->getUserValue(
				$this->userId,
				$this->appName,
				$key,
				$default
			);
		} catch (\Exception $e) {
			\OCP\Util::writeLog('bookmarks', $e->getMessage(), \OCP\Util::ERROR);
			return new JSONResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new JSONResponse([$name => $userValue], Http::STATUS_OK);
	}

	private function setSetting($key, $value) {
		try {
			$this->config->setUserValue(
				$this->userId,
				$this->appName,
				$key,
				$value
			);
		} catch (\Exception $e) {
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
	public function getSorting() {
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
	public function setSorting($sorting = "") {
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
	public function getViewMode() {
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
	public function setViewMode($viewMode = "") {
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
	public function getLimit() {
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
	public function getArchivePath() {
		return $this->getSetting(
			'archive.filePath',
			'archivePath',
			$this->l->t('Bookmarks')
		);
	}

	/**
	 * set user-defined archive path
	 *
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function setArchivePath($archivePath) {
		return $this->setSetting('archive.filePath', $archivePath);
	}
}
