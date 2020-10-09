<?php

/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks;

use OC;
use OCP\AppFramework\Http\TemplateResponse;

class AugmentedTemplateResponse extends TemplateResponse {
	public function render() {
		$return = parent::render();
		$return = preg_replace('/<link rel="manifest" href="(.*?)">/i', '<link rel="manifest" href="'. OC::$server->getURLGenerator()->linkToRouteAbsolute('bookmarks.web_view.manifest').'">', $return);
		return $return;
	}
}
