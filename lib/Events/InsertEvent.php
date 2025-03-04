<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Events;

/**
 * Event emitted when a bookmarks entity is inserted into the DB
 * Not exposed via the activity app
 */
class InsertEvent extends ChangeEvent {
}
