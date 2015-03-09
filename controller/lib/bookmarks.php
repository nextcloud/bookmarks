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

namespace OCA\Bookmarks\Controller\Lib;

use \OCP\IDb;

class Bookmarks {

	/**
	 * @brief Finds all tags for bookmarks
	 * @param $userId UserId
	 * @param IDb $db Database Interface
	 * @param filterTags array of tag to look for if empty then every tag
	 * @param offset integer offset
	 * @param limit integer of item to return
	 * @return Found Tags
	 */
	public static function findTags($userId, IDb $db, $filterTags = array(), $offset = 0, $limit = -1) {
		$params = array_merge($filterTags, $filterTags);
		array_unshift($params, $userId);
		$not_in = '';
		if (!empty($filterTags)) {
			$exist_clause = " AND	exists (select 1 from `*PREFIX*bookmarks_tags`
				`t2` where `t2`.`bookmark_id` = `t`.`bookmark_id` and `tag` = ?) ";

			$not_in = ' AND `tag` not in (' . implode(',', array_fill(0, count($filterTags), '?')) . ')' .
					str_repeat($exist_clause, count($filterTags));
		}
		$sql = 'SELECT tag, count(*) as nbr from *PREFIX*bookmarks_tags t ' .
				' WHERE EXISTS( SELECT 1 from *PREFIX*bookmarks bm where  t.bookmark_id  = bm.id and user_id = ?) ' .
				$not_in .
				' GROUP BY `tag` ORDER BY `nbr` DESC ';

