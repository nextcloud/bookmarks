<?php

/*
 * Copyright (c) 2026. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Exception;

/**
 * Thrown by a previewer when the service can't create a preview right now but may later,
 * e.g. because a rate limit or the monthly quota of the account was reached.
 */
class PreviewTemporarilyUnavailableError extends Exception {
	/**
	 * @param int $retryAfter Seconds to wait before trying again
	 */
	public function __construct(
		string $message,
		private int $retryAfter,
	) {
		parent::__construct($message);
	}

	public function getRetryAfter(): int {
		return $this->retryAfter;
	}
}
