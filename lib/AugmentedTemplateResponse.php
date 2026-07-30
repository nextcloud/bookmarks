<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IURLGenerator;

/**
 * @template S of \OCP\AppFramework\Http::STATUS_*
 * @template H of array<string, mixed>
 * @template-extends TemplateResponse<S,H>
 */
class AugmentedTemplateResponse extends TemplateResponse {
	public function render() {
		$return = parent::render();
		$return = preg_replace('/<link rel="manifest" href="(.*?)">/i', '<link rel="manifest" href="' . \OCP\Server::get(IUrlGenerator::class)->linkToRouteAbsolute('bookmarks.webview.manifest') . '">', $return);
		return $return;
	}
}
