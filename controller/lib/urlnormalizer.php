<?php
namespace OCA\Bookmarks\Controller\Lib;

use \webignition\NormalisedUrl\NormalisedUrl;

class UrlNormalizer {
	private $normalizer;

	public function __construct() {
		$this->normalizer = new NormalisedUrl();
	}

	/**
	 * @brief Normalize Url
	 * @param string $url Url to load and analyze
	 * @return string Normalized url;
	 */
	public function normalize($url) {
		$this->normalizer->init($url);
		return (string) $this->normalizer;
	}
}
