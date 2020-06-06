<?php
namespace OCA\Bookmarks\Events;

use OCP\EventDispatcher\Event;

abstract class ChangeEvent extends Event {

	/**
	 * @var string
	 */
	private $type;
	/**
	 * @var int
	 */
	private $id;

	public function __construct(string $type, int $id) {
		parent::__construct();
		$this->type = $type;
		$this->id = $id;
	}

	/**
	 * @return string
	 */
	public function getType(): string {
		return $this->type;
	}

	/**
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
	}
}
