<?php

namespace OCA\Bookmarks\Controller\Lib;

class Helper {

	static function getDomainWithoutExt($name) {
		$pos = strripos($name, '.');
		if ($pos === false) {
			return $name;
		} else {
			return substr($name, 0, $pos);
		}
	}

	/**
	 * fake-encodes a URL so filter_var($url, FILTER_VALIDATE_URL) would not
	 * stumble over non-ascii characters
	 *
	 * @param array $urlData as received from parse_url
	 * @return string
	 * @link https://php.net/manual/en/function.parse-url.php#106731
	 */
	static function fakeEncodeUrl(array $urlData) {
		$urlData['path'] = implode('/', array_map(function($part) {
			return rawurlencode($part);
		}, explode('/', $urlData['path'])));

		$scheme   = isset($urlData['scheme'])   ? rawurlencode($urlData['scheme']) . '://' : '';
		$host     = isset($urlData['host'])     ? rawurlencode($urlData['host']) : '';
		$port     = isset($urlData['port'])     ? ':' . $urlData['port'] : '';
		$user     = isset($urlData['user'])     ? rawurlencode($urlData['user']) : '';
		$pass     = isset($urlData['pass'])     ? ':' . rawurlencode($urlData['pass'])  : '';
		$pass     = ($user || $pass)            ? "$pass@" : '';
		$path     = isset($urlData['path'])     ? $urlData['path'] : '';
		$query    = isset($urlData['query'])    ? '?' . rawurlencode($urlData['query']) : '';
		$fragment = isset($urlData['fragment']) ? '#' . rawurlencode($urlData['fragment']) : '';

		return "$scheme$user$pass$host$port$path$query$fragment";
	}

}
