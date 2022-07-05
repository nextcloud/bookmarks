<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Controller;

use OCA\Bookmarks\Db\TrashMapper;
use OCA\Bookmarks\Service\Authorizer;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

class TrashController extends ApiController {
	/**
	 * @var Authorizer
	 */
	private $authorizer;

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	private $logger;
	private TrashMapper $trash;

	/**
	 * FoldersController constructor.
	 *
	 * @param $appName
	 * @param $request
	 * @param Authorizer $authorizer
	 * @param \Psr\Log\LoggerInterface $logger
	 * @param TrashMapper $trash
	 */
	public function __construct($appName, $request, Authorizer $authorizer, \Psr\Log\LoggerInterface $logger, TrashMapper $trash) {
		parent::__construct($appName, $request);
		$this->authorizer = $authorizer;
		$this->logger = $logger;

		$this->authorizer->setCORS(false);
		$this->trash = $trash;
	}

	/**
	 * @param int $folderId
	 * @param int $layers
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function getChildren(): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder(-1, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}

		$children = $this->trash->getChildren($this->authorizer->getUserId());
		$res = new JSONResponse(['status' => 'success', 'data' => $children]);
		return $res;
	}

	/**
	 * @return JSONResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function count(): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_ALL, $this->authorizer->getPermissionsForFolder(-1, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}

		$count = $this->trash->countTrash($this->authorizer->getUserId());
		return new JSONResponse(['status' => 'success', 'item' => $count]);
	}
}
