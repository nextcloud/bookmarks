<?php

/**
 * ownCloud - bookmarks plugin
 *
 * @author David Iwanowitsch
 * @copyright 2012 David Iwanowitsch <david at unclouded dot de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Bookmarks\Controller\Lib;

use \OCA\Bookmarks\Controller\Lib\Bookmarks;

class Search extends \OCP\Search\Provider{

	function search($query) {
		$results = array();

		if (substr_count($query, ' ') > 0) {
			$search_words = explode(' ', $query);
		} else {
			$search_words = $query;
		}

		$db = \OC::$server->getDb();
		$user = \OCP\User::getUser();

		$bookmarks = Bookmarks::findBookmarks($user, $db, 0, 'id', $search_words, false);
		$l = new \OC_l10n('bookmarks'); //resulttype can't be localized, javascript relies on that type
		foreach ($bookmarks as $bookmark) {
			$results[] = new \OC_Search_Result($bookmark['title'], $bookmark['title'], $bookmark['url'], (string) $l->t('Bookm.'));
		}

		return $results;
	}

}
