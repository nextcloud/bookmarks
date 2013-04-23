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
	* @param filterTags array of tag to look for if empty then every tag
	* @param offset result offset
	* @param limit number of item to return
	*/
	public static function findTags($filterTags = array(), $offset = 0, $limit = 10){
		$params = array_merge($filterTags, $filterTags);
		array_unshift($params, OCP\USER::getUser());
		$not_in = '';
		if(!empty($filterTags) ) {
			$exist_clause = " AND	exists (select 1 from `*PREFIX*bookmarks_tags`
				`t2` where `t2`.`bookmark_id` = `t`.`bookmark_id` and `tag` = ?) ";

			$not_in = ' AND `tag` not in ('. implode(',', array_fill(0, count($filterTags), '?') ) .')'.
			str_repeat($exist_clause, count($filterTags));
		}
		$sql = 'SELECT tag, count(*) as nbr from *PREFIX*bookmarks_tags t '.
			' WHERE EXISTS( SELECT 1 from *PREFIX*bookmarks bm where  t.bookmark_id  = bm.id and user_id = ?) '.
			$not_in.
			' GROUP BY `tag` ORDER BY `nbr` DESC ';

		$query = OCP\DB::prepare($sql, $limit, $offset);
		$tags = $query->execute($params)->fetchAll();
		return $tags;
	}

	public static function findOneBookmark($id) {
		$CONFIG_DBTYPE = OCP\Config::getSystemValue( 'dbtype', 'sqlite' );
		if($CONFIG_DBTYPE == 'pgsql') {
			$group_fct = 'array_agg(`tag`)';
		}
		else {
			$group_fct = 'GROUP_CONCAT(`tag`)';
		}
		$sql = "SELECT *, (select $group_fct from `*PREFIX*bookmarks_tags` where `bookmark_id` = `b`.`id`) as `tags`
				FROM `*PREFIX*bookmarks` `b`
				WHERE `user_id` = ? AND `id` = ?";
		$query = OCP\DB::prepare($sql);
		$result = $query->execute(array(OCP\USER::getUser(), $id))->fetchRow();
		$result['tags'] = explode(',', $result['tags']);
		return $result;
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
		if(! in_array($sqlSortColumn, array('id', 'url', 'title', 'user_id',
			'description', 'public', 'added', 'lastmodified','clickcount',))) {
			$sqlSortColumn = 'bookmarks_sorting_recent';
		}
		$params=array(OCP\USER::getUser());

		if($CONFIG_DBTYPE == 'pgsql') {
			$sql = "SELECT * FROM (SELECT *, (select array_to_string(array_agg(`tag`),'') from `*PREFIX*bookmarks_tags` where `bookmark_id` = `b`.`id`) as `tags`
				FROM `*PREFIX*bookmarks` `b`
				WHERE `user_id` = ? ) as `x` WHERE true ";
		}
		else {
			$sql = "SELECT *, (SELECT GROUP_CONCAT(`tag`) from `*PREFIX*bookmarks_tags` WHERE `bookmark_id` = `b`.`id`) as `tags`
				FROM `*PREFIX*bookmarks` `b`
				WHERE `user_id` = ? ";
		}

		if($filterTagOnly) {
			$exist_clause = " AND	exists (SELECT `id` FROM  `*PREFIX*bookmarks_tags`
				`t2` WHERE `t2`.`bookmark_id` = `b`.`id` AND `tag` = ?) ";
			$sql .= str_repeat($exist_clause, count($filters));
			$params = array_merge($params, $filters);
		} else {
			if($CONFIG_DBTYPE == 'mysql') { //Dirty hack to allow usage of alias in where
				$sql .= ' HAVING true ';
			}
			foreach($filters as $filter) {
				$sql .= ' AND lower(`url` || `title` || `description` || `tags` ) LIKE ? ';
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
			WHERE `id` = ?
			");

		$result = $query->execute(array($id));

		$query = OCP\DB::prepare("
			DELETE FROM `*PREFIX*bookmarks_tags`
			WHERE `bookmark_id` = ?
			");

		$result = $query->execute(array($id));
		return true;
	}

	public static function renameTag($old, $new)
	{
		$user_id = OCP\USER::getUser();
		$CONFIG_DBTYPE = OCP\Config::getSystemValue( 'dbtype', 'sqlite' );


		if( $CONFIG_DBTYPE == 'sqlite' or $CONFIG_DBTYPE == 'sqlite3' ) {
			// Update tags to the new label unless it already exists a tag like this
			$query = OCP\DB::prepare("
				UPDATE OR REPLACE `*PREFIX*bookmarks_tags`
				SET `tag` = ?
				WHERE `tag` = ?
				AND exists( select `b`.`id` from `*PREFIX*bookmarks` `b` WHERE `b`.`user_id` = ? AND `bookmark_id` = `b`.`id`)
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
			DELETE FROM `*PREFIX*bookmarks_tags` as `tgs` WHERE `tgs`.`tag` = ?
			AND exists( SELECT `id` FROM `*PREFIX*bookmarks` WHERE `user_id` = ? AND `tgs`.`bookmark_id` = `id`)
			AND exists( SELECT `t`.`tag` FROM `*PREFIX*bookmarks_tags` `t` where `t`.`tag` = ? AND `tgs`.`bookmark_id` = `t`.`bookmark_id`");

			$params=array(
				$new,
				$user_id,
			);

			$result = $query->execute($params);


			// Update tags to the new label unless it already exists a tag like this
			$query = OCP\DB::prepare("
			UPDATE `*PREFIX*bookmarks_tags`
			SET `tag` = ?
			WHERE `tag` = ?
			AND exists( SELECT `b`.`id` FROM `*PREFIX*bookmarks` `b` WHERE `b`.`user_id` = ? AND `bookmark_id` = `b`.`id`)
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
		DELETE FROM `*PREFIX*bookmarks_tags`
		WHERE `tag` = ?
		AND exists( SELECT `id` FROM `*PREFIX*bookmarks` WHERE `user_id` = ? AND `bookmark_id` = `id`)
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
		UPDATE `*PREFIX*bookmarks` SET
			`url` = ?, `title` = ?, `public` = ?, `description` = ?,
			`lastmodified` = ".self::getNowValue() ."
		WHERE `id` = ?
		AND `user_id` = ?
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
		$sql = "DELETE FROM `*PREFIX*bookmarks_tags`  WHERE `bookmark_id` = ?";
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
		$enc_url = htmlspecialchars_decode($url);
		$_ut = self::getNowValue();
		// Change lastmodified date if the record if already exists
		$sql = "SELECT * from  `*PREFIX*bookmarks` WHERE `url` = ? AND `user_id` = ?";
		$query = OCP\DB::prepare($sql, 1);
		$result = $query->execute(array($enc_url, OCP\USER::getUser()));
		if ($row = $result->fetchRow()){
			$params = array();
			$title_str = '';
			if(trim($title) != '') { // Do we replace the old title
				$title_str = ' , title = ?';
				$params[] = $title;
			}
			$desc_str = '';
			if(trim($title) != '') { // Do we replace the old description
				$desc_str = ' , description = ?';
				$params[] = $description;
			}
			$sql = "UPDATE `*PREFIX*bookmarks` SET `lastmodified` = $_ut $title_str $desc_str WHERE `url` = ? and `user_id` = ?";
			$params[] = $enc_url;
			$params[] = OCP\USER::getUser();
			$query = OCP\DB::prepare($sql);
			$query->execute($params);
			return $row['id'];
		}
		$query = OCP\DB::prepare("
			INSERT INTO `*PREFIX*bookmarks`
			(`url`, `title`, `user_id`, `public`, `added`, `lastmodified`, `description`)
			VALUES (?, ?, ?, ?, $_ut, $_ut, ?)
			");

		$params=array(
			$enc_url,
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
			INSERT INTO `*PREFIX*bookmarks_tags`
			(`bookmark_id`, `tag`)
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

			$desc_str = '';
			if($link->hasAttribute("description"))
				$desc_str = $link->getAttribute("description");

			self::addBookmark($ref, $title, $tags,$desc_str );
		}
		OCP\DB::commit();
		return array();
	}

  public static function getURLMetadata($url) {
		//allow only http(s) and (s)ftp
		$protocols = '/^[hs]{0,1}[tf]{0,1}tp[s]{0,1}\:\/\//i';
		//if not (allowed) protocol is given, assume http
		if(preg_match($protocols, $url) == 0) {
			$url = 'http://' . $url;
		}
		$metadata['url'] = $url;
		$page  = OC_Util::getUrlContent($url);
		if($page) {
			if(preg_match( "/<title>(.*)<\/title>/sUi", $page, $match ) !== false)
				if(isset($match[1])) {
					$metadata['title'] =  html_entity_decode($match[1], ENT_NOQUOTES , 'UTF-8');
					//Not the best solution but....
					$metadata['title'] = str_replace('&trade;', chr(153), $metadata['title']);
					$metadata['title'] = str_replace('&dash;', '‐', $metadata['title']);
					$metadata['title'] = str_replace('&ndash;', '–', $metadata['title']);
				}
		}
		return $metadata;
	}

	public static function analyzeTagRequest($line) {
		$tags = explode(',', $line);
		$filterTag = array();
		foreach($tags as $tag){
			if(trim($tag) != '')
				$filterTag[] = trim($tag);
		}
		return $filterTag;
	}
}

