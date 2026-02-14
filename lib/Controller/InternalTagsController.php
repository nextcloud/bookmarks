<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Controller;

use OCA\Bookmarks\Service\Authorizer;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class InternalTagsController extends ApiController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ?string $userId,
		private TagsController $publicController,
		private Authorizer $authorizer,
	) {
		parent::__construct($appName, $request);
		$this->authorizer->setCORS(false);
		if ($this->userId !== null) {
			$this->authorizer->setUserId($this->userId);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/tag/{old_name}')]
	public function deleteTag(string $old_name = ''): JSONResponse {
		return $this->publicController->deleteTag($old_name);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/tag/{old_name}')]
	#[FrontpageRoute(verb: 'POST', url: '/tag/{old_name}')]
	public function renameTag(string $old_name = '', string $new_name = '', string $name = ''): JSONResponse {
		return $this->publicController->renameTag($old_name, $new_name, $name);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/tag')]
	public function fullTags(bool $count = false): JSONResponse {
		return $this->publicController->fullTags($count);
	}
}
