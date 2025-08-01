<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Db;

use Exception;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use RangeException;

/**
 * Class PublicFolderMapper
 *
 * @package OCA\Bookmarks\Db
 * @template-extends QBMapper<PublicFolder>
 */
class PublicFolderMapper extends QBMapper {
	/**
	 * @var IDBConnection
	 */
	protected $db;

	/**
	 * TagMapper constructor.
	 *
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bookmarks_folders_public', PublicFolder::class);
		$this->db = $db;
	}

	/**
	 * @param string $id
	 * @return PublicFolder
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(string $id): PublicFolder {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('bookmarks_folders_public')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		return $this->findEntity($qb);
	}

	/**
	 * @param int $folderId
	 * @return PublicFolder
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByFolder(int $folderId): PublicFolder {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('bookmarks_folders_public')
			->where($qb->expr()->eq('folder_id', $qb->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	/**
	 * @param int $createdAt
	 *
	 * @return PublicFolder[]
	 *
	 * @psalm-return list<PublicFolder>
	 */
	public function findAllCreatedBefore(int $createdAt): array {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('bookmarks_folders_public')
			->where($qb->expr()->lt('created_at', $qb->createNamedParameter($createdAt, IQueryBuilder::PARAM_INT)));

		return $this->findEntities($qb);
	}

	/**
	 * @param Entity $entity
	 * @psalm-param PublicFolder $entity
	 * @return PublicFolder
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function insert(Entity $entity): PublicFolder {
		try {
			while (true) {
				// 63^7 = 3 939 000 000 000 links -- I guess that's enough.
				$entity->setId(self::randomString(7));
				$this->find($entity->getId());
			}
		} catch (DoesNotExistException $e) {
			return parent::insert($entity);
		}
	}

	/**
	 * @param Entity $entity
	 * @psalm-param PublicFolder $entity
	 * @return PublicFolder
	 * @throws MultipleObjectsReturnedException
	 */
	public function insertOrUpdate(Entity $entity): PublicFolder {
		try {
			$this->find($entity->getId());
		} catch (DoesNotExistException $e) {
			return $this->insert($entity);
		}
		return $this->update($entity);
	}

	/**
	 * Generate a random string, using a cryptographically secure
	 * pseudorandom number generator (random_int)
	 *
	 * This function uses type hints now (PHP 7+ only), but it was originally
	 * written for PHP 5 as well.
	 *
	 * For PHP 7, random_int is a PHP core function
	 * For PHP 5.x, depends on https://github.com/paragonie/random_compat
	 *
	 * @param int $length How many characters do we want?
	 * @param string $keyspace A string of all possible characters
	 *                         to select from
	 * @return string
	 * @throws RangeException
	 * @throws Exception
	 */
	public static function randomString(
		int $length = 64,
		string $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
	): string {
		if ($length < 1) {
			throw new RangeException('Length must be a positive integer');
		}
		$pieces = [];
		$max = mb_strlen($keyspace, '8bit') - 1;
		for ($i = 0; $i < $length; ++$i) {
			$pieces [] = $keyspace[random_int(0, $max)];
		}
		return implode('', $pieces);
	}
}
