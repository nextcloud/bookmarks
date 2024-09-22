<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks;

use OC;
use OCP\AppFramework\Http\TemplateResponse;

/**
 * @psalm-template S of int
 * @psalm-template H of array<string, mixed>
 * @psalm-implements TemplateResponse<S,H>
 */
class AugmentedTemplateResponse extends TemplateResponse {
	public function render() {
		$return = parent::render();
		preg_replace('/<link rel="manifest" href="(.*?)">/i', '<link rel="manifest" href="' . OC::$server->getURLGenerator()->linkToRouteAbsolute('bookmarks.web_view.manifest') . '">', $return);
		return $return;
	}
}
