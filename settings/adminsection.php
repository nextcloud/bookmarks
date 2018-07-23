<?php
namespace OCA\Bookmarks\Settings;

use OCP\IL10N;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {

		/** @var IL10N */
	private $l;

	public function __construct(IL10N $l) {
		$this->l = $l;
	}

	/**
	 * returns the ID of the section. It is supposed to be a lower case string
	 *
	 * @returns string
	 */
	public function getID() {
		return 'bookmarks';
	}

	/**
	 * returns the translated name as it should be displayed, e.g. 'LDAP / AD
	 * integration'. Use the L10N service to translate it.
	 *
	 * @return string
	 */
	public function getName() {
		return $this->l->t('Bookmarks');
	}

	public function getIcon() {
		return '/apps/bookmarks/img/bookmarks-black.svg';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the settings navigation. The sections are arranged in ascending order of
	 * the priority values. It is required to return a value between 0 and 99.
	 */
	public function getPriority() {
		return 80;
	}
}
