<?php
namespace OCA\Bookmarks\Service;

use OCA\Bookmarks\Exception\UrlParseError;
use Rowbot\URL\Exception\TypeError;
use Rowbot\URL\URL;

class UrlNormalizer {
	public function __construct() {
	}

	/**
	 * @param $urlString
	 * @return string
	 * @throws UrlParseError
	 */
	public function normalize($urlString) {
		try {
			$url = new URL($urlString);
		} catch (TypeError $e) {
			throw new UrlParseError();
		}
		return $url->href;
	}
}
