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

}
