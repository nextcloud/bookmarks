<?php
namespace OCA\Bookmarks\Migration;

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\IDBConnection;
use OCP\IConfig;
use OCA\Bookmarks\Controller\Lib\Bookmarks;

class RecreateAllBookmarks implements IRepairStep {
	/** @var IDBConnection */
	protected $db;

	/** @var Bookmarks */
	protected $libbookmarks;

	/** @var IConfig */
	protected $config;

	public function __construct(IDBConnection $db, Bookmarks $libbookmarks, IConfig $config) {
		$this->db = $db;
		$this->libbookmarks = $libbookmarks;
		$this->config = $config;
	}

	/**
	* Returns the step's name
	*/
	public function getName() {
		return 'Recreate all bookmarks to fetch new meta data';
	}

	/**
	* @param IOutput $output
	*/
	public function run(IOutput $output) {
		$bookmarks = $this->getAllBookmarks();
		\OCP\Util::writeLog('bookmarks', 'bookmarks: '.var_export($bookmarks, true), \OCP\Util::INFO);
		//$this->removeAllBookmarks();
		$this->addBookmarks($output, $bookmarks);
	}

	public function getAllBookmarks() {
		$dbType = $this->config->getSystemValue('dbtype', 'sqlite');
		$qb = $this->db->getQueryBuilder();
		$qb
			->select(['id', 'user_id', 'url', 'title', 'description', 'public'])
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tags', 't', $qb->expr()->eq('t.bookmark_id', 'b.id'))
			->groupBy(['id', 'user_id', 'url', 'title', 'description', 'public']);
		if ($dbType == 'pgsql') {
			$qb->selectAlias($qb->createFunction("array_to_string(array_agg(" . $qb->getColumnName('t.tag') . "), ',')"), 'tags');
        }else{
			$qb->selectAlias($qb->createFunction('GROUP_CONCAT(' . $qb->getColumnName('t.tag') . ')'), 'tags');
		}
		return $qb->execute()->fetchAll();
	}

	public function removeAllBookmarks() {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('bookmarks');
		$qb->execute();
		$qb = $this->db->getQueryBuilder();
		$qb->delete('bookmarks_tags');
		$qb->execute();
	}

	public function addBookmarks($output, $bookmarks) {
		$output->info("Recreating bookmarks");
		$output->startProgress(count($bookmarks));
		foreach ($bookmarks as $bookmark) {
			$this->libbookmarks->addBookmark(
				$bookmark['user_id'],
				$bookmark['url'],
				$bookmark['title'],
				explode(',', $bookmark['tags']),
				$bookmark['description'],
				$bookmark['public']
			);
			$output->advance(1);
		}
	}
}
