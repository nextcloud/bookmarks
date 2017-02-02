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
		$qb = $this->db->getQueryBuilder();
		$qb->automaticTablePrefix(true);
		$qb
		->select('tag', $qb->createFunction('COUNT(*)'))
		->selectAlias('count(*)', 'nbr')
		->from('bookmarks_tags', 't')
		->innerJoin('t','bookmarks','b','b.id = t.bookmark_id AND b.user_id = :user_id');
		if (!empty($filterTags)) {
			$qb->where($qb->expr()->notIn('tag', $filterTags));
		}
		$qb
		->groupBy('tag')
		->orderBy('nbr', 'DESC')
		->setFirstResult($offset)
		->setMaxResults($limit);
		$qb->setParameter(':user_id', $userId);
		$tags = $qb->execute()->fetchAll();
		return $tags;
	}

	/**
	 * @brief Finds Bookmark with certain ID
	 * @param int $id BookmarkId
	 * @param string $userId UserId
	 * @return array Specific Bookmark
	 */
	public function findUniqueBookmark($id, $userId) {
		$qb = $this->db->getQueryBuilder();
		$qb->automaticTablePrefix(true);
		$qb
		->select('*')
		->from('bookmarks')
		->where('user_id = :user_id')
		->andWhere('id = :bm_id');
		$qb->setParameters(array(
		  ':user_id' => $userId,
		  ':bm_id' => $id
		));
		$result = $qb->execute()->fetch();
		
		$qb = $this->db->getQueryBuilder();
		$qb->automaticTablePrefix(true);
    $qb
		->select('tag')
		->from('bookmarks_tags')
		->where('bookmark_id = :bm_id');
		$qb->setParameters(array(
		  ':user_id' => $userId,
		  ':bm_id' => $id
		));
		$result['tags'] = $qb->execute()->fetchColumn();
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
		$qb = $this->db->getQueryBuilder();
		$qb->automaticTablePrefix(true);
    $qb
    ->select('id')
		->from('bookmarks')
		->where('user_id = :user_id')
		->andWhere('url = :url');
		$qb->setParameters(array(
		  ':user_id' => $userId,
		  ':url' => $encodedUrl
		));
		$result = $qb->execute()->fetch();
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

		$qb = $this->db->getQueryBuilder();
		$qb->automaticTablePrefix(true);
		$qb
		->select('id')
		->from('bookmarks')
		->where('user_id = :user_id')
		->andWhere('id = :bm_id');
		$qb->setParameters(array(
		  ':user_id' => $userId,
		  ':bm_id' => $id
		));

		$id = $qb->execute()->fetchColumn();
		if ($id === false) {
			return false;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->automaticTablePrefix(true);
		$qb
		->delete('bookmarks')
		->where('id = :bm_id');
		$qb->setParameters(array(
		  ':bm_id' => $id
		));
		$qb->execute();

		$qb = $this->db->getQueryBuilder();
		$qb->automaticTablePrefix(true);
		$qb
		->delete('bookmarks_tags')
		->where('bookmark_id = :bm_id');
		$qb->setParameters(array(
		  ':bm_id' => $id
		  ));
		$qb->execute();
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
		// Remove potentially duplicated tags
		$qb = $this->db->getQueryBuilder();
		$qb->automaticTablePrefix(true);
		$qb
		->delete('bookmarks_tags', 'tgs')
		->innerJoin('bm', 'bookmarks', 'tgs.bookmark_id = bm.id')
		->innerJoin('t', 'bookmarks_tags', 'tgs.bookmark_id = t.bookmark_id')
		->where('tgs.tag = :newtag')
		->andWhere('bm.user_id = :user_id')
		->andWhere('t.tag = :oldtag');
		$qb->setParameters(array(
		  ':newtag' => $new,
		  ':oldtag' => $old,
		  ':user_id' => $userId
		));
		$qb->execute();

		// Update tags to the new label
		$qb = $this->db->getQueryBuilder();
		$qb->automaticTablePrefix(true);
		$qb
		->update('bookmarks_tags', 'tgs')
		->set('tgs.tag', $new)
		->innerJoin('bm', 'bookmarks', 'tgs.bookmark_id = bm.id')
		->where('tgs.tag = :oldtag')
		->andWhere('bm.user_id = :user_id');
		$qb->setParameters(array(
		  ':oldtag' => $old,
		  ':user_id' => $userId
		));
		$qb->execute();

		return true;
	}

	/**
	 * @brief Delete a tag
	 * @param string $userid UserId
	 * @param string $old Tag Name to delete
	 * @return boolean Success of operation
	 */
	public function deleteTag($userid, $old) {
		$qb = $this->db->getQueryBuilder();
		$qb->automaticTablePrefix(true);
		$qb
		->delete('bookmarks_tags', 'tgs')
		->innerJoin('bm', 'bookmarks', 'tgs.bookmark_id = bm.id')
		->where('tgs.tag = :tag')
		->andWhere('bm.user_id = :user_id')
		->andWhere('t.tag = :oldtag');
		$qb->setParameters(array(
		  ':tag' => $old,
		  ':user_id' => $userId
		));
		return $qb->execute();
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

		$result = $query->execute($params);

		$qb = $this->db->getQueryBuilder();
		$qb->automaticTablePrefix(true);
		$qb
		->update('bookmarks', 'bm')
		->set('bm.url', htmlspecialchars_decode($url))
		->set('bm.title', htmlspecialchars_decode($title))
		->set('bm.public', $isPublic)
		->set('bm.description', htmlspecialchars_decode($description))
		->set('bm.lastmodified', 'UNIX_TIMESTAMP()')
		->where('bm.id = :bm_id')
		->andWhere('bm.user_id = :user_id');
		$qb->setParameters(array(
		  ':user_id' => $userId,
		  ':bm_id' => $id
		));

		$result = $qb->execute();
		// Abort the operation if bookmark couldn't be set
		// (probably because the user is not allowed to edit this bookmark)
		if ($result == 0) {
			exit();
		}

		// Remove old tags

		$qb = $this->db->getQueryBuilder();
		$qb->automaticTablePrefix(true);
		$qb
		->delete('bookmarks_tags', 'tgs')
		->where('tgs.bookmark_id = :bm_id');
		$qb->setParameters(array(
		  ':bm_id' => $id
		));
		$qb->execute();

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
		
		$qb = $this->db->getQueryBuilder();
		$qb->automaticTablePrefix(true);
		$qb
		->select('*')
		->from('bookmarks')
		->where($qb->expr()->like('url', '%:url')) // Find url in the db independantly from its protocol
		->andWhere('user_id = :user_id');
		$qb->setParameters(array(
		  ':url' => $decodedUrlNoPrefix,
		  ':user_id' => $userid
		));
		$row = $qb->execute()->fetch();
		
		if ($row) {
			$qb = $this->db->getQueryBuilder();
			$qb->automaticTablePrefix(true);
			$qb
			->update('bookmarks')
			->set('lastmodified', 'UNIX_TIMESTAMP()')
			->set('url', $decodedUrl);
			if (trim($title) != '') { // Do we replace the old title
				$qb->set('title', $title);
			}

			if (trim($description) != '') { // Do we replace the old description
				$qb->set('decription', $description);
			}

			$qb
			->where($qb->expr()->like('url', '%'.$decodedUrlNoPrefix)) // Find url in the db independantly from its protocol
			->andWhere('user_id = :user_id');
			$qb->setParameters(array(
			  ':user_id' => $userid
			));
			$qb->execute();
			return $row['id'];
		} else {
			$qb = $this->db->getQueryBuilder();
			$qb->automaticTablePrefix(true);
			$qb
			->insert('bookmarks')
			->values(array(
				'url' => ':url',
				'title' => ':title',
            	'user_id' => ':user_id',
				'public' => ':public',
				'added' => 'UNIX_TIMESTAMP()',
				'lastmodified' => 'UNIX_TIMESTAMP()',
				'description' => ':public'
			))
			->where('user_id = :user_id');
			$qb->setParameters(array(
				':user_id' => $userid,
				':url' => $decodedUrl,
				':title' => htmlspecialchars_decode($title), // XXX: Should the title update above also decode it first?
				':public' => $public,
				':description' => $description
			));	

			$qb->execute();

			$insertId = $qb->getLastInsertId();

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
		foreach ($tags as $tag) {
			$tag = trim($tag);
			if (empty($tag)) {
				//avoid saving white spaces
				continue;
			}

			// check if tag for this bookmark exists

			$qb = $this->db->getQueryBuilder();
			$qb->automaticTablePrefix(true);
			$qb
			->select('*')
			->from('bookmarks_tags')
			->where('bookmark_id = :bm_id')
			->andWhere('tag = :tag');
			$qb->setParameters(array(
			  ':tag' => $tag,
			  ':bm_id' => $bookmarkID
			));	

			if ($qb->execute()->fetch()) continue;

			$qb = $this->db->getQueryBuilder();
			$qb->automaticTablePrefix(true);
			$qb
			->insert('bookmarks_tags')
			->values(array(
				'tag' => ':tag',
				'bookmark_id' => ':bookmark_id'
			));
			$qb->setParameters(array(	
				':tag' => $tag,
				':bookmark_id' => $bookmarkID
			));
			$qb->execute();
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
