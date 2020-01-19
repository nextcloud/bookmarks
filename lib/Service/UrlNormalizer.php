<?php

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
	public function normalize($urlString) {
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
