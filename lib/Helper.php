<?php

namespace OCA\Bookmarks;

class Helper {
	public static function getDomainWithoutExt($name) {
		$pos = strripos($name, '.');
		if ($pos === false) {
			return $name;
		} else {
			return substr($name, 0, $pos);
		}
	}
}
