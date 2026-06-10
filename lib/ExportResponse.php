<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks;

use OC\HintException;
use OCA\Theming\ThemingDefaults;
use OCP\AppFramework\Http\Response;
use OCP\IDateTimeFormatter;
use OCP\IUserSession;

/**
 * @psalm-template S of int
 * @psalm-template H of array<string, mixed>
 * @extends Response<S,H>
 */
class ExportResponse extends Response {
	private $returnstring;

	public function __construct($returnstring) {
		parent::__construct();
		$dateTime = \OCP\Server::get(IDateTimeFormatter::class);
		$themingDefaults = \OCP\Server::get(ThemingDefaults::class);
		$productName = $themingDefaults->getName();
		$userName = null;

		$user = \OCP\Server::get(IUserSession::class)->getUser();
		if ($user !== null) {
			$userName = $user->getDisplayName();
		}

		$export_name = '"' . $productName . ' Bookmarks' . ($userName ? ' (' . $userName . ') ' : '') . '(' . $dateTime->formatDate(time()) . ').html"';
		$this->addHeader('Cache-Control', 'private');
		$this->addHeader('Content-Type', 'application/stream');
		$this->addHeader('Content-Length', '' . strlen($returnstring));
		$this->addHeader('Content-Disposition', 'attachment; filename=' . $export_name);
		$this->returnstring = $returnstring;
	}

	public function render() {
		return $this->returnstring;
	}
}
