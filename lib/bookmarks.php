<?php
/**
 * ownCloud - bookmarks plugin
 *
 * @author Arthur Schiwon
 * @copyright 2011 Arthur Schiwon blizzz@arthur-schiwon.de
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

/**
 * This class manages bookmarks
 */
class OC_Bookmarks_Bookmarks{


	/**
	* @brief Find People with whome we shared bookmarks and how much
	*/
	public static function findSharing($offset = 0, $limit = 10){
		$query = OCP\DB::prepare('SELECT \'@public\' as name, count(*) as nbr from  *PREFIX*bookmarks where public=1 group by public LIMIT '.$offset.',  '.$limit);
		$tags = $query->execute()->fetchAll();
		return $tags;
	}
	/**
	* @brief Finds all tags for bookmarks
	*/
	public static function findTags($filterTags = array(), $offset = 0, $limit = 10){
		//$query = OCP\DB::prepare('SELECT tag, count(*) as nbr from  *PREFIX*bookmarks_tags group by tag LIMIT '.$offset.',  '.$limit);

		$params = array_merge($filterTags,$filterTags);
		$not_in = '';

		if(!empty($filterTags) ) {
			$not_in = ' where tag not in ('. implode(',',array_fill(0, count($filterTags) ,'?') ) .')'.
			str_repeat(" AND	exists (select 1 from  *PREFIX*bookmarks_tags t2 where t2.bookmark_id = t.bookmark_id and tag = ?) ", count($filterTags));
		}
		$sql = 'SELECT tag, count(*) as nbr from *PREFIX*bookmarks_tags t '.$not_in.
			'group by tag Order by nbr DESC LIMIT '.$offset.',  '.$limit;
		$query = OCP\DB::prepare($sql);
		$tags = $query->execute($params)->fetchAll();
		return $tags;
	}
	/**
	 * @brief Finds all bookmarks, matching the filter
	 * @param offset result offset
	 * @param sqlSortColumn sort result with this column
	 * @param filter can be: empty -> no filter, a string -> filter this, a string array -> filter for all strings
	 * @param filterTagOnly if true, filter affects only tags, else filter affects url, title and tags
	 * @return void
	 */
	public static function findBookmarks($offset, $sqlSortColumn, $filter, $filterTagOnly){
		$CONFIG_DBTYPE = OCP\Config::getSystemValue( 'dbtype', 'sqlite' );
		$limit = 10;
		$params=array(OCP\USER::getUser());
		//@TODO replace GROUP_CONCAT for postgresql
		$sql = "SELECT *, (select GROUP_CONCAT(tag) from oc_bookmarks_tags where bookmark_id = b.id) as tags
				FROM *PREFIX*bookmarks b
				WHERE user_id = ? ";

		if($filterTagOnly) {
			if(is_string($filter)) $filter = array($filter);

			$sql .= str_repeat(" AND	exists (select id from  *PREFIX*bookmarks_tags t2 where t2.bookmark_id = b.id and tag = ?) ", count($filter));
			$params = array_merge($params, $filter);
		}
		$sql .= " ORDER BY ".$sqlSortColumn." DESC
				LIMIT $limit
				OFFSET  $offset";

		$query = OCP\DB::prepare($sql);
		$results = $query->execute($params)->fetchAll();
		$bookmarks = array();
		foreach($results as $result){
			$result['tags'] = explode(',', $result['tags']);
			$bookmarks[] = $result;
		}
		return $bookmarks;
	}

	public static function deleteUrl($id)
	{
		$user = OCP\USER::getUser();

		$query = OCP\DB::prepare("
				SELECT `id` FROM `*PREFIX*bookmarks`
				WHERE `id` = ?
				AND `user_id` = ?
				");

		$result = $query->execute(array($id, $user));
		$id = $result->fetchOne();
		if ($id === false) {
			return false;
		}

		$query = OCP\DB::prepare("
			DELETE FROM `*PREFIX*bookmarks`
			WHERE `id` = $id
			");

		$result = $query->execute();

		$query = OCP\DB::prepare("
			DELETE FROM `*PREFIX*bookmarks_tags`
			WHERE `bookmark_id` = $id
			");

		$result = $query->execute();
		return true;
	}
}
