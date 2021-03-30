<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Dashboard;

use OCP\Dashboard\IWidget;
use OCP\IL10N;
use OCP\Util;

class Frequent implements IWidget {
	/**
	 * @var IL10N
	 */
	private $l;
	/**
	 * @var \OCP\IURLGenerator
	 */
	private $url;

	/**
	 * Widget constructor.
	 *
	 * @param IL10N $l
	 * @param \OCP\IURLGenerator $url
	 */
	public function __construct(IL10N $l, \OCP\IURLGenerator $url) {
		$this->l = $l;
		$this->url = $url;
	}


	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'bookmarks.frequent';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l->t('Frequent bookmarks');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 50;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconClass(): string {
		return 'icon-favorite';
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): ?string {
		return $this->url->linkToRouteAbsolute('bookmarks.web_view.index');
	}

	/**
	 * @inheritDoc
	 */
	public function load(): void {
		Util::addScript('bookmarks', 'bookmarks-dashboard');
	}
}
