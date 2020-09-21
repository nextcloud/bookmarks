<?php

namespace OCA\Bookmarks;

class Helper {
	public static function getDomainWithoutExt($name) {
		$pos = strrpos($name, '.');
		if ($pos === false) {
			return $name;
		}

		return substr($name, 0, $pos);
	}
}
