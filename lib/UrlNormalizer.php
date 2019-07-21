<?php
namespace OCA\Bookmarks;

use Rowbot\URL\URL;
use Rowbot\URL\Exception\TypeError;

class UrlNormalizer {
	public function __construct() {
	}

	public function normalize($urlString) {
		$url = new URL($urlString);
		return $url->href;
	}
}
