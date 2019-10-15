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

namespace OCA\Bookmarks;

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
		$this->limit = intval($config->getAppValue('bookmarks', 'performance.maxBookmarksperAccount', 0));
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
		if ($limit !== -1) {
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


	private function getBookmarkParentFolders($bookmarkId) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('folder_id')
			->from('bookmarks_folders_bookmarks')
			->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($bookmarkId)));
		$parentIds = $qb->execute()->fetchAll(\PDO::FETCH_COLUMN);
		// normalize postgres numbers to strings to be en par with mysql
		foreach ($parentIds as $key => $id) {
			$parentIds[$key] = (string) $id;
		}
		return $parentIds;
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
		return true;
	}

	/**
	 * @brief Remove a bookmark from a set of folders
	 * @param int $bookmarkID The bookmark reference
	 * @param array $folders Set of folders ids to add the bookmark to
	 * */
	public function removeFromFolders($userId, $bookmarkId, $folders) {
		$bm = $this->findUniqueBookmark($bookmarkId, $userId);

		if (!$bm) {
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
	public function importFile($userId, $file, $rootFolder=-1) {
		$result = ['children' => [], 'errors' => []];
		if (!$this->existsFolder($userId, $rootFolder)) {
			$result['errors'][] = $this->l->t('Not allowed to access folder to import into');
			return $result;
		}
		try {
			$this->bookmarksParser->parse(file_get_contents($file), false);
		} catch (\Exception $e) {
			$result['errors'][] = $e->getMessage();
			return $result;
		}
		foreach ($this->bookmarksParser->currentFolder['children'] as $folder) {
			$result['children'][] = $this->importFolder($userId, $folder, $rootFolder, $result['errors']);
		}
		foreach ($this->bookmarksParser->currentFolder['bookmarks'] as $bookmark) {
			try {
				$bmId = $this->addBookmark($userId, $bookmark['href'], $bookmark['title'], $bookmark['tags'], $bookmark['description'], false, [$rootFolder]);
				$result['children'][] = ['type' => 'bookmark', 'id' => $bmId, 'title' => $bookmark['title'], 'url' => $bookmark['href']];
			} catch (\InvalidArgumentException $e) {
				$this->logger->logException($e, ['app' => 'bookmarks']);
				$result['errors'][] = $this->l->t('Failed to import one bookmark, because: ') . $e->getMessage();
			}
		}
		return $result;
	}

	private function importFolder($userId, $folder, $parentId, &$errors = []) {
		$folderId = $this->addFolder($userId, $folder['title'], $parentId);
		$newFolder = ['type' => 'folder', 'id' => $folderId, 'title' => $folder['title'], 'children' => []];
		foreach ($folder['bookmarks'] as $bookmark) {
			try {
				$add_date = isset($bookmark['add_date']) ? $bookmark['add_date']->getTimestamp() : null;
				$bmId = $this->addBookmark($userId, $bookmark['href'], $bookmark['title'], $bookmark['tags'], $bookmark['description'], false, [$folderId], $add_date);
				$newFolder['children'][] = ['type' => 'bookmark', 'id' => $bmId, 'title' => $bookmark['title'], 'url' => $bookmark['href']];
			} catch (\InvalidArgumentException $e) {
				$this->logger->logException($e, ['app' => 'bookmarks']);
				$errors[] =  $this->l->t('Failed to import one bookmark, because: ') . $e->getMessage();
			}
		}
		foreach ($folder['children'] as $childFolder) {
			$newFolder['children'][] = $this->importFolder($userId, $childFolder, $folderId, $errors);
		}
		return $newFolder;
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
			if (trim($tag) !== '') {
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
