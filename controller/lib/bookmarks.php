<?php

/**
 * @author Arthur Schiwon
 * @copyright 2016 Arthur Schiwon blizzz@arthur-schiwon.de
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

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;

class Bookmarks {

	/** @var IDBConnection */
	private $db;

	/** @var IConfig */
	private $config;

	/** @var IL10N */
	private $l;

	/** @var IClientService */
	private $httpClientService;

	/** @var ILogger */
	private $logger;

	public function __construct(
		IDBConnection $db,
		IConfig $config,
		IL10N $l,
		IClientService $httpClientService,
		ILogger $logger
	) {
		$this->db = $db;
		$this->config = $config;
		$this->l = $l;
		$this->httpClientService = $httpClientService;
		$this->logger = $logger;
	}

	/**
	 * @brief Finds all tags for bookmarks
	 * @param string $userId UserId
	 * @param array $filterTags of tag to look for if empty then every tag
	 * @param int $offset
	 * @param int $limit
	 * @return array Found Tags
	 */
	public function findTags($userId, $filterTags = [], $offset = 0, $limit = -1) {
		$params = array_merge($filterTags, $filterTags);
		array_unshift($params, $userId);
		$notIn = '';
		if (!empty($filterTags)) {
			$existClause = " AND	exists (select 1 from `*PREFIX*bookmarks_tags`
				`t2` where `t2`.`bookmark_id` = `t`.`bookmark_id` and `tag` = ?) ";

			$notIn = ' AND `tag` not in (' . implode(',', array_fill(0, count($filterTags), '?')) . ')' .
					str_repeat($existClause, count($filterTags));
		}
		$sql = 'SELECT tag, count(*) AS nbr FROM *PREFIX*bookmarks_tags t ' .
				' WHERE EXISTS( SELECT 1 FROM *PREFIX*bookmarks bm ' .
				'	WHERE  t.bookmark_id  = bm.id AND user_id = ?) ' .
				$notIn .
				' GROUP BY `tag` ORDER BY `nbr` DESC ';

		$query = $this->db->prepare($sql, $limit, $offset);
		$query->execute($params);
		$tags = $query->fetchAll();
		return $tags;
	}

	/**
	 * @brief Finds Bookmark with certain ID
	 * @param int $id BookmarkId
	 * @param string $userId UserId
	 * @return array Specific Bookmark
	 */
	public function findUniqueBookmark($id, $userId) {
		$dbType = $this->config->getSystemValue('dbtype', 'sqlite');
		if ($dbType == 'pgsql') {
			$groupFunction = 'array_agg(`tag`)';
		} else {
			$groupFunction = 'GROUP_CONCAT(`tag`)';
		}
		$sql = "SELECT *, (SELECT $groupFunction FROM `*PREFIX*bookmarks_tags`
			       WHERE `bookmark_id` = `b`.`id`) AS `tags`
				FROM `*PREFIX*bookmarks` `b`
				WHERE `user_id` = ? AND `id` = ?";
		$query = $this->db->prepare($sql);
		$query->execute(array($userId, $id));
		$result = $query->fetch();
		$result['tags'] = explode(',', $result['tags']);
		return $result;
	}

	/**
	 * @brief Check if an URL is bookmarked
	 * @param string $url Url of a possible bookmark
	 * @param string $userId UserId
	 * @return bool|int the bookmark ID if existing, false otherwise
	 */
	public function bookmarkExists($url, $userId) {
		$encodedUrl = htmlspecialchars_decode($url);
		$sql = "SELECT id FROM `*PREFIX*bookmarks` WHERE `url` = ? AND `user_id` = ?";
		$query = $this->db->prepare($sql);
		$query->execute(array($encodedUrl, $userId));
		$result = $query->fetch();
		if ($result) {
			return $result['id'];
		} else {
			return false;
		}
	}

	/**
	 * @brief Finds all bookmarks, matching the filter
	 * @param string $userid UserId
	 * @param int $offset offset
	 * @param string $sqlSortColumn result with this column
	 * @param string|array $filters filters can be: empty -> no filter, a string -> filter this, a string array -> filter for all strings
	 * @param bool $filterTagOnly true, filter affects only tags, else filter affects url, title and tags
	 * @param int $limit limit of items to return (default 10) if -1 or false then all items are returned
	 * @param bool $public check if only public bookmarks should be returned
	 * @param array $requestedAttributes select all the attributes that should be returned. default is * + tags
	 * @param string $tagFilterConjunction select wether the filterTagOnly should filter with an AND or an OR  conjunction
	 * @return array Collection of specified bookmarks
	 */
	public function findBookmarks(
		$userid,
		$offset,
		$sqlSortColumn,
		$filters,
		$filterTagOnly,
		$limit = 10,
		$public = false,
		$requestedAttributes = null,
		$tagFilterConjunction = "and"
	) {
		$dbType = $this->config->getSystemValue('dbtype', 'sqlite');
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

		if ($dbType == 'pgsql') {
			$sql = "SELECT " . $toSelect . " FROM (SELECT *, (select array_to_string(array_agg(`tag`),',')
					FROM `*PREFIX*bookmarks_tags` WHERE `bookmark_id` = `b2`.`id`) AS `tags`
				FROM `*PREFIX*bookmarks` `b2`
				WHERE `user_id` = ? ) as `b` WHERE true ";
		} else {
			$sql = "SELECT " . $toSelect . ", (SELECT GROUP_CONCAT(`tag`) FROM `*PREFIX*bookmarks_tags`
				WHERE `bookmark_id` = `b`.`id`) AS `tags`
				FROM `*PREFIX*bookmarks` `b`
				WHERE `user_id` = ? ";
		}

		$params = array($userid);

		if ($public) {
			$sql .= ' AND public = 1 ';
		}

		if (count($filters) > 0) {
			$this->findBookmarksBuildFilter($sql, $params, $filters, $filterTagOnly, $tagFilterConjunction, $dbType);
		}

		if (!in_array($sqlSortColumn, $tableAttributes)) {
			$sqlSortColumn = 'lastmodified';
		}
		$sql .= " ORDER BY " . $sqlSortColumn . " DESC ";
		if ($limit == -1 || $limit === false) {
			$limit = null;
			$offset = null;
		}

		$query = $this->db->prepare($sql, $limit, $offset);
		$query->execute($params);
		$results = $query->fetchAll();
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

	private function findBookmarksBuildFilter(&$sql, &$params, $filters, $filterTagOnly, $tagFilterConjunction, $dbType) {
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
			$existClause = " exists (SELECT `id` FROM  `*PREFIX*bookmarks_tags`
				`t2` WHERE `t2`.`bookmark_id` = `b`.`id` AND `tag` = ?) ";
			$sql .= str_repeat($existClause . $connectWord, count($filters));
			if ($tagOrSearch) {
				$sql = rtrim($sql, 'OR');
				$sql .= ')';
			} else {
				$sql = rtrim($sql, 'AND');
			}
			$params = array_merge($params, $filters);
		} else {
			if ($dbType == 'mysql') { //Dirty hack to allow usage of alias in where
				$sql .= ' HAVING true ';
			}
			foreach ($filters as $filter) {
				if ($dbType == 'mysql') {
					$sql .= ' AND lower( concat(url,title,description,IFNULL(tags,\'\') )) like ? ';
				} else {
					$sql .= ' AND lower(url || title || description || IFNULL(tags,\'\') ) like ? ';
				}
				$params[] = '%' . strtolower($filter) . '%';
			}
		}
	}

	/**
	 * @brief Delete bookmark with specific id
	 * @param string $userId UserId
	 * @param int $id Bookmark ID to delete
	 * @return boolean Success of operation
	 */
	public function deleteUrl($userId, $id) {
		$user = $userId;

		$query = $this->db->prepare("
				SELECT `id` FROM `*PREFIX*bookmarks`
				WHERE `id` = ?
				AND `user_id` = ?
				");

		$query->execute(array($id, $user));
		$id = $query->fetchColumn();
		if ($id === false) {
			return false;
		}

		$query = $this->db->prepare("
			DELETE FROM `*PREFIX*bookmarks`
			WHERE `id` = ?
			");

		$query->execute(array($id));

		$query = $this->db->prepare("
			DELETE FROM `*PREFIX*bookmarks_tags`
			WHERE `bookmark_id` = ?
			");

		$query->execute(array($id));
		return true;
	}

	/**
	 * @brief Rename a tag
	 * @param string $userId UserId
	 * @param string $old Old Tag Name
	 * @param string $new New Tag Name
	 * @return boolean Success of operation
	 */
	public function renameTag($userId, $old, $new) {
		$dbType = $this->config->getSystemValue('dbtype', 'sqlite');


		if ($dbType == 'sqlite' or $dbType == 'sqlite3') {
			// Update tags to the new label unless it already exists a tag like this
			$query = $this->db->prepare("
				UPDATE OR REPLACE `*PREFIX*bookmarks_tags`
				SET `tag` = ?
				WHERE `tag` = ?
				AND exists( select `b`.`id` from `*PREFIX*bookmarks` `b`
				WHERE `b`.`user_id` = ? AND `bookmark_id` = `b`.`id`)
			");

			$params = [$new, $old, $userId];

			$query->execute($params);
		} else {

			// Remove potentially duplicated tags
			$query = $this->db->prepare("
			DELETE FROM `*PREFIX*bookmarks_tags` as `tgs` WHERE `tgs`.`tag` = ?
			AND exists( SELECT `id` FROM `*PREFIX*bookmarks` WHERE `user_id` = ?
			AND `tgs`.`bookmark_id` = `id`)
			AND exists( SELECT `t`.`tag` FROM `*PREFIX*bookmarks_tags` `t` where `t`.`tag` = ?
			AND `tgs`.`bookmark_id` = `t`.`bookmark_id`)");

			$params = [$new, $userId, $new];
			$query->execute($params);

			// Update tags to the new label unless it already exists a tag like this
			$query = $this->db->prepare("
			UPDATE `*PREFIX*bookmarks_tags`
			SET `tag` = ?
			WHERE `tag` = ?
			AND exists( SELECT `b`.`id` FROM `*PREFIX*bookmarks` `b`
			WHERE `b`.`user_id` = ? AND `bookmark_id` = `b`.`id`)
			");

			$params = [$new, $old, $userId];
			$query->execute($params);
		}

		return true;
	}

	/**
	 * @brief Delete a tag
	 * @param string $userid UserId
	 * @param string $old Tag Name to delete
	 * @return boolean Success of operation
	 */
	public function deleteTag($userid, $old) {

		// Update the record
		$query = $this->db->prepare("
		DELETE FROM `*PREFIX*bookmarks_tags`
		WHERE `tag` = ?
		AND exists( SELECT `id` FROM `*PREFIX*bookmarks` WHERE `user_id` = ? AND `bookmark_id` = `id`)
		");

		$params = [$old, $userid];
		$result = $query->execute($params);
		return $result;
	}

	/**
	 * Edit a bookmark
	 *
	 * @param string $userid UserId
	 * @param int $id The id of the bookmark to edit
	 * @param string $url The url to set
	 * @param string $title Name of the bookmark
	 * @param array $tags Simple array of tags to qualify the bookmark (different tags are taken from values)
	 * @param string $description A longer description about the bookmark
	 * @param boolean $isPublic True if the bookmark is publishable to not registered users
	 * @return null
	 */
	public function editBookmark($userid, $id, $url, $title, $tags = [], $description = '', $isPublic = false) {

		$isPublic = $isPublic ? 1 : 0;

		// Update the record
		$query = $this->db->prepare("
		UPDATE `*PREFIX*bookmarks` SET
			`url` = ?, `title` = ?, `public` = ?, `description` = ?,
			`lastmodified` = UNIX_TIMESTAMP()
		WHERE `id` = ?
		AND `user_id` = ?
		");

		$params = array(
			htmlspecialchars_decode($url),
			htmlspecialchars_decode($title),
			$isPublic,
			htmlspecialchars_decode($description),
			$id,
			$userid,
		);

		$result = $query->execute($params);

		// Abort the operation if bookmark couldn't be set
		// (probably because the user is not allowed to edit this bookmark)
		if ($result == 0) {
			exit();
		}

		// Remove old tags
		$sql = "DELETE FROM `*PREFIX*bookmarks_tags`  WHERE `bookmark_id` = ?";
		$query = $this->db->prepare($sql);
		$query->execute(array($id));

		// Add New Tags
		$this->addTags($id, $tags);

		return $id;
	}

	/**
	 * Add a bookmark
	 *
	 * @param string $userid UserId
	 * @param string $url
	 * @param string $title Name of the bookmark
	 * @param array $tags Simple array of tags to qualify the bookmark (different tags are taken from values)
	 * @param string $description A longer description about the bookmark
	 * @param boolean $isPublic True if the bookmark is publishable to not registered users
	 * @return int The id of the bookmark created
	 */
	public function addBookmark($userid, $url, $title, $tags = array(), $description = '', $isPublic = false) {
		$public = $isPublic ? 1 : 0;
		$urlWithoutPrefix = trim(substr($url, strpos($url, "://") + 3)); // Removes everything from the url before the "://" pattern (included)
		if($urlWithoutPrefix === '') {
			throw new \InvalidArgumentException('Bookmark URL is missing');
		}
		$decodedUrlNoPrefix = htmlspecialchars_decode($urlWithoutPrefix);
		$decodedUrl = htmlspecialchars_decode($url);

		$title = mb_substr($title, 0, 4096);
		$description = mb_substr($description, 0, 4096);

		// Change lastmodified date if the record if already exists
		$sql = "SELECT * from  `*PREFIX*bookmarks` WHERE `url` like ? AND `user_id` = ?";
		$query = $this->db->prepare($sql, 1);
		$query->execute(array('%'.$decodedUrlNoPrefix, $userid)); // Find url in the db independantly from its protocol
		if ($row = $query->fetch()) {
			$params = array();
			$titleStr = '';
			if (trim($title) != '') { // Do we replace the old title
				$titleStr = ' , title = ?';
				$params[] = $title;
			}
			$descriptionStr = '';
			if (trim($description) != '') { // Do we replace the old description
				$descriptionStr = ' , description = ?';
				$params[] = $description;
			}
			$sql = "UPDATE `*PREFIX*bookmarks` SET `lastmodified` = "
					. "UNIX_TIMESTAMP() $titleStr $descriptionStr , `url` = ? WHERE `url` like ? and `user_id` = ?";
			$params[] = $decodedUrl;
			$params[] = '%'.$decodedUrlNoPrefix;
			$params[] = $userid;
			$query = $this->db->prepare($sql);
			$query->execute($params);
			return $row['id'];
		} else {
			$query = $this->db->prepare("
			INSERT INTO `*PREFIX*bookmarks`
			(`url`, `title`, `user_id`, `public`, `added`, `lastmodified`, `description`)
			VALUES (?, ?, ?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), ?)
			");

			$params = array(
				$decodedUrl,
				htmlspecialchars_decode($title),
				$userid,
				$public,
				$description,
			);
			$query->execute($params);

			$insertId = $this->db->lastInsertId('*PREFIX*bookmarks');

			if ($insertId !== false) {
				$this->addTags($insertId, $tags);
				return $insertId;
			}
		}
		return -1;
	}

	/**
	 * @brief Add a set of tags for a bookmark
	 * @param int $bookmarkID The bookmark reference
	 * @param array $tags Set of tags to add to the bookmark
	 * */
	private function addTags($bookmarkID, $tags) {
		$sql = 'INSERT INTO `*PREFIX*bookmarks_tags` (`bookmark_id`, `tag`) select ?, ? ';
		$dbType = $this->config->getSystemValue('dbtype', 'sqlite');

		if ($dbType === 'mysql') {
			$sql .= 'from dual ';
		}
		$sql .= 'where not exists(select * from `*PREFIX*bookmarks_tags` where `bookmark_id` = ? and `tag` = ?)';

		$query = $this->db->prepare($sql);
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
	 * @param string $user User imported Bookmarks should belong to
	 * @param string $file Content to import
	 * @return null
	 * */
	public function importFile($user, $file) {
		libxml_use_internal_errors(true);
		$dom = new \domDocument();

		$dom->loadHTMLFile($file);
		$links = $dom->getElementsByTagName('a');

		$errors = [];

		// Reintroduce transaction here!?
		foreach ($links as $link) {
			/* @var \DOMElement $link */
			$title = $link->nodeValue;
			$ref = $link->getAttribute("href");
			$tagStr = '';
			if ($link->hasAttribute("tags"))
				$tagStr = $link->getAttribute("tags");
			$tags = explode(',', $tagStr);

			$descriptionStr = '';
			if ($link->hasAttribute("description"))
				$descriptionStr = $link->getAttribute("description");
			try {
				$this->addBookmark($user, $ref, $title, $tags, $descriptionStr);
			} catch (\InvalidArgumentException $e) {
				$this->logger->logException($e, ['app' => 'bookmarks']);
				$errors[] =  $this->l->t('Failed to import one bookmark, because: ') . $e->getMessage();
			}
		}

		return $errors;
	}

	/**
	 * @brief Load Url and receive Metadata (Title)
	 * @param string $url Url to load and analyze
	 * @param bool $tryHarder modifies cURL options for another atttempt if the
	 *                        first request did not succeed (e.g. cURL error 18)
	 * @return array Metadata for url;
	 * @throws \Exception|ClientException
	 */
	public function getURLMetadata($url, $tryHarder = false) {
		$metadata = ['url' => $url];
		$page = $contentType = '';
		
		try {
			$client = $this->httpClientService->newClient();
			$options = [];
			if($tryHarder) {
				$curlOptions = [ 'curl' =>
					[ CURLOPT_HTTPHEADER => ['Expect:'] ]
				];
				if(version_compare(ClientInterface::VERSION, '6') === -1) {
					$options = ['config' => $curlOptions];
				} else {
					$options = $curlOptions;
				}
			}
			$request = $client->get($url, $options);
			$page = $request->getBody();
			$contentType = $request->getHeader('Content-Type');
		} catch (ClientException $e) {
			$errorCode = $e->getCode();
			if (!($errorCode >= 401 && $errorCode <= 403)) {
				// whitelist Unauthorized, Forbidden and Paid pages
				throw $e;
			}
		} catch (\GuzzleHttp\Exception\RequestException $e) {
			if($tryHarder) {
				throw $e;
			}
			return $this->getURLMetadata($url, true);
		} catch (\Exception $e) {
			throw $e;
		}
		
		//Check for encoding of site.
		//If not UTF-8 convert it.
		$encoding = array();
		preg_match('#.+?/.+?;\\s?charset\\s?=\\s?(.+)#i', $contentType, $encoding);
		if(empty($encoding)) {
			preg_match('/charset="?(.*?)["|;]/i', $page, $encoding);
		}

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
				$metadata['title'] = html_entity_decode($match[1]);
			}
		}
		
		return $metadata;
	}

	/**
	 * @brief Separate Url String at comma character
	 * @param $line String of Tags
	 * @return array Array of Tags
	 * */
	public function analyzeTagRequest($line) {
		$tags = explode(',', $line);
		$filterTag = array();
		foreach ($tags as $tag) {
			if (trim($tag) != '')
				$filterTag[] = trim($tag);
		}
		return $filterTag;
	}

}
