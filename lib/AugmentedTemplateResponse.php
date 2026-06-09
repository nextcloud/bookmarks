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
		$params = $this->getParams();
		if (isset($params['url']) && $params['url'] instanceof IURLGenerator) {
			$manifestUrl = $params['url']->linkToRouteAbsolute('bookmarks.web_view.manifest');
			$return = preg_replace('/<link rel="manifest" href="(.*?)">/i', '<link rel="manifest" href="' . $manifestUrl . '">', $return);
		}
		return $return;
	}
}
