<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Events;

class MoveEvent extends ChangeEvent {
	private $oldParent;
	private $newParent;

	/**
	 * MoveEvent constructor.
	 *
	 * @param string $type
	 * @param int $id
	 * @param int|null $oldParent
	 * @param int|null $newParent
	 */
	public function __construct(string $type, int $id, int $oldParent = null, int $newParent = null) {
		parent::__construct($type, $id);
		$this->oldParent = $oldParent;
		$this->newParent = $newParent;
	}

	/**
	 * @return int|null
	 */
	public function getOldParent(): ?int {
		return $this->oldParent;
	}

	/**
	 * @return int|null
	 */
	public function getNewParent(): ?int {
		return $this->newParent;
	}
}
