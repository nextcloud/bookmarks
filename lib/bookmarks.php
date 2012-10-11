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
	* @brief Finds all tags for bookmarks
	*/
	public static function findTags($filterTags = array(), $offset = 0, $limit = 10){
		$params = array_merge($filterTags, $filterTags);
		array_unshift($params, OCP\USER::getUser());
		$not_in = '';
		if(!empty($filterTags) ) {
			$exist_clause = " AND	exists (select 1 from  *PREFIX*bookmarks_tags
				t2 where t2.bookmark_id = t.bookmark_id and tag = ?) ";

			$not_in = ' AND tag not in ('. implode(',', array_fill(0, count($filterTags), '?') ) .')'.
			str_repeat($exist_clause, count($filterTags));
		}
		$sql = 'SELECT tag, count(*) as nbr from *PREFIX*bookmarks_tags t '.
			' WHERE EXISTS( SELECT 1 from *PREFIX*bookmarks bm where  t.bookmark_id  = bm.id and user_id = ?) '.
			$not_in.
			' group by tag Order by nbr DESC ';

		$query = OCP\DB::prepare($sql, $limit, $offset);
		$tags = $query->execute($params)->fetchAll();
		return $tags;
	}
	
	/**
	 * @brief Finds all bookmarks, matching the filter
	 * @param offset result offset
	 * @param sqlSortColumn sort result with this column
	 * @param filters can be: empty -> no filter, a string -> filter this, a string array -> filter for all strings
	 * @param filterTagOnly if true, filter affects only tags, else filter affects url, title and tags
	 * @param limit number of item to return (default 10) if -1 or false then all item are returned
	 * @return void
	 */
	public static function findBookmarks($offset, $sqlSortColumn, $filters, $filterTagOnly, $limit = 10) {
		$CONFIG_DBTYPE = OCP\Config::getSystemValue( 'dbtype', 'sqlite' );
		if(is_string($filters)) $filters = array($filters);

		$params=array(OCP\USER::getUser());

		if($CONFIG_DBTYPE == 'pgsql') {
			$group_fct = 'array_agg(tag)';
		}
		else {
			$group_fct = 'GROUP_CONCAT(tag)';
		}
		$sql = "SELECT *, (select $group_fct from *PREFIX*bookmarks_tags where bookmark_id = b.id) as tags
				FROM *PREFIX*bookmarks b
				WHERE user_id = ? ";

		if($filterTagOnly) {
			$exist_clause = " AND	exists (select id from  *PREFIX*bookmarks_tags
				t2 where t2.bookmark_id = b.id and tag = ?) ";
			$sql .= str_repeat($exist_clause, count($filters));
			$params = array_merge($params, $filters);
		} else {
			foreach($filters as $filter) {
				$sql .= ' AND lower(url || title || description || tags ) like ? ';
				$params[] = '%' . strtolower($filter) . '%';
			}
		}
		$sql .= " ORDER BY ".$sqlSortColumn." DESC ";
		if($limit == -1 || $limit === false) {
			$limit = null;
			$offset = null;
		}

		$query = OCP\DB::prepare($sql, $limit, $offset);
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

	public static function renameTag($old, $new)
	{
		$user_id = OCP\USER::getUser();
		$CONFIG_DBTYPE = OCP\Config::getSystemValue( 'dbtype', 'sqlite' );


		if( $CONFIG_DBTYPE == 'sqlite' or $CONFIG_DBTYPE == 'sqlite3' ) {
			// Update tags to the new label unless it already exists a tag like this
			$query = OCP\DB::prepare("
				UPDATE OR REPLACE *PREFIX*bookmarks_tags
				SET tag = ?
				WHERE tag = ?
				AND exists( select b.id from *PREFIX*bookmarks b where b.user_id = ? and bookmark_id = b.id)
			");

			$params=array(
				$new,
				$old,
				$user_id,
			);

			$result = $query->execute($params);
		} else {

			// Remove potentialy duplicated tags
			$query = OCP\DB::prepare("
			DELETE FROM *PREFIX*bookmarks_tags as tgs where tgs.tag = ?
			AND exists( select id from *PREFIX*bookmarks where user_id = ? and tgs.bookmark_id = id)
			AND exists( select t.tag from *PREFIX*bookmarks_tags t where t.tag=? and tgs.bookmark_id = tbookmark_id");

			$params=array(
				$new,
				$user_id,
			);

			$result = $query->execute($params);


			// Update tags to the new label unless it already exists a tag like this
			$query = OCP\DB::prepare("
			UPDATE *PREFIX*bookmarks_tags
			SET tag = ?
			WHERE tag = ?
			AND exists( select b.id from *PREFIX*bookmarks b where b.user_id = ? and bookmark_id = b.id)
			");

			$params=array(
				$new,
				$old,
				$user_id,
				$old,
			);

			$result = $query->execute($params);
		}


		return true;
	}

	public static function deleteTag($old)
	{
		$user_id = OCP\USER::getUser();

		// Update the record
		$query = OCP\DB::prepare("
		DELETE FROM *PREFIX*bookmarks_tags
		WHERE tag = ?
		AND exists( select id from *PREFIX*bookmarks where user_id = ? and bookmark_id = id)
		");

		$params=array(
			$old,
			$user_id,
		);

		$result = $query->execute($params);
		return $result;
	}

	/**
	* get a string corresponding to the current time depending
	* of the OC database system
	* @return string
	*/
	protected static function getNowValue() {
		$CONFIG_DBTYPE = OCP\Config::getSystemValue( "dbtype", "sqlite" );
		if( $CONFIG_DBTYPE == 'sqlite' or $CONFIG_DBTYPE == 'sqlite3' ) {
			$_ut = "strftime('%s','now')";
		} elseif($CONFIG_DBTYPE == 'pgsql') {
			$_ut = 'date_part(\'epoch\',now())::integer';
		} else {
			$_ut = "UNIX_TIMESTAMP()";
		}
		return $_ut;
	}

	/**
	 * Edit a bookmark
	 * @param int $id The id of the bookmark to edit
	 * @param string $url
	 * @param string $title Name of the bookmark
	 * @param array $tags Simple array of tags to qualify the bookmark (different tags are taken from values)
	 * @param string $description A longer description about the bookmark
	 * @param boolean $is_public True if the bookmark is publishable to not registered users
	 * @return null
	 */
	public static function editBookmark($id, $url, $title, $tags = array(), $description='', $is_public=false) {

		$is_public = $is_public ? 1 : 0;
		$user_id = OCP\USER::getUser();

		// Update the record
		$query = OCP\DB::prepare("
		UPDATE *PREFIX*bookmarks SET
			url = ?, title = ?, public = ?, description = ?,
			lastmodified = ".self::getNowValue() ."
		WHERE id = ?
		AND user_id = ?
		");

		$params=array(
			htmlspecialchars_decode($url),
			htmlspecialchars_decode($title),
			$is_public,
			htmlspecialchars_decode($description),
			$id,
			$user_id,
		);

		$result = $query->execute($params);

		// Abort the operation if bookmark couldn't be set
		// (probably because the user is not allowed to edit this bookmark)
		if ($result->numRows() == 0) exit();


		// Remove old tags
		$sql = "DELETE from *PREFIX*bookmarks_tags  WHERE bookmark_id = ?";
		$query = OCP\DB::prepare($sql);
		$query->execute(array($id));

		// Add New Tags
		self::addTags($id, $tags);
	}

	/**
	* Add a bookmark
	 * @param string $url
	 * @param string $title Name of the bookmark
	 * @param array $tags Simple array of tags to qualify the bookmark (different tags are taken from values)
	 * @param string $description A longer description about the bookmark
	 * @param boolean $is_public True if the bookmark is publishable to not registered users
	 * @return int The id of the bookmark created
	 */
	public static function addBookmark($url, $title, $tags=array(), $description='', $is_public=false) {

		$is_public = $is_public ? 1 : 0;
		//FIXME: Detect and do smth when user adds a known URL
		$_ut = self::getNowValue();

		$query = OCP\DB::prepare("
			INSERT INTO *PREFIX*bookmarks
			(url, title, user_id, public, added, lastmodified, description)
			VALUES (?, ?, ?, ?, $_ut, $_ut, ?)
			");

		$params=array(
			htmlspecialchars_decode($url),
			htmlspecialchars_decode($title),
			OCP\USER::getUser(),
			$is_public,
			$description,
		);
		$query->execute($params);

		$b_id = OCP\DB::insertid('*PREFIX*bookmarks');

		if($b_id !== false) {
			self::addTags($b_id, $tags);
			return $b_id;
		}
	}

	/**
	 * Add a set of tags for a bookmark
	 * @param int $bookmark_id The bookmark reference
	 * @param array $tags Set of tags to add to the bookmark
	 * @return null
	 **/
	private static function addTags($bookmark_id, $tags) {
		$query = OCP\DB::prepare("
			INSERT INTO *PREFIX*bookmarks_tags
			(bookmark_id, tag)
			VALUES (?, ?)");

		foreach ($tags as $tag) {
			if(empty($tag)) {
				//avoid saving blankspaces
				continue;
			}
			$params = array($bookmark_id, trim($tag));
			$query->execute($params);
		}
	}

	/**
	 * Simple function to search for bookmark. call findBookmarks
	 * @param array $search_words Set of words to look for in bookmars fields
	 * @return array An Array of bookmarks
	 **/
	public static function searchBookmarks($search_words) {
		return self::findBookmarks(0, 'id', $search_words, false);
	}

	public static function importFile($file){
		libxml_use_internal_errors(true);
		$dom = new domDocument();

		$dom->loadHTMLFile($file);
		$links = $dom->getElementsByTagName('a');

		OCP\DB::beginTransaction();
		foreach($links as $link) {
			$title = $link->nodeValue;
			$ref = $link->getAttribute("href");
			$tag_str = '';
			if($link->hasAttribute("tags"))
				$tag_str = $link->getAttribute("tags");
			$tags = explode(',', $tag_str);

			self::addBookmark($ref, $title, $tags);
		}
		OCP\DB::commit();
		return array();
	}
}