		$query = $db->prepareQuery($sql, $limit, $offset);
		$tags = $query->execute($params)->fetchAll();
		return $tags;
	}

	/**
	 * @brief Finds Bookmark with certain ID
	 * @param $id BookmarkId
	 * @param $userId UserId
	 * @param IDb $db Database Interface
	 * @return Specific Bookmark
	 */
	public static function findUniqueBookmark($id, $userId, IDb $db) {
		$CONFIG_DBTYPE = \OCP\Config::getSystemValue('dbtype', 'sqlite');
		if ($CONFIG_DBTYPE == 'pgsql') {
			$group_fct = 'array_agg(`tag`)';
		} else {
			$group_fct = 'GROUP_CONCAT(`tag`)';
		}
		$sql = "SELECT *, (select $group_fct from `*PREFIX*bookmarks_tags` where `bookmark_id` = `b`.`id`) as `tags`
				FROM `*PREFIX*bookmarks` `b`
				WHERE `user_id` = ? AND `id` = ?";
		$query = $db->prepareQuery($sql);
		$result = $query->execute(array($userId, $id))->fetchRow();
		$result['tags'] = explode(',', $result['tags']);
		return $result;
	}

	/**
	 * @brief Check if an URL is bookmarked
	 * @param string $url Url of a possible bookmark
	 * @param $userId UserId
	 * @param IDb $db Database Interface
	 * @return boolean if the url is already bookmarked
	 */
	public static function bookmarkExists($url, $userId, IDb $db) {
		$enc_url = htmlspecialchars_decode($url);
		$sql = "SELECT id from `*PREFIX*bookmarks` where `url` = ? and `user_id` = ?";
		$query = $db->prepareQuery($sql);
		$result = $query->execute(array($enc_url, $userId))->fetchRow();
		if ($result) {
			return $result['id'];
		} else {
			return false;
		}
	}

	/**
	 * @brief Finds all bookmarks, matching the filter
	 * @param $userid UserId
	 * @param IDb $db Database Interface
	 * @param int $offset offset
	 * @param string $sqlSortColumn result with this column
	 * @param string|array $filters filters can be: empty -> no filter, a string -> filter this, a string array -> filter for all strings
	 * @param bool $filterTagOnly true, filter affects only tags, else filter affects url, title and tags
	 * @param int $limit limit of items to return (default 10) if -1 or false then all items are returned
	 * @param bool $public check if only public bookmarks should be returned
	 * @param array $requestedAttributes select all the attributes that should be returned. default is * + tags
	 * @param string $tagFilterConjunction select wether the filterTagOnly should filter with an AND or an OR  conjunction
	 * @return Collection of specified bookmarks
	 */
	public static function findBookmarks(
	$userid, IDb $db, $offset, $sqlSortColumn, $filters, $filterTagOnly, $limit = 10, $public = false, $requestedAttributes = null, $tagFilterConjunction = "and") {

		$CONFIG_DBTYPE = \OCP\Config::getSystemValue('dbtype', 'sqlite');
		if (is_string($filters)) {
			$filters = array($filters);
		}

		$toSelect = '*';
		$tableAttributes = array('id', 'url', 'title', 'user_id', 'description',
			'public', 'added', 'lastmodified', 'clickcount',);

		$returnTags = true;

		if ($requestedAttributes != null) {

			$key = array_search('tags', $requestedAttributes);
			if ($key == false) {
				$returnTags = false;
			} else {
				unset($requestedAttributes[$key]);
			}

			$toSelect = implode(",", array_intersect($tableAttributes, $requestedAttributes));
		}

		if ($CONFIG_DBTYPE == 'pgsql') {
			$sql = "SELECT " . $toSelect . " FROM (SELECT *, (select array_to_string(array_agg(`tag`),',')
				from `*PREFIX*bookmarks_tags` where `bookmark_id` = `b2`.`id`) as `tags`
				FROM `*PREFIX*bookmarks` `b2`
				WHERE `user_id` = ? ) as `b` WHERE true ";
		} else {
			$sql = "SELECT " . $toSelect . ", (SELECT GROUP_CONCAT(`tag`) from `*PREFIX*bookmarks_tags`
				WHERE `bookmark_id` = `b`.`id`) as `tags`
				FROM `*PREFIX*bookmarks` `b`
				WHERE `user_id` = ? ";
		}

		$params = array($userid);

		if ($public) {
			$sql .= ' AND public = 1 ';
		}

		if (count($filters) > 0) {
			Bookmarks::findBookmarksBuildFilter($sql, $params, $filters, $filterTagOnly, $tagFilterConjunction, $CONFIG_DBTYPE);
		}

		if (!in_array($sqlSortColumn, $tableAttributes)) {
			$sqlSortColumn = 'lastmodified';
		}
		$sql .= " ORDER BY " . $sqlSortColumn . " DESC ";
		if ($limit == -1 || $limit === false) {
			$limit = null;
			$offset = null;
		}

		$query = $db->prepareQuery($sql, $limit, $offset);
		$results = $query->execute($params)->fetchAll();
		$bookmarks = array();
		foreach ($results as $result) {
			if ($returnTags) {
				$result['tags'] = explode(',', $result['tags']);
			} else {
				unset($result['tags']);
			}
			$bookmarks[] = $result;
		}
		return $bookmarks;
	}

	/**
	 * @brief Outlined private code to build filter sql for findBookmarks function
	 * @param string $sql Sql to be built
	 * @param array $params Sql parameters
	 * @param array $filters Values used for filter
	 * @param boolean $filterTagOnly Filter by tags or by all data
	 * @param string $tagFilterConjunction And or Or conjunctjon of filters
	 * @param string $CONFIG_DBTYPE Type of database
	 */
	private static function findBookmarksBuildFilter(&$sql, &$params, $filters, $filterTagOnly, $tagFilterConjunction, $CONFIG_DBTYPE) {
		$tagOrSearch = false;
		$connectWord = 'AND';

		if ($tagFilterConjunction == 'or') {
			$tagOrSearch = true;
			$connectWord = 'OR';
		}

		if ($filterTagOnly) {
			if ($tagOrSearch) {
				$sql .= 'AND (';
			} else {
				$sql .= 'AND';
			}
			$exist_clause = " exists (SELECT `id` FROM  `*PREFIX*bookmarks_tags`
				`t2` WHERE `t2`.`bookmark_id` = `b`.`id` AND `tag` = ?) ";
			$sql .= str_repeat($exist_clause . $connectWord, count($filters));
			if ($tagOrSearch) {
				$sql = rtrim($sql, 'OR');
				$sql .= ')';
			} else {
				$sql = rtrim($sql, 'AND');
			}
			$params = array_merge($params, $filters);
		} else {
			if ($CONFIG_DBTYPE == 'mysql') { //Dirty hack to allow usage of alias in where
				$sql .= ' HAVING true ';
			}
			foreach ($filters as $filter) {
				if ($CONFIG_DBTYPE == 'mysql') {
					$sql .= ' AND lower( concat(url,title,description,IFNULL(tags,\'\') )) like ? ';
				} else {
					$sql .= ' AND lower(url || title || description || ifnull(tags,\'\') ) like ? ';
				}
				$params[] = '%' . strtolower($filter) . '%';
			}
		}
	}

	/**
	 * @brief Delete bookmark with specific id
	 * @param $userId UserId
	 * @param IDb $db Database Interface
	 * @param integer $id Bookmark ID to delete
	 * @return boolean Success of operation
	 */
	public static function deleteUrl($userId, IDb $db, $id) {
		$user = $userId;

		$query = $db->prepareQuery("
				SELECT `id` FROM `*PREFIX*bookmarks`
				WHERE `id` = ?
				AND `user_id` = ?
				");

		$result = $query->execute(array($id, $user));
		$id = $result->fetchOne();
		if ($id === false) {
			return false;
		}

		$query = $db->prepareQuery("
			DELETE FROM `*PREFIX*bookmarks`
			WHERE `id` = ?
			");

		$query->execute(array($id));

		$query = $db->prepareQuery("
			DELETE FROM `*PREFIX*bookmarks_tags`
			WHERE `bookmark_id` = ?
			");

		$query->execute(array($id));
		return true;
	}

	/**
	 * @brief Rename a tag
	 * @param $userId UserId
	 * @param IDb $db Database Interface
	 * @param string $old Old Tag Name
	 * @param string $new New Tag Name
	 * @return boolean Success of operation
	 */
	public static function renameTag($userId, IDb $db, $old, $new) {
		$user_id = $userId;
		$CONFIG_DBTYPE = \OCP\Config::getSystemValue('dbtype', 'sqlite');


		if ($CONFIG_DBTYPE == 'sqlite' or $CONFIG_DBTYPE == 'sqlite3') {
			// Update tags to the new label unless it already exists a tag like this
			$query = $db->prepareQuery("
				UPDATE OR REPLACE `*PREFIX*bookmarks_tags`
				SET `tag` = ?
				WHERE `tag` = ?
				AND exists( select `b`.`id` from `*PREFIX*bookmarks` `b`
				WHERE `b`.`user_id` = ? AND `bookmark_id` = `b`.`id`)
			");

			$params = array(
				$new,
				$old,
				$user_id,
			);

			$query->execute($params);
		} else {

			// Remove potentialy duplicated tags
			$query = $db->prepareQuery("
			DELETE FROM `*PREFIX*bookmarks_tags` as `tgs` WHERE `tgs`.`tag` = ?
			AND exists( SELECT `id` FROM `*PREFIX*bookmarks` WHERE `user_id` = ?
			AND `tgs`.`bookmark_id` = `id`)
			AND exists( SELECT `t`.`tag` FROM `*PREFIX*bookmarks_tags` `t` where `t`.`tag` = ?
			AND `tgs`.`bookmark_id` = `t`.`bookmark_id`");

			$params = array(
				$new,
				$user_id,
			);

			$query->execute($params);


			// Update tags to the new label unless it already exists a tag like this
			$query = $db->prepareQuery("
			UPDATE `*PREFIX*bookmarks_tags`
			SET `tag` = ?
			WHERE `tag` = ?
			AND exists( SELECT `b`.`id` FROM `*PREFIX*bookmarks` `b`
			WHERE `b`.`user_id` = ? AND `bookmark_id` = `b`.`id`)
			");

			$params = array(
				$new,
				$old,
				$user_id,
				$old,
			);

			$query->execute($params);
		}


		return true;
	}

	/**
	 * @brief Delete a tag
	 * @param $userid UserId
	 * @param IDb $db Database Interface
	 * @param string $old Tag Name to delete
	 * @return boolean Success of operation
	 */
	public static function deleteTag($userid, IDb $db, $old) {

		// Update the record
		$query = $db->prepareQuery("
		DELETE FROM `*PREFIX*bookmarks_tags`
		WHERE `tag` = ?
		AND exists( SELECT `id` FROM `*PREFIX*bookmarks` WHERE `user_id` = ? AND `bookmark_id` = `id`)
		");

		$params = array(
			$old,
			$userid,
		);

		$result = $query->execute($params);
		return $result;
	}

	/**
	 * Edit a bookmark
	 * @param $userid UserId
	 * @param IDb $db Database Interface
	 * @param int $id The id of the bookmark to edit
	 * @param string $url The url to set
	 * @param string $title Name of the bookmark
	 * @param array $tags Simple array of tags to qualify the bookmark (different tags are taken from values)
	 * @param string $description A longer description about the bookmark
	 * @param boolean $is_public True if the bookmark is publishable to not registered users
	 * @return null|integer
	 */
	public static function editBookmark($userid, IDb $db, $id, $url, $title, $tags = array(), $description = '', $is_public = false) {

		$is_public = $is_public ? 1 : 0;
		$user_id = $userid;

		// Update the record
		$query = $db->prepareQuery("
		UPDATE `*PREFIX*bookmarks` SET
			`url` = ?, `title` = ?, `public` = ?, `description` = ?,
			`lastmodified` = UNIX_TIMESTAMP()
		WHERE `id` = ?
		AND `user_id` = ?
		");

		$params = array(
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
		if ($result == 0)
			exit();


		// Remove old tags
		$sql = "DELETE FROM `*PREFIX*bookmarks_tags`  WHERE `bookmark_id` = ?";
		$query = $db->prepareQuery($sql);
		$query->execute(array($id));

		// Add New Tags
		self::addTags($db, $id, $tags);

		return $id;
	}

	/**
	 * Add a bookmark
	 * @param $userid UserId
	 * @param IDb $db Database Interface
	 * @param string $url
	 * @param string $title Name of the bookmark
	 * @param array $tags Simple array of tags to qualify the bookmark (different tags are taken from values)
	 * @param string $description A longer description about the bookmark
	 * @param boolean $is_public True if the bookmark is publishable to not registered users
	 * @return int The id of the bookmark created
	 */
	public static function addBookmark($userid, IDb $db, $url, $title, $tags = array(), $description = '', $is_public = false) {
		$public = $is_public ? 1 : 0;
		$enc_url = htmlspecialchars_decode($url);
		// Change lastmodified date if the record if already exists
		$sql = "SELECT * from  `*PREFIX*bookmarks` WHERE `url` = ? AND `user_id` = ?";
		$query = $db->prepareQuery($sql, 1);
		$result = $query->execute(array($enc_url, $userid));
		if ($row = $result->fetchRow()) {
			$params = array();
			$title_str = '';
			if (trim($title) != '') { // Do we replace the old title
				$title_str = ' , title = ?';
				$params[] = $title;
			}
			$desc_str = '';
			if (trim($description) != '') { // Do we replace the old description
				$desc_str = ' , description = ?';
				$params[] = $description;
			}
			$sql = "UPDATE `*PREFIX*bookmarks` SET `lastmodified` = "
					. "UNIX_TIMESTAMP() $title_str $desc_str WHERE `url` = ? and `user_id` = ?";
			$params[] = $enc_url;
			$params[] = $userid;
			$query = $db->prepareQuery($sql);
			$query->execute($params);
			return $row['id'];
		} else {
			$query = $db->prepareQuery("
			INSERT INTO `*PREFIX*bookmarks`
			(`url`, `title`, `user_id`, `public`, `added`, `lastmodified`, `description`)
			VALUES (?, ?, ?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), ?)
			");

			$params = array(
				$enc_url,
				htmlspecialchars_decode($title),
				$userid,
				$public,
				$description,
			);
			$query->execute($params);

			$b_id = $db->getInsertId('*PREFIX*bookmarks');

			if ($b_id !== false) {
				self::addTags($db, $b_id, $tags);
				return $b_id;
			}
		}
	}

	/**
	 * @brief Add a set of tags for a bookmark
	 * @param IDb $db Database Interface
	 * @param int $bookmarkID The bookmark reference
	 * @param array $tags Set of tags to add to the bookmark
	 * @return null
	 * */
	private static function addTags(IDb $db, $bookmarkID, $tags) {
		$sql = 'INSERT INTO `*PREFIX*bookmarks_tags` (`bookmark_id`, `tag`) select ?, ? ';
		$dbtype = \OCP\Config::getSystemValue('dbtype', 'sqlite');

		if ($dbtype === 'mysql') {
			$sql .= 'from dual ';
		}
		$sql .= 'where not exists(select * from oc_bookmarks_tags where bookmark_id = ? and tag = ?)';

		$query = $db->prepareQuery($sql);
		foreach ($tags as $tag) {
			$tag = trim($tag);
			if (empty($tag)) {
				//avoid saving white spaces
				continue;
			}
			$params = array($bookmarkID, $tag, $bookmarkID, $tag);
			$query->execute($params);
		}
	}

	/**
	 * @brief Import Bookmarks from html formatted file
	 * @param $user User imported Bookmarks should belong to
	 * @param IDb $db Database Interface
	 * @param $file Content to import
	 * @return null
	 * */
	public static function importFile($user, IDb $db, $file) {
		libxml_use_internal_errors(true);
		$dom = new \domDocument();

		$dom->loadHTMLFile($file);
		$links = $dom->getElementsByTagName('a');

		// Reintroduce transaction here!?
		foreach ($links as $link) {
			$title = $link->nodeValue;
			$ref = $link->getAttribute("href");
			$tag_str = '';
			if ($link->hasAttribute("tags"))
				$tag_str = $link->getAttribute("tags");
			$tags = explode(',', $tag_str);

			$desc_str = '';
			if ($link->hasAttribute("description"))
				$desc_str = $link->getAttribute("description");

			self::addBookmark($user, $db, $ref, $title, $tags, $desc_str);
		}

		return array();
	}

	/**
	 * @brief Check if url is valid or try to create a valid url.
	 * @param string $url Url to check for validity or to fix
	 * @return string|null A valid Url or null
	 * */
	public static function sanitizeURL($url, &$finalDestination = null) {

		if ($url == null || $url == "") {
			return null;
		}

		$hasProtocol = preg_match('/^.+:/i', $url);
		$isHttp = preg_match('/^http:/i', $url);

		if ($hasProtocol && !$isHttp) {
			return (self::isUrlValid($url) ? $url : null);
		}

		if (!$hasProtocol) {
			$url = 'http://' . $url;
		}

		if (!self::isUrlValid($url)) {
			return null;
		}

		$finalDestination = \OC::$server->getHTTPHelper()->getFinalLocationOfURL($url);
		
		$urlHost = parse_url($url, PHP_URL_HOST);
		$finalDestinationHost = parse_url($finalDestination, PHP_URL_HOST);

		$hostsAreEqual = ($urlHost == $finalDestinationHost) && ($urlHost != null) && ($urlHost != "");

		if (!strncmp($finalDestination, 'https', strlen('https')) && $hostsAreEqual) {
			$offset = 4;
			$strippedUrl = substr($url, $offset);
			$url = 'https' . $strippedUrl;
		}

		return (self::isUrlValid($url) ? $url : null);
	}

	/**
	 * @brief Url is valid if it contains scheme and host or path
	 * @param string $url Url to check
	 * @return boolean Result of check if url is valid
	 */
	public static function isUrlValid($url) {
		$result = parse_url($url);

		$scheme = "";
		$host = "";
		$path = "";

		if (isset($result['scheme'])) {
			$scheme = $result['scheme'];
		}

		if (isset($result['host'])) {
			$host = $result['host'];
		}

		if (isset($result['path'])) {
			$path = $result['path'];
		}

		if ($scheme == "" || ($host == "" && $path = "")) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * @brief Load Url and receive Metadata (Title)
	 * @param string $url Url to load and analyze
	 * @return array Metadata for url;
	 * */
	public static function getURLMetadata($url, $finalDestination = null) {

		$metadata = array();
		$metadata['url'] = $url;

		// Don't try to fetch metadata if url doesn't start with http(s)
		if (strncmp($url, 'http', strlen('http'))) {
			$metadata['title'] = $url;
			return $metadata;
		}

		// Get the final destination of a possibly redirected url and fetch headers there.
		if ($finalDestination == null) {
			$finalDestination = \OC::$server->getHTTPHelper()->getFinalLocationOfURL($url);
		}
		$headers = \OC::$server->getHTTPHelper()->getHeaders($finalDestination);

		//Only try to fetch content and parse title if content is supposed to be html
		if (isset($headers['Content-Type']) && strpos($headers['Content-Type'], 'text/html') !== false) {
			$title = Bookmarks::fetchHtmlTitle($finalDestination);
			if ($title != null) {
				$metadata['title'] = $title;
				return $metadata;
			}
		}

		$metadata['title'] = $url;
		return $metadata;
	}

	/**
	 * @brief Parses the html title out of a resource behind an url
	 * @param $url Url of resource to parse
	 * @return string|null Parsed title or null
	 * */
	private static function fetchHtmlTitle($url) {
		$page = \OC::$server->getHTTPHelper()->getUrlContent($url);

		//Check for encoding of site.
		//If not UTF-8 convert it.
		$encoding = array();
		preg_match('/charset="?(.*?)["|;]/i', $page, $encoding);

		if (isset($encoding[1])) {
			$decodeFrom = strtoupper($encoding[1]);
		} else {
			$decodeFrom = 'UTF-8';
		}

		if ($page) {

			if ($decodeFrom != 'UTF-8') {
				$page = iconv($decodeFrom, "UTF-8", $page);
			}

			preg_match("/<title>(.*)<\/title>/si", $page, $match);

			if (isset($match[1])) {
				return html_entity_decode($match[1]);
			} else {
				return null;
			}
		} else {
			return null;
		}
	}

	/**
	 * @brief Seperate Url String at comma charachter
	 * @param string $line String of Tags
	 * @return array Array of Tags
	 * */
	public static function analyzeTagRequest($line) {
		$tags = explode(',', $line);
		$filterTag = array();
		foreach ($tags as $tag) {
			if (trim($tag) != '')
				$filterTag[] = trim($tag);
		}
		return $filterTag;
	}

}
