<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

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
