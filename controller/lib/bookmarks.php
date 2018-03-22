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

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Bookmarks {

	/** @var IDBConnection */
	private $db;

	/** @var IConfig */
	private $config;

	/** @var IL10N */
	private $l;
	
    /** @var LinkExplorer */
	private $linkExplorer;

	/** @var EventDispatcherInterface */
	private $eventDispatcher;

	/** @var ILogger */
	private $logger;

	public function __construct(
		IDBConnection $db,
		IConfig $config,
		IL10N $l,
        LinkExplorer $linkExplorer,
		EventDispatcherInterface $eventDispatcher,
		ILogger $logger
	) {
		$this->db = $db;
		$this->config = $config;
		$this->l = $l;
		$this->linkExplorer = $linkExplorer;
		$this->eventDispatcher = $eventDispatcher;
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
		$qb
			->select('t.tag')
			->selectAlias($qb->createFunction('COUNT(' . $qb->getColumnName('t.bookmark_id') . ')'), 'nbr')
			->from('bookmarks_tags', 't')
			->innerJoin('t','bookmarks','b', $qb->expr()->eq('b.id', 't.bookmark_id'))
			->where($qb->expr()->eq('b.user_id', $qb->createNamedParameter($userId)));
		if (!empty($filterTags)) {
			$qb->andWhere($qb->expr()->notIn('t.tag', array_map([$qb, 'createNamedParameter'], $filterTags)));
		}
		$qb
			->groupBy('t.tag')
			->orderBy('nbr', 'DESC')
			->setFirstResult($offset);
		if ($limit != -1) {
			$qb->setMaxResults($limit);
		}
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
		$qb
			->select('*')
			->from('bookmarks')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$result = $qb->execute()->fetch();
		
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('tag')
			->from('bookmarks_tags')
			->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($id)));
		$result['tags'] = $qb->execute()->fetchAll(\PDO::FETCH_COLUMN);
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
		$qb
			->select('id')
			->from('bookmarks')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('url', $qb->createNamedParameter($encodedUrl)));
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

		$tableAttributes = array('id', 'url', 'title', 'user_id', 'description',
			'public', 'added', 'lastmodified', 'clickcount', 'image');

		$returnTags = true;
		
		$qb = $this->db->getQueryBuilder();
		
		if ($requestedAttributes != null) {
			$key = array_search('tags', $requestedAttributes);
			if ($key == false) {
				$returnTags = false;
			} else {
				unset($requestedAttributes[$key]);
			}
			$selectedAttributes = array_intersect($tableAttributes, $requestedAttributes);
			$qb->select($selectedAttributes);
		}else{
			$selectedAttributes = $tableAttributes;
		}
		$qb->select($selectedAttributes);

		if ($dbType == 'pgsql') {
			$qb->selectAlias($qb->createFunction("array_to_string(array_agg(" . $qb->getColumnName('t.tag') . "), ',')"), 'tags');
        }else{
			$qb->selectAlias($qb->createFunction('GROUP_CONCAT(' . $qb->getColumnName('t.tag') . ')'), 'tags');
		}
		if (!in_array($sqlSortColumn, $tableAttributes)) {
			$sqlSortColumn = 'lastmodified';
		}

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tags', 't', $qb->expr()->eq('t.bookmark_id', 'b.id'))
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userid)))
			->groupBy(array_merge($selectedAttributes, [$sqlSortColumn]));

		if ($public) {
			$qb->andWhere($qb->expr()->eq('public', $qb->createNamedParameter(1)));
		}
		
		if (count($filters) > 0) {
			$this->findBookmarksBuildFilter($qb, $filters, $filterTagOnly, $tagFilterConjunction);
		}

		$qb->orderBy($sqlSortColumn, 'DESC');
		if ($limit != -1 && $limit !== false) {
			$qb->setMaxResults($limit);
			if ($offset != null) {
				$qb->setFirstResult($offset);
			}
		}
		$results = $qb->execute()->fetchAll();
		$bookmarks = array();
		foreach ($results as $result) {
			if ($returnTags) {
				// pgsql returns "", others null
				if($result['tags'] === null || $result['tags'] === '') {
					$result['tags'] = [];
				} else {
					$result['tags'] = explode(',', $result['tags']);
				}
			} else {
				unset($result['tags']);
			}
			$bookmarks[] = $result;
		}
		return $bookmarks;
	}

	/**
	 * @param IQueryBuilder $qb
	 * @param array $filters
	 * @param bool $filterTagOnly
	 * @param string $tagFilterConjunction
	 */
	private function findBookmarksBuildFilter(&$qb, $filters, $filterTagOnly, $tagFilterConjunction) {
		$dbType = $this->config->getSystemValue('dbtype', 'sqlite');
		$connectWord = 'AND';
		if ($tagFilterConjunction == 'or') {
			$connectWord = 'OR';
		}
		if (count($filters) == 0) {
			return;
		}
		$filterExpressions = [];
		$otherColumns = ['b.url', 'b.title', 'b.description'];
		$i = 0;
		foreach ($filters as $filter) {
      		$expr = [];
			if ($dbType == 'pgsql') {
				$expr[] = $qb->expr()->iLike(
					// Postgres doesn't like select aliases in HAVING clauses, well f*** you too!
					$qb->createFunction("array_to_string(array_agg(" . $qb->getColumnName('t.tag') . "), ',')"),
					$qb->createNamedParameter('%'.$this->db->escapeLikeParameter($filter).'%')
				);
			}else{
				$expr[] = $qb->expr()->iLike('tags', $qb->createNamedParameter('%'.$this->db->escapeLikeParameter($filter).'%'));
			}
			if (!$filterTagOnly) {
				foreach ($otherColumns as $col) {
					$expr[] = $qb->expr()->iLike(
						$qb->createFunction($qb->getColumnName($col)),
						$qb->createNamedParameter('%' . $this->db->escapeLikeParameter(strtolower($filter)) . '%')
					);
				}
			}
			$filterExpressions[] = call_user_func_array([$qb->expr(), 'orX'], $expr);
      		$i++;
		}
		if ($connectWord == 'AND') {
			$filterExpression = call_user_func_array([$qb->expr(), 'andX'], $filterExpressions);
		}else {
			$filterExpression = call_user_func_array([$qb->expr(), 'orX'], $filterExpressions);
		}
		$qb->having($filterExpression);
	}

	/**
	 * @brief Delete bookmark with specific id
	 * @param string $userId UserId
	 * @param int $id Bookmark ID to delete
	 * @return boolean Success of operation
	 */
	public function deleteUrl($userId, $id) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('id')
			->from('bookmarks')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		$id = $qb->execute()->fetchColumn();
		if ($id === false) {
			return false;
		}

		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('bookmarks')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$qb->execute();

		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('bookmarks_tags')
			->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($id)));
		$qb->execute();

		$this->eventDispatcher->dispatch(
			'\OCA\Bookmarks::onBookmarkDelete',
			new GenericEvent(null, ['id' => $id, 'userId' => $userId])
		);

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
		// Remove about-to-be duplicated tags
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('tgs.bookmark_id')
			->from('bookmarks_tags', 'tgs')
			->innerJoin('tgs', 'bookmarks', 'bm', $qb->expr()->eq('tgs.bookmark_id', 'bm.id'))
			->innerJoin('tgs', 'bookmarks_tags', 't', $qb->expr()->eq('tgs.bookmark_id', 't.bookmark_id'))
			->where($qb->expr()->eq('tgs.tag', $qb->createNamedParameter($new)))
			->andWhere($qb->expr()->eq('bm.user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('t.tag', $qb->createNamedParameter($old)));
		$duplicates = $qb->execute()->fetchAll(\PDO::FETCH_COLUMN);
		if (count($duplicates) !== 0) {
			$qb = $this->db->getQueryBuilder();
			$qb
				->delete('bookmarks_tags')
				->where($qb->expr()->in('bookmark_id', array_map([$qb, 'createNamedParameter'], $duplicates)))
				->andWhere($qb->expr()->eq('tag', $qb->createNamedParameter($old)));
			$qb->execute();
		}

		// Update tags to the new label
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('tgs.bookmark_id')
			->from('bookmarks_tags', 'tgs')
			->innerJoin('tgs', 'bookmarks', 'bm', $qb->expr()->eq('tgs.bookmark_id', 'bm.id'))
			->where($qb->expr()->eq('tgs.tag', $qb->createNamedParameter($old)))
			->andWhere($qb->expr()->eq('bm.user_id', $qb->createNamedParameter($userId)));
		$bookmarks = $qb->execute()->fetchAll(\PDO::FETCH_COLUMN);
		if (count($bookmarks) !== 0) {
			$qb = $this->db->getQueryBuilder();
			$qb
				->update('bookmarks_tags')
				->set('tag', $qb->createNamedParameter($new))
				->where($qb->expr()->eq('tag', $qb->createNamedParameter($old)))
				->andWhere($qb->expr()->in('bookmark_id', array_map([$qb, 'createNamedParameter'], $bookmarks)));
			$qb->execute();
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
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('tgs.bookmark_id')
			->from('bookmarks_tags', 'tgs')
			->innerJoin('tgs', 'bookmarks', 'bm', $qb->expr()->eq('tgs.bookmark_id', 'bm.id'))
			->where($qb->expr()->eq('tgs.tag', $qb->createNamedParameter($old)))
			->andWhere($qb->expr()->eq('bm.user_id', $qb->createNamedParameter($userid)));
		$bookmarks = $qb->execute()->fetchAll(\PDO::FETCH_COLUMN);
		if ($bookmarks !== false) {
			$qb = $this->db->getQueryBuilder();
			$qb
				->delete('bookmarks_tags')
				->where($qb->expr()->eq('tag', $qb->createNamedParameter($old)))
				->andWhere($qb->expr()->in('bookmark_id', array_map([$qb, 'createNamedParameter'], $bookmarks)));
			return $qb->execute();
		}
		return true;
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

		$qb = $this->db->getQueryBuilder();
		$qb
			->update('bookmarks')
			->set('url', $qb->createNamedParameter(htmlspecialchars_decode($url)))
			->set('title', $qb->createNamedParameter(htmlspecialchars_decode($title)))
			->set('public', $qb->createNamedParameter($isPublic))
			->set('description', $qb->createNamedParameter(htmlspecialchars_decode($description)))
			->set('lastmodified', $qb->createFunction('UNIX_TIMESTAMP()'))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userid)));

		$result = $qb->execute();
		// Abort the operation if bookmark couldn't be set
		// (probably because the user is not allowed to edit this bookmark)
		if ($result == 0) {
			exit();
		}

		$this->eventDispatcher->dispatch(
			'\OCA\Bookmarks::onBookmarkUpdate',
			new GenericEvent(null, ['id' => $id, 'userId' => $userid])
		);

		// Remove old tags

		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('bookmarks_tags')
			->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($id)));
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
	 * @param string $image URL to a visual representation of the bookmarked site
	 * @return int The id of the bookmark created
	 */
	public function addBookmark($userid, $url, $title, $tags = array(), $description = '', $isPublic = false, $image = null) {
		$public = $isPublic ? 1 : 0;
		
        // do some meta tag inspection of the link...

		// allow only http(s) and (s)ftp
		$protocols = '/^(https?|s?ftp)\:\/\//i';
		try {
			if (preg_match($protocols, $url)) {
				$data = $this->getURLMetadata($url);
			} else {
				// if no allowed protocol is given, evaluate https and https
				foreach(['https://', 'http://'] as $protocol) {
					$testUrl = $protocol . $url;
					$data = $this->getURLMetadata($testUrl);
					if(isset($data['title'])) {
						break;
					}
				}
			}
		} catch (\Exception $e) {
			// only because the server cannot reach a certain URL it does not
			// mean the user's browser cannot.
			\OC::$server->getLogger()->logException($e, ['app' => 'bookmarks']);
		}
		if (isset($data['url'])) {
			$url   = $data['url'];
		}
		if ((!isset($title) || trim($title) === '')) {
			$title =  isset($data['title'])? $data['title'] : $url;
		}
		if (isset($data['description']) && (!isset($description) || trim($description) === '')) {
		  $description = $data['description'];
		}
		if (isset($data['image']) && !isset($image)) {
		  $image = $data['image'];
		}

		// Check if it is a valid URL (after adding http(s) prefix)
        $urlData = parse_url($url);
		if(!$this->isProperURL($urlData)) {
			throw new \InvalidArgumentException('Invalid URL supplied');
		}
    
        $urlWithoutPrefix = trim(substr($url, strpos($url, "://") + 3)); // Removes everything from the url before the "://" pattern (included)
		$decodedUrlNoPrefix = htmlspecialchars_decode($urlWithoutPrefix);
		$decodedUrl = htmlspecialchars_decode($url);

		$title = mb_substr($title, 0, 4096);
		$description = mb_substr($description, 0, 4096);

		// Change lastmodified date if the record if already exists
		
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('bookmarks')
			->where($qb->expr()->like('url', $qb->createParameter('url'))) // Find url in the db independantly from its protocol
			->andWhere($qb->expr()->eq('user_id', $qb->createParameter('userID')));
		$qb->setParameters([
			'userID' => $userid,
			'url' => '%' . $this->db->escapeLikeParameter($decodedUrlNoPrefix)
		]);
		$row = $qb->execute()->fetch();
		
		if ($row) {
			$qb = $this->db->getQueryBuilder();
			$qb
				->update('bookmarks')
				->set('lastmodified', $qb->createFunction('UNIX_TIMESTAMP()'))
				->set('url', $qb->createParameter('url'));
			if (trim($title) != '') { // Do we replace the old title
				$qb->set('title', $qb->createParameter('title'));
			}

			if (trim($description) != '') { // Do we replace the old description
				$qb->set('description', $qb->createParameter('description'));
			}
			
			if (isset($image)) { // Do we replace the old description
				$qb->set('image', $qb->createParameter('image'));
			}

			$qb
				->where($qb->expr()->like('url', $qb->createParameter('compareUrl'))) // Find url in the db independantly from its protocol
				->andWhere($qb->expr()->eq('user_id', $qb->createParameter('userID')));
				$qb->setParameters([
					'userID' => $userid,
					'url' => $decodedUrl,
					'compareUrl' => '%' . $this->db->escapeLikeParameter($decodedUrlNoPrefix),
					'title' => $title,
					'description' => $description,
					'image' => $image,
				]);
				$qb->execute();

			$this->eventDispatcher->dispatch(
				'\OCA\Bookmarks::onBookmarkUpdate',
				new GenericEvent(null, ['id' => $row['id'], 'userId' => $userid])
			);

			return $row['id'];
		} else {
			$qb = $this->db->getQueryBuilder();
			$qb
				->insert('bookmarks')
				->values(array(
					'url' => $qb->createParameter('url'),
					'title' => $qb->createParameter('title'),
					'user_id' => $qb->createParameter('user_id'),
					'public' => $qb->createParameter('public'),
					'added' => $qb->createFunction('UNIX_TIMESTAMP()'),
					'lastmodified' => $qb->createFunction('UNIX_TIMESTAMP()'),
					'description' => $qb->createParameter('description'),
					'image' => $qb->createParameter('image'),
				))
				->where($qb->expr()->eq('user_id', $qb->createParameter('user_id')));
			$qb->setParameters(array(
				'user_id' => $userid,
				'url' => $decodedUrl,
				'title' => htmlspecialchars_decode($title), // XXX: Should the title update above also decode it first?
				'public' => $public,
				'description' => $description,
				'image' => $image,
			));	

			$qb->execute();

			$insertId = $qb->getLastInsertId();

			if ($insertId !== false) {
				$this->addTags($insertId, $tags);

				$this->eventDispatcher->dispatch(
					'\OCA\Bookmarks::onBookmarkCreate',
					new GenericEvent(null, ['id' => $insertId, 'userId' => $userid])
				);

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
			$qb
			->select('*')
			->from('bookmarks_tags')
				->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($bookmarkID)))
				->andWhere($qb->expr()->eq('tag', $qb->createNamedParameter($tag)));

			if ($qb->execute()->fetch()) continue;

			$qb = $this->db->getQueryBuilder();
			$qb
				->insert('bookmarks_tags')
				->values(array(
					'tag' => $qb->createNamedParameter($tag),
					'bookmark_id' => $qb->createNamedParameter($bookmarkID)
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
		$dom = new \domDocument();

		$dom->loadHTMLFile($file, \LIBXML_PARSEHUGE);
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
			if ($link->hasAttribute("description")) {
				$descriptionStr = $link->getAttribute("description");
			} else {
				/* Get description from a following <DD> when link in a
				 * <DT> (e.g., Delicious export, firefox) */
				$parent = $link->parentNode;
				if ($parent && $parent->tagName == "dt") {
					$dd = $parent->nextSibling;
					if ($dd->tagName == "dd") {
						$descriptionStr = trim($dd->nodeValue);
					}
				}
			}
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
	 * @return array Metadata for url;
	 * @throws \Exception|ClientException
	 */
	public function getURLMetadata($url) {
	    return $this->linkExplorer->get($url);
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

	/**
	 * Checks whether parse_url was able to return proper URL data
	 *
	 * @param bool|array $urlData result of parse_url
	 * @return bool
	 */
	public function isProperURL($urlData) {
		if ($urlData === false || !isset($urlData['scheme']) || !isset($urlData['host'])) {
			return false;
		}
		return true;
	}
}
