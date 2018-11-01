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

	/** @var UrlNormalizer */
	private $urlNormalizer;

	/** @var ILogger */
	private $logger;

	/** @var BookmarksParser */
	private $bookmarksParser;

	public function __construct(
		IDBConnection $db,
		IConfig $config,
		IL10N $l,
		LinkExplorer $linkExplorer,
		UrlNormalizer $urlNormalizer,
		EventDispatcherInterface $eventDispatcher,
		ILogger $logger,
		BookmarksParser $bookmarksParser
	) {
		$this->db = $db;
		$this->config = $config;
		$this->l = $l;
		$this->linkExplorer = $linkExplorer;
		$this->eventDispatcher = $eventDispatcher;
		$this->urlNormalizer = $urlNormalizer;
		$this->logger = $logger;
		$this->bookmarksParser = $bookmarksParser;
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
			->innerJoin('t', 'bookmarks', 'b', $qb->expr()->eq('b.id', 't.bookmark_id'))
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
	 * @brief Lists bookmark folders
	 * @param string $userId UserId
	 * @param int $root Root folder from which to return hierarchy, -1 for absolute root
	 * @param int $layers Number of hierarchy layers to return; 0 for all
	 * @return array the folders each in the format
	 *               ["id" => int, "title" => string, "parent_folder" => int, "children"=> array() ]
	 */
	public function listFolders($userId, $root = -1, $layers = 0) {
		if ($root !== -1 && $root !== '-1' && !$this->existsFolder($userId, $root)) {
			return false;
		}
		$childFolders = $this->listChildFolders($userId, $root);
		foreach ($childFolders as &$folder) {
			if ($layers !== 1) {
				$folder['children'] = $this->listFolders($userId, $folder['id'], $layers !== 0 ? $layers-1 : 0);
			}
		}
		return $childFolders;
	}

	/**
	 * @brief Lists bookmark folders' child folders (helper)
	 * @param string $userId UserId
	 * @param int $root Root folder from which to return hierarchy, -1 for absolute root
	 * @return array the folders each in the format ["id" => int, "title" => string, "parent_folder" => int ]
	 */
	public function listChildFolders($userId, $root = -1) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('id', 'title', 'parent_folder')
			->from('bookmarks_folders')
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)))
			->andWhere($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($root)))
			->orderBy('title', 'DESC');
		$childFolders = $qb->execute()->fetchAll();
		return $childFolders;
	}

	/**
	 * @brief Lists bookmark folders' child folders (helper)
	 * @param string $userId UserId
	 * @param int $root Root folder from which to return hierarchy, -1 for absolute root
	 * @return array the folders each in the format ["id" => int, "title" => string, "parent_folder" => int ]
	 */
	public function getFolderChildren($userId, $folderId = -1) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('id', 'title', 'parent_folder', 'index')
			->from('bookmarks_folders')
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)))
			->andWhere($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId)))
			->orderBy('title', 'DESC');
		$childFolders = $qb->execute()->fetchAll();


		$qb = $this->db->getQueryBuilder();
		$qb
			->select('bookmark_id', 'index')
			->from('bookmarks_folders_bookmarks')
			->where($qb->expr()->eq('folder_id', $qb->createPositionalParameter($folderId)));
		$childBookmarks = $qb->execute()->fetchAll();

		$children = array_merge($childFolders, $childBookmarks);
		array_multisort(array_column($children, 'index'), \SORT_ASC, $children);
		$children = array_map(function ($child) {
			return isset($child['bookmark_id']) ?
			  ['type' =>  'bookmark', 'id' => $child['bookmark_id']]
			: ['type' => 'folder', 'id' => $child['id']];
		}, $children);

		return $children;
	}

	/**
	 * @brief Lists bookmark folders' child folders (helper)
	 * @param string $userId UserId
	 * @param int $root Root folder from which to return hierarchy, -1 for absolute root
	 * @return array the folders each in the format ["id" => int, "title" => string, "parent_folder" => int ]
	 */
	public function setFolderChildren($userId, $folderId, $newChildrenOrder) {
		$existingChildren = $this->getFolderChildren($userId, $folderId);
		foreach ($existingChildren as $child) {
			if (!in_array($child, $newChildrenOrder)) {
				return false;
			}
			if (!isset($child['id'], $child['type'])) {
				return false;
			}
		}
		if (count($newChildrenOrder) !== count($existingChildren)) {
			return false;
		}
		foreach ($newChildrenOrder as $i => $child) {
			switch ($child['type']) {
				case'bookmark':
					$qb = $this->db->getQueryBuilder();
					$qb
						->update('bookmarks_folders_bookmarks')
						->set('index', $qb->createPositionalParameter($i))
						->where($qb->expr()->eq('bookmark_id', $qb->createPositionalParameter($child['id'])))
						->andWhere($qb->expr()->eq('folder_id', $qb->createPositionalParameter($folderId)));
					$qb->execute();
					break;
				case 'folder':
					$qb = $this->db->getQueryBuilder();
					$qb
						->update('bookmarks_folders')
						->set('index', $qb->createPositionalParameter($i))
						->where($qb->expr()->eq('id', $qb->createPositionalParameter($child['id'])))
						->andWhere($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId)));
					$qb->execute();
					break;
			}
		}
		return true;
	}

	/**
	 * @brief Add a folder
	 * @param string $userId UserId
	 * @param string title
	 * @param int $root Root folder from which to return hierarchy, -1 for absolute root
	 */
	public function addFolder($userId, $title='', $parent = -1) {
		if ($parent !== -1 && $parent !== '-1' && !$this->existsFolder($userId, $parent)) {
			return false;
		}
		$qb = $this->db->getQueryBuilder();
		$qb
			->insert('bookmarks_folders')
			->values([
				'title' => $qb->createNamedParameter($title),
				'user_id' => $qb->createNamedParameter($userId),
				'parent_folder' => $qb->createNamedParameter($parent),
				'index' => count($this->getFolderChildren($userId, $parent))
		  ]);
		if ($qb->execute()) {
			$id = $qb->getLastInsertId();
			return $id;
		} else {
			return false;
		}
	}

	/**
	 * @brief Modify a folder
	 * @param string $userId UserId
	 * @param string $title new title
	 * @param int $parent (optional) new parent folder, -1 for absolute root
	 */
	public function editFolder($userId, $folderId, $title=null, $parent = null) {
		if (!$this->existsFolder($userId, $folderId)) {
			return false;
		}

		if (!isset($title) && !isset($parent)) {
			return true;
		}
		$qb = $this->db->getQueryBuilder();
		$qb
			->update('bookmarks_folders')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($folderId)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		if (isset($parent)) {
			if ($parent !== -1 && $parent !== '-1' && !$this->existsFolder($userId, $parent)) {
				return false;
			}
			$qb->set('parent_folder', $qb->createNamedParameter($parent));
		}
		if (isset($title)) {
			$qb->set('title', $qb->createNamedParameter($title));
		}

		$result = $qb->execute();
		return $result !== 0;
	}

	public function getFolder($userId, $folderId) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('id', 'parent_folder', 'title', 'user_id')
			->from('bookmarks_folders')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($folderId)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $qb->execute()->fetch();
	}

	private function existsFolder($userId, $folderId) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('bookmarks_folders')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($folderId)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$result = $qb->execute()->fetchAll();

		if (count($result) === 0) {
			return false;
		}
		return true;
	}

	public function deleteFolder($userId, $folderId) {
		$dbType = $this->config->getSystemValue('dbtype', 'sqlite');
		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('bookmarks_folders')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($folderId)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$result = $qb->execute();

		if ($result === 0) {
			return;
		}

		// get all bookmarks that are in this folder *only*
		$qb = $this->db->getQueryBuilder();
		$qb
				->select('id')
				->from('bookmarks', 'b')
				->leftJoin('b', 'bookmarks_folders_bookmarks', 'f', $qb->expr()->eq('b.id', 'f.bookmark_id'))
				->where($qb->expr()->eq('f.folder_id', $qb->createNamedParameter($folderId)));
		$bookmarksToDelete = $qb->execute()->fetchAll(\PDO::FETCH_COLUMN);
		// ... and "delete" them
		foreach ($bookmarksToDelete as $bookmarkId) {
			// remove this folder from the list of parent folders
			$bookmark = $this->findUniqueBookmark($bookmarkId, $userId);
			$newFolders = [];
			foreach ($bookmark['folders'] as $oldFolderId) {
				if ((string) $oldFolderId === (string) $folderId) {
					continue;
				}
				$newFolders[] = $oldFolderId;
			}
			// only if no parent folders are left do we delete the bookmark as a whole
			if (count($newFolders) > 0) {
				$this->editBookmark($userId, $bookmarkId, $bookmark['url'], $bookmark['title'], $bookmark['tags'], $bookmark['description'], $bookmark['public'], $newFolders);
			} else {
				$this->deleteUrl($userId, $bookmarkId);
			}
		}

		// delete all subfolders
		$childFolders = $this->listChildFolders($userId, $folderId);
		foreach ($childFolders as $folder) {
			$this->deleteFolder($userId, $folder['id']);
		}
	}

	private function getBookmarkParentFolders($bookmarkId) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('folder_id')
			->from('bookmarks_folders_bookmarks')
			->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($bookmarkId)));
		return $qb->execute()->fetchAll(\PDO::FETCH_COLUMN);
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
		if ($result) {
			$qb = $this->db->getQueryBuilder();
			$qb
			->select('tag')
			->from('bookmarks_tags')
			->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($id)));
			$result['tags'] = $qb->execute()->fetchAll(\PDO::FETCH_COLUMN);
			$result['folders'] = $this->getBookmarkParentFolders($id);

			return $result;
		} else {
			return false;
		}
	}

	/**
	 * @brief Check if an URL is bookmarked
	 * @param string $url Url of a possible bookmark
	 * @param string $userId UserId
	 * @return bool|int the bookmark ID if existing, false otherwise
	 */
	public function bookmarkExists($url, $userId) {
		$encodedUrl = htmlspecialchars_decode($url);

		// normalize url
		$encodedUrl = $this->urlNormalizer->normalize($encodedUrl);

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
	 * @param bool $untagged if `true` only untagged bookmarks will be returned and the filters will have no effect
	 * @param int $parentFolder if set, only bookmarks that are in the specified folder will be returned
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
		$tagFilterConjunction = "and",
		$untagged = false,
		$parentFolder = null
	) {
		$dbType = $this->config->getSystemValue('dbtype', 'sqlite');
		if (is_string($filters)) {
			$filters = [$filters];
		}

		$tableAttributes = ['url', 'title', 'user_id', 'description',
			'public', 'added', 'lastmodified', 'clickcount'];

		$qb = $this->db->getQueryBuilder();

		$returnTags = true;
		$returnFolders = true;
		if ($requestedAttributes != null) {
			$key = array_search('tags', $requestedAttributes);
			if ($key == false) {
				$returnTags = false;
			} else {
				unset($requestedAttributes[$key]);
			}
			$selectedAttributes = array_intersect($tableAttributes, $requestedAttributes);
			array_push($selectedAttributes, 'id');
		} else {
			$selectedAttributes = $tableAttributes;
			array_push($selectedAttributes, 'id');
		}
		$qb->select($selectedAttributes);

		if ($dbType == 'pgsql') {
			$qb->selectAlias($qb->createFunction("array_to_string(array_agg(" . $qb->getColumnName('t.tag') . "), ',')"), 'tags');
		} else {
			$qb->selectAlias($qb->createFunction('GROUP_CONCAT(' . $qb->getColumnName('t.tag') . ')'), 'tags');
		}

		if (!in_array($sqlSortColumn, $tableAttributes)) {
			$sqlSortColumn = 'lastmodified';
		}

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tags', 't', $qb->expr()->eq('t.bookmark_id', 'b.id'))
			->leftJoin('b', 'bookmarks_folders_bookmarks', 'f', $qb->expr()->eq('f.bookmark_id', 'b.id'))
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userid)))
			->groupBy(array_merge($selectedAttributes, [$sqlSortColumn]));

		if (isset($parentFolder)) {
			$qb->andWhere($qb->expr()->eq('f.folder_id', $qb->createPositionalParameter($parentFolder)));
		}

		if ($public) {
			$qb->andWhere($qb->expr()->eq('public', $qb->createPositionalParameter(1)));
		}

		if ($untagged) {
			if ($dbType == 'pgsql') {
				$tagCol = $qb->createFunction("array_to_string(array_agg(" . $qb->getColumnName('t.tag') . "), ',')");
			} else {
				$tagCol = 'tags';
			}
			$qb->having($qb->expr()->orX(
				$qb->expr()->emptyString($tagCol),
				$qb->expr()->isNull($tagCol)
			));
		} elseif (count($filters) > 0) {
			$this->findBookmarksBuildFilter($qb, $filters, $filterTagOnly, $tagFilterConjunction);
		}

		if ($sqlSortColumn == 'title') {
			$qb->orderBy($qb->createFunction('UPPER(`title`)'), 'ASC');
		} else {
			$qb->orderBy($sqlSortColumn, 'DESC');
		}

		if ($limit != -1 && $limit !== false) {
			$qb->setMaxResults($limit);
			if ($offset != null) {
				$qb->setFirstResult($offset);
			}
		}
		$results = $qb->execute()->fetchAll();
		$bookmarks = [];
		foreach ($results as $result) {
			if ($returnTags) {
				// pgsql returns "", others null
				if ($result['tags'] === null || $result['tags'] === '') {
					$result['tags'] = [];
				} else {
					$result['tags'] = explode(',', $result['tags']);
				}
			} else {
				unset($result['tags']);
			}
			if ($returnFolders) {
				$result['folders'] = $this->getBookmarkParentFolders($result['id']);
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
					$qb->createPositionalParameter('%'.$this->db->escapeLikeParameter($filter).'%')
				);
			} else {
				$expr[] = $qb->expr()->iLike('tags', $qb->createPositionalParameter('%'.$this->db->escapeLikeParameter($filter).'%'));
			}
			if (!$filterTagOnly) {
				foreach ($otherColumns as $col) {
					$expr[] = $qb->expr()->iLike(
						$qb->createFunction($qb->getColumnName($col)),
						$qb->createPositionalParameter('%' . $this->db->escapeLikeParameter(strtolower($filter)) . '%')
					);
				}
			}
			$filterExpressions[] = call_user_func_array([$qb->expr(), 'orX'], $expr);
			$i++;
		}
		if ($connectWord == 'AND') {
			$filterExpression = call_user_func_array([$qb->expr(), 'andX'], $filterExpressions);
		} else {
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

		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('bookmarks_folders_bookmarks')
			->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($id)));
		$qb->execute();

		$this->eventDispatcher->dispatch(
			'\OCA\Bookmarks::onBookmarkDelete',
			new GenericEvent(null, ['id' => $id, 'userId' => $userId])
		);

		return true;
	}

	/**
	 * Delete all bookmarks of a specific user
	 * @param string $userrId User ID
	 */
	public function deleteAllBookmarks($userId) {
		$allBookmarks = $this->findBookmarks($userId, -1, 'id', [], false, -1);
		foreach ($allBookmarks as $bookmark) {
			$this->deleteUrl($userId, $bookmark['id']);
		}
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
	public function editBookmark($userid, $id, $url, $title, $tags = [], $description = '', $isPublic = false, $folders = null) {
		$isPublic = $isPublic ? 1 : 0;

		// normalize url
		$url = $this->urlNormalizer->normalize($url);

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
			return false;
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

		// Update folders
		if (isset($folders)) {
			// Remove from old folders
			$qb = $this->db->getQueryBuilder();
			$qb
			->delete('bookmarks_folders_bookmarks')
			->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($id)));
			$qb->execute();

			// Add New Tags
			$this->addToFolders($userid, $id, $folders);
		}

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
	 * @param array $folders ids of the parent folders for the new bookmark
	 * @return int The id of the bookmark created
	 */
	public function addBookmark($userid, $url, $title, $tags = [], $description = '', $isPublic = false, $folders = null) {
		$public = $isPublic ? 1 : 0;
		if (!isset($folders) || count($folders) === 0) {
			$folders = [-1]; // we have to do it this way, as we don't want people to add a bookmark with [] parents
		}

		// do some meta tag inspection of the link...

		// allow only http(s) and (s)ftp
		$protocols = '/^(https?|s?ftp)\:\/\//i';
		try {
			if (preg_match($protocols, $url)) {
				$data = $this->getURLMetadata($url);
			} else {
				// if no allowed protocol is given, evaluate https and https
				foreach (['https://', 'http://'] as $protocol) {
					$testUrl = $protocol . $url;
					$data = $this->getURLMetadata($testUrl);
					if (isset($data['basic']) && isset($data['basic']['title'])) {
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
			$url = $data['url'];
		}
		if ((!isset($title) || trim($title) === '')) {
			$title = isset($data['basic']) && isset($data['basic']['title'])? $data['basic']['title'] : $url;
		}
		if (isset($data['basic']['description']) && (!isset($description) || trim($description) === '')) {
			$description = $data['basic']['description'];
		}

		// Check if it is a valid URL (after adding http(s) prefix)
		$urlData = parse_url($url);
		if (!$this->isProperURL($urlData)) {
			throw new \InvalidArgumentException('Invalid URL supplied');
		}

		// normalize url
		$url = $this->urlNormalizer->normalize($url);

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
			->where($qb->expr()->like('url', $qb->createPositionalParameter(
				'%' . $this->db->escapeLikeParameter($decodedUrlNoPrefix)
			))) // Find url in the db independantly from its protocol
			->andWhere($qb->expr()->eq('user_id', $qb->createPositionalParameter($userid)));
		$row = $qb->execute()->fetch();

		if ($row) {
			if (trim($title) == '') { // Do we replace the old title
				$title = $row['title'];
			}

			if (trim($description) == '') { // Do we replace the old description
				$description = $row['description'];
			}

			$oldParentFolders = $this->getBookmarkParentFolders($row['id']);
			$folders = array_unique(array_merge($folders, $oldParentFolders), \SORT_STRING);

			$this->editBookmark($userid, $row['id'], $url, $title, $tags, $description, $isPublic, $folders);

			return $row['id'];
		} else {
			$qb = $this->db->getQueryBuilder();
			$qb
				->insert('bookmarks')
				->values([
					'url' => $qb->createParameter('url'),
					'title' => $qb->createParameter('title'),
					'user_id' => $qb->createParameter('user_id'),
					'public' => $qb->createParameter('public'),
					'added' => $qb->createFunction('UNIX_TIMESTAMP()'),
					'lastmodified' => $qb->createFunction('UNIX_TIMESTAMP()'),
					'description' => $qb->createParameter('description'),
				])
				->where($qb->expr()->eq('user_id', $qb->createParameter('user_id')));
			$qb->setParameters([
				'user_id' => $userid,
				'url' => $decodedUrl,
				'title' => htmlspecialchars_decode($title), // XXX: Should the title update above also decode it first?
				'public' => $public,
				'description' => $description
			]);

			$qb->execute();

			$insertId = $qb->getLastInsertId();

			if ($insertId !== false) {
				$this->addTags($insertId, $tags);
				$this->addToFolders($userid, $insertId, $folders);

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
	 * @brief Add a bookmark to a set of folders
	 * @param int $bookmarkID The bookmark reference
	 * @param array $folders Set of folders ids to add the bookmark to
	 * */
	public function addToFolders($userId, $bookmarkId, $folders) {
		foreach ($folders as $folderId) {
			// check if folder exists
			if ($folderId !== -1 && $folderId !== '-1') {
				$qb = $this->db->getQueryBuilder();
				$row = $qb
				->select('*')
				->from('bookmarks_folders')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($folderId)))
				->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

				if (!$qb->execute()->fetch()) {
					continue;
				}
			}

			if (!$this->findUniqueBookmark($bookmarkId, $userId)) {
				return false;
			}

			// check if this folder<->bookmark mapping already exists
			$qb = $this->db->getQueryBuilder();
			$qb
			->select('*')
			->from('bookmarks_folders_bookmarks')
			->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($bookmarkId)))
			->andWhere($qb->expr()->eq('folder_id', $qb->createNamedParameter($folderId)));

			if ($qb->execute()->fetch()) {
				continue;
			}

			$qb = $this->db->getQueryBuilder();
			$qb
				->insert('bookmarks_folders_bookmarks')
				->values([
					'folder_id' => $qb->createNamedParameter($folderId),
					'bookmark_id' => $qb->createNamedParameter($bookmarkId),
					'index' => count($this->getFolderChildren($userId, $folderId))
				]);
			$qb->execute();
		}
	}

	/**
	 * @brief Remove a bookmark from a set of folders
	 * @param int $bookmarkID The bookmark reference
	 * @param array $folders Set of folders ids to add the bookmark to
	 * */
	public function removeFromFolders($userId, $bookmarkId, $folders) {
		$bm = $this->findUniqueBookmark($bookmarkId, $userId);

		if (!bm) {
			return false;
		}

		$foldersLeft = count($bm['folders']);

		foreach ($folders as $folderId) {
			// check if folder exists
			if ($folderId !== -1 && $folderId !== '-1') {
				$qb = $this->db->getQueryBuilder();
				$row = $qb
				->select('*')
				->from('bookmarks_folders')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($folderId)))
				->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

				if (!$qb->execute()->fetch()) {
					continue;
				}
			}

			// check if this folder<->bookmark mapping exists
			$qb = $this->db->getQueryBuilder();
			$qb
			->select('*')
			->from('bookmarks_folders_bookmarks')
			->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($bookmarkId)))
			->andWhere($qb->expr()->eq('folder_id', $qb->createNamedParameter($folderId)));

			if (!$qb->execute()->fetch()) {
				continue;
			}

			$qb = $this->db->getQueryBuilder();
			$qb
				->delete('bookmarks_folders_bookmarks')
				->where($qb->expr()->eq('folder_id', $qb->createNamedParameter($folderId)))
				->andwhere($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($bookmarkId)));
			$qb->execute();

			$foldersLeft--;
		}
		if ($foldersLeft <= 0) {
			$this->deleteUrl($userId, $bookmarkId);
		}

		return true;
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

			if ($qb->execute()->fetch()) {
				continue;
			}

			$qb = $this->db->getQueryBuilder();
			$qb
				->insert('bookmarks_tags')
				->values([
					'tag' => $qb->createNamedParameter($tag),
					'bookmark_id' => $qb->createNamedParameter($bookmarkID)
				]);
			$qb->execute();
		}
	}

	/**
	 * @brief Import Bookmarks from html formatted file
	 * @param string $user User imported Bookmarks should belong to
	 * @param string $file Content to import
	 * @return null
	 * */
	public function importFile($userId, $file) {
		try {
			$this->bookmarksParser->parse(file_get_contents($file), false);
		} catch (\Exception $e) {
			return [$e->message];
		}
		$errors = [];
		foreach ($this->bookmarksParser->currentFolder['children'] as $folder) {
			$this->importFolder($userId, $folder, -1);
		}
		foreach ($this->bookmarksParser->currentFolder['bookmarks'] as $bookmark) {
			try {
				$this->addBookmark($userId, $bookmark['href'], $bookmark['title'], $bookmark['tags'], $bookmark['description'], false, [-1]);
			} catch (\InvalidArgumentException $e) {
				$this->logger->logException($e, ['app' => 'bookmarks']);
				$errors[] =  $this->l->t('Failed to import one bookmark, because: ') . $e->getMessage();
			}
		}
		return $errors;
	}

	private function importFolder($userId, $folder, $parentId) {
		$folderId = $this->addFolder($userId, $folder['title'], $parentId);
		foreach ($folder['bookmarks'] as $bookmark) {
			try {
				$this->addBookmark($userId, $bookmark['href'], $bookmark['title'], $bookmark['tags'], $bookmark['description'], false, [$folderId]);
			} catch (\InvalidArgumentException $e) {
				$this->logger->logException($e, ['app' => 'bookmarks']);
				$errors[] =  $this->l->t('Failed to import one bookmark, because: ') . $e->getMessage();
			}
		}
		foreach ($folder['children'] as $childFolder) {
			$this->importFolder($userId, $childFolder, $folderId);
		}
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
		$filterTag = [];
		foreach ($tags as $tag) {
			if (trim($tag) != '') {
				$filterTag[] = trim($tag);
			}
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
