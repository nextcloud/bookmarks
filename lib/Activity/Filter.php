<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Activity;

use OCP\Activity\IFilter;
use OCP\IL10N;
use OCP\IURLGenerator;

class Filter implements IFilter {
	/**
	 * @var IL10N
	 */
	private $l;
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;

	public function __construct(IL10N $l, IURLGenerator $urlGenerator) {
		$this->l = $l;
		$this->urlGenerator = $urlGenerator;
	}


	/**
	 * @inheritDoc
	 */
	public function getIdentifier() {
		return 'bookmarks';
	}

	/**
	 * @inheritDoc
	 */
	public function getName() {
		return $this->l->t('Bookmarks');
	}

	/**
	 * @inheritDoc
	 */
	public function getPriority() {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIcon() {
		return $this->urlGenerator->imagePath('bookmarks', 'bookmarks-black.svg');
	}

	/**
	 * @inheritDoc
	 */
	public function filterTypes(array $types) {
		return $types;
	}

	/**
	 * @inheritDoc
	 */
	public function allowedApps() {
		return ['bookmarks'];
	}
}
