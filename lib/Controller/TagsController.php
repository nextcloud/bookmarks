<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Controller;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Service\Authorizer;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\DB\Exception;
use OCP\IRequest;

class TagsController extends ApiController {

	public function __construct(
		string $appName,
		IRequest $request,
		private string $userId,
		private DB\TagMapper $tagMapper,
		private Authorizer $authorizer,
	) {
		parent::__construct($appName, $request);
		$this->authorizer->setCORS(true);
	}

	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\BruteForceProtection(action: 'deleteTag')]
	#[Http\Attribute\FrontpageRoute(verb: 'DELETE', url: '/public/rest/v2/tag')]
	#[Http\Attribute\FrontpageRoute(verb: 'DELETE', url: '/public/rest/v2/tag/{old_name}')]
	public function deleteTag(string $old_name = ''): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_WRITE, $this->authorizer->getPermissionsForFolder(-1, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Could not find tag']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		}
		if ($old_name === '') {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		$this->tagMapper->deleteTag($this->userId, $old_name); // TODO: Catch exceptions
		return new JSONResponse(['status' => 'success']);
	}

	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\BruteForceProtection(action: 'renameTag')]
	#[Http\Attribute\FrontpageRoute(verb: 'POST', url: '/public/rest/v2/tag')]
	#[Http\Attribute\FrontpageRoute(verb: 'POST', url: '/public/rest/v2/tag/{old_name}')]
	#[Http\Attribute\FrontpageRoute(verb: 'PUT', url: '/public/rest/v2/tag/{old_name}')]
	public function renameTag(string $old_name = '', string $new_name = '', string $name = ''): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_WRITE, $this->authorizer->getPermissionsForFolder(-1, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Could not find tag']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		}
		if ($new_name === '') {
			$new_name = $name;
		}

		if ($old_name === '' || $new_name === '') {
			return new JSONResponse(['status' => 'error', 'data' => ['Must provide old_name and a new name']], Http::STATUS_BAD_REQUEST);
		}

		$this->tagMapper->renameTag($this->userId, $old_name, $new_name);
		return new JSONResponse(['status' => 'success']);
	}

	#[Http\Attribute\NoAdminRequired]
	#[Http\Attribute\NoCSRFRequired]
	#[Http\Attribute\PublicPage]
	#[Http\Attribute\BruteForceProtection(action: 'fullTags')]
	#[Http\Attribute\FrontpageRoute(verb: 'GET', url: '/public/rest/v2/tag')]
	public function fullTags(bool $count = false): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_WRITE, $this->authorizer->getPermissionsForFolder(-1, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not authorized']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		}

		try {
			if ($count === true) {
				$tags = $this->tagMapper->findAllWithCount($this->userId);
			} else {
				$tags = $this->tagMapper->findAll($this->userId);
			}
		} catch (Exception $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new JSONResponse($tags);
	}
}
