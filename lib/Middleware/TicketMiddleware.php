<?php

/*
 * Copyright (c) 2021. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Middleware;

use OCA\Bookmarks\Controller\BookmarkController;
use OCA\Bookmarks\Controller\FoldersController;
use OCA\Bookmarks\Controller\InternalBookmarkController;
use OCA\Bookmarks\Controller\InternalFoldersController;
use OCA\Bookmarks\Controller\TagsController;
use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Exception\UnauthenticatedError;
use OCA\Bookmarks\Service\Authorizer;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;

class TicketMiddleware extends Middleware {

	public function __construct(private Authorizer $authorizer, private \OCP\IRequest $request) {
	}

	public function afterController(Controller $controller, string $methodName, Response $response): Response {
		if ($controller instanceof FoldersController ||  $controller instanceof BookmarkController) {
			if ($this->authorizer->getUserId() !== null && !str_starts_with($this->request->getHeader('Authorization'), 'Bearer')) {
				if ($response instanceof DataResponse || $response instanceof Http\JSONResponse) {
					$data = $response->getData();
					if (is_array($data)) {
						$data['ticket'] = $this->authorizer->generateTicket($this->authorizer->getUserId());
						$response->setData($data);
					}
				}
			}
		}
		return $response;
	}
}
