<?php


namespace OCA\Bookmarks\Activity;

use OCP\Activity\IFilter;

class Filter implements IFilter {
	/**
	 * @var \OCP\IL10N
	 */
	private $l;
	/**
	 * @var \OCP\IURLGenerator
	 */
	private $urlGenerator;

	public function __construct(\OCP\IL10N $l, \OCP\IURLGenerator $urlGenerator) {
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
