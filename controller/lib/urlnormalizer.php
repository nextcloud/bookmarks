<?php
namespace OCA\Bookmarks\Controller\Lib;

use URL\Normalizer;

class UrlNormalizer {

	private $normalizer;

	public function __construct() {
		$this->normalizer = new Normalizer();
	}

    /**
	 * @brief Normalize Url
	 * @param string $url Url to load and analyze
	 * @return string Normalized url;
	 */
	public function normalize($url) {
		$this->normalizer->setUrl($url);
		return $this->normalizer->normalize();
	}

}
