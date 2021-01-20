<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use OCA\Bookmarks\Exception\UrlParseError;
use Rowbot\URL\Exception\TypeError;
use Rowbot\URL\URL;

class UrlNormalizer {
	private $cache = [];

	public function __construct() {
	}

	/**
	 * @param $urlString
	 * @return string
	 * @throws UrlParseError
	 */
	public function normalize(string $urlString): string {
		if (isset($this->cache[$urlString])) {
			return $this->cache[$urlString];
		}
		try {
			$url = new URL($urlString);
		} catch (TypeError $e) {
			throw new UrlParseError();
		}
		$this->cache[$urlString] = $url->href;
		return $url->href;
	}
}
