<?php

/*
 * Copyright (c) 2026. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\AugmentedTemplateResponse;
use OCP\IURLGenerator;

class AugmentedTemplateResponseTest extends TestCase {
	public function testRouteExists(): void {
		$urlGenerator = \OCP\Server::get(IURLGenerator::class);
		$url = $urlGenerator->linkToRouteAbsolute('bookmarks.webview.manifest');
		$this->assertNotEmpty($url);
		$this->assertStringContainsString('manifest.webmanifest', $url);
	}
}
