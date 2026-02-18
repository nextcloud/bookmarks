<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Migration;

use Closure;
use OCA\Bookmarks\Db\TreeMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version016002000Date20260218124723 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {

	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		$this->deduplicateAll($output);
	}

	/**
	 * Deduplicate bookmarks for all users.
	 */
	public function deduplicateAll(IOutput $output): void {
		$duplicates = $this->findDuplicates();

		$output->info('Merging n=' . count($duplicates) . ' per-user duplicate URLs in the database.');
		$output->startProgress(count($duplicates));

		foreach ($duplicates as $group) {
			$output->advance();
			if (count($group) < 2) {
				continue;
			}

			// Assume the first bookmark in the group is the primary
			$primary = $group[0];
			$primaryId = $primary['id'];

			// Merge all secondary bookmarks into the primary
			$count = count($group);
			for ($i = 1; $i < $count; $i++) {
				$secondary = $group[$i];
				$secondaryId = $secondary['id'];

				// Merge folders, tags, and descriptions
				$this->mergeFolders($primaryId, $secondaryId);
				$this->mergeTags($primaryId, $secondaryId);
				$this->mergeDescriptions($primaryId, $secondary['description']);

				// Delete the secondary bookmark
				$this->deleteBookmark($secondaryId);
			}
		}
	}

	/**
	 * Merge folders from a secondary bookmark into the primary bookmark.
	 * Assigns the last index value for the parent_folder to new entries.
	 *
	 * @param int $primaryId - ID of the primary bookmark.
	 * @param int $secondaryId - ID of the secondary bookmark to merge.
	 */
	public function mergeFolders(int $primaryId, int $secondaryId): void {
		$qb = $this->db->getQueryBuilder();

		// Fetch all folder assignments for the secondary bookmark
		$secondaryFolders = $qb->select('parent_folder')
			->from('bookmarks_tree')
			->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($secondaryId)))
			->andWhere($qb->expr()->eq('type', $qb->createNamedParameter(TreeMapper::TYPE_BOOKMARK)))  // Only bookmarks (not folders)
			->executeQuery()
			->fetchAll(\PDO::FETCH_COLUMN);

		// Insert each folder assignment into the primary bookmark (skip if already exists)
		foreach ($secondaryFolders as $parentFolderId) {
			// Check if the folder assignment already exists for the primary bookmark
			$exists = $qb->select('id')
				->from('bookmarks_tree')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($primaryId, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('parent_folder', $qb->createNamedParameter($parentFolderId, IQueryBuilder::PARAM_INT)))
				->executeQuery()
				->fetchOne();

			if (!$exists) {
				// Get the last index value for the parent_folder
				$lastIndex = $qb->select($qb->func()->max('index'))
					->from('bookmarks_tree')
					->where($qb->expr()->eq('parent_folder', $qb->createNamedParameter($parentFolderId, IQueryBuilder::PARAM_INT)))
					->executeQuery()
					->fetchOne();

				$nextIndex = ($lastIndex !== null && $lastIndex !== false) ? $lastIndex + 1 : 0;

				// Insert the folder assignment with the last index
				$insertQb = $this->db->getQueryBuilder();
				$insertQb->insert('bookmarks_tree')
					->values([
						'id' => $insertQb->createNamedParameter($primaryId, IQueryBuilder::PARAM_INT),
						'parent_folder' => $insertQb->createNamedParameter($parentFolderId, IQueryBuilder::PARAM_INT),
						'type' => $insertQb->createNamedParameter(TreeMapper::TYPE_BOOKMARK),  // Bookmark (not folder)
						'index' => $insertQb->createNamedParameter($nextIndex, IQueryBuilder::PARAM_INT),
					])
					->executeStatement();
			}
		}
	}

	/**
	 * Merge tags from a secondary bookmark into the primary bookmark.
	 * Checks for existing tags first to avoid constraint violations.
	 *
	 * @param int $primaryId - ID of the primary bookmark.
	 * @param int $secondaryId - ID of the secondary bookmark to merge.
	 */
	public function mergeTags(int $primaryId, int $secondaryId): void {
		$qb = $this->db->getQueryBuilder();

		// Fetch all tags from the secondary bookmark
		$secondaryTags = $qb->select('tag')
			->from('bookmarks_tags')
			->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($secondaryId)))
			->executeQuery()
			->fetchAll(\PDO::FETCH_COLUMN);

		// Insert each tag into the primary bookmark (skip if already exists)
		foreach ($secondaryTags as $tag) {
			// Check if the tag already exists for the primary bookmark
			$exists = $qb->select('bookmark_id')
				->from('bookmarks_tags')
				->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($primaryId)))
				->andWhere($qb->expr()->eq('tag', $qb->createNamedParameter($tag)))
				->executeQuery()
				->fetchOne();

			if (!$exists) {
				// Insert the tag (no conflict possible)
				$insertQb = $this->db->getQueryBuilder();
				$insertQb->insert('bookmarks_tags')
					->values([
						'bookmark_id' => $insertQb->createNamedParameter($primaryId),
						'tag' => $insertQb->createNamedParameter($tag),
					])
					->executeStatement();
			}
		}
	}

	/**
	 * Concatenate descriptions from secondary bookmarks to the primary bookmark.
	 */
	public function mergeDescriptions(int $primaryId, string $secondaryDescription): void {
		$qb = $this->db->getQueryBuilder();

		// Fetch the primary bookmark's description
		$primaryDesc = $qb->select('description')
			->from('bookmarks')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($primaryId, IQueryBuilder::PARAM_INT)))
			->executeQuery()
			->fetchOne();

		// Update the primary description (append secondary description if different)
		if (($primaryDesc !== '' || $secondaryDescription !== '') && $primaryDesc !== $secondaryDescription) {
			$newDesc = $primaryDesc . "\n" . $secondaryDescription;
			$qb->update('bookmarks')
				->set('description', $qb->createNamedParameter($newDesc))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($primaryId, IQueryBuilder::PARAM_INT)))
				->executeStatement();
		}
	}

	/**
	 * Delete a bookmark and all its associated data (tags, folder entries).
	 *
	 * @param int $bookmarkId - ID of the bookmark to delete.
	 */
	public function deleteBookmark(int $bookmarkId): void {
		$qb = $this->db->getQueryBuilder();

		// Start a transaction to ensure atomicity
		$this->db->beginTransaction();

		try {
			// Delete tag assignments for the bookmark
			$qb->delete('bookmarks_tags')
				->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($bookmarkId)))
				->executeStatement();

			// Delete folder entries for the bookmark from bookmarks_tree
			$qb->delete('bookmarks_tree')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($bookmarkId)))
				->executeStatement();

			// Delete the bookmark itself
			$qb->delete('bookmarks')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($bookmarkId)))
				->executeStatement();

			// Commit the transaction
			$this->db->commit();
		} catch (\Exception $e) {
			// Roll back on error
			$this->db->rollBack();
			throw $e;
		}
	}
	/**
	 * Fetch duplicate bookmarks (same URL, same user).
	 * Returns an array of arrays, where each sub-array contains bookmark IDs sharing a URL.
	 */
	public function findDuplicates(): array {
		$duplicates = [];

		// Step 1: Find all duplicate (url, user_id) pairs
		$duplicateQb = $this->db->getQueryBuilder();
		$duplicateQb->select('url', 'user_id')
			->selectAlias($duplicateQb->func()->count('*'), 'count')
			->from('bookmarks')
			->groupBy('url', 'user_id')
			->having($duplicateQb->expr()->gt('count', $duplicateQb->createNamedParameter(1)));

		$duplicatePairs = $duplicateQb->executeQuery()->fetchAll();

		// Step 2: For each duplicate pair, fetch all matching bookmarks
		foreach ($duplicatePairs as $pair) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'url', 'user_id', 'title', 'description')
				->from('bookmarks')
				->where($qb->expr()->eq('url', $qb->createNamedParameter($pair['url'])))
				->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($pair['user_id'])))
				->orderBy('id');

			$result = $qb->executeQuery();

			$key = $pair['user_id'] . '|' . $pair['url'];
			while ($row = $result->fetch()) {
				if (!isset($duplicates[$key])) {
					$duplicates[$key] = [];
				}
				$duplicates[$key][] = [
					'id' => $row['id'],
					'title' => $row['title'],
					'description' => $row['description'],
				];
			}
		}

		return $duplicates;
	}

}
