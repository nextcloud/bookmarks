<?php
/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Marcel Klehr <mklehr@gmx.net>
 * @copyright Marcel Klehr 2017
 */
namespace OCA\Bookmarks\Controller\Lib;

use \OCA\Bookmarks\Appinfo\Application;
use \OCP\Search\PagedProvider;
use OCP\IDBConnection;

class Search extends PagedProvider {
	private $userid;
	private $db;
	private $libBookmarks;

	public function __construct($options) {
		parent::__construct(array_merge([
			self::OPTION_APPS => ['bookmarks'],
			'offset' => 0,
			'sortby' => 'title',
			'filterTagsOnly' => false,
			'limit' => 0,
			'publicOnly' => false,
			'returnedAttrs' => null,
			'conjunction' => "and",
		], $options));
		$app = new Application();
		$server = $app->getContainer()->getServer();
		$this->userid = $server->getUserSession()->getUser()->getUID();
		$this->db = $server->getDatabaseConnection();
		$this->libBookmarks = $server->query(Bookmarks::class);
	}
	
	public function searchPaged($query, $page, $size) {
		$filters = explode(' ', $query);
		$results = $this->libBookmarks->findBookmarks(
			$this->userid,
			($page-1)*$size,
			$this->getOption('sortby'),
			$filters,
			$this->getOption('filterTagsOnly'),
			$size,
			$this->getOption('publicOnly'),
			$this->getOption('returnedAttrs'),
			$this->getOption('conjunction')
		);

		return array_map([$this, 'mapResult'], $results);
	}

	public function mapResult($result) {
		return new SearchResult('bookmarks/'+$result['id'], $result['title'], $result['url']);
	}
}

?>
