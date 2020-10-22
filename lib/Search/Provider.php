<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Search;

use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\QueryParameters;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;

class Provider implements IProvider {

	/**
	 * @var IL10N
	 */
	private $l;

	/**
	 * @var BookmarkMapper
	 */
	private $bookmarkMapper;

	/**
	 * @var IURLGenerator
	 */
	private $url;

	public function __construct(IL10N $l, BookmarkMapper $bookmarkMapper, IURLGenerator $url) {
		$this->l = $l;
		$this->bookmarkMapper = $bookmarkMapper;
		$this->url = $url;
	}

	public function getId(): string {
		return 'bookmarks';
	}

	public function getName(): string {
		return $this->l->t('Bookmarks');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if ($route === 'bookmarks.WebView.index') {
			return -1;
		}
		return 20;
	}

	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$params = new QueryParameters();
		$params->setLimit($query->getLimit());
		$params->setOffset($query->getCursor() ?? 0);
		$params->setSearch(explode(' ', $query->getTerm()));
		$bookmarks = $this->bookmarkMapper->findAll($user->getUID(), $params);

		$results = array_map(function (Bookmark $bookmark) {
			$favicon = $this->url->linkToRouteAbsolute('bookmarks.internal_bookmark.get_bookmark_favicon', ['id' => $bookmark->getId()]);
			$resourceUrl = $this->url->linkToRouteAbsolute('bookmarks.web_view.indexbookmark', ['bookmark' => $bookmark->getId()]);
			return new SearchResultEntry($favicon, $bookmark->getTitle(), $bookmark->getUrl(), $resourceUrl);
		}, $bookmarks);

		return SearchResult::paginated($this->getName(), $results, $params->getLimit()+$params->getOffset());
	}
}
