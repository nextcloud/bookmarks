<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Events;

/**
 * Event emitted when a bookmarks entity is manipulated into the DB
 * Not exposed via the activity app
 */
class ManipulateEvent extends ChangeEvent {
	/**
	 * @param string $type
	 * @param int $id
	 * @param list<string>|null $updatedFields the entity fields that changed, null if unknown
	 */
	public function __construct(
		string $type,
		int $id,
		private ?array $updatedFields = null,
	) {
		parent::__construct($type, $id);
	}

	/**
	 * @return list<string>|null the entity fields that changed, null if unknown
	 */
	public function getUpdatedFields(): ?array {
		return $this->updatedFields;
	}
}
