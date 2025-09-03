<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks;

use OC;
use OC\HintException;
use OCA\Theming\ThemingDefaults;
use OCP\AppFramework\Http\Response;
use OCP\IDateTimeFormatter;

/**
 * @psalm-template S of int
 * @psalm-template H of array<string, mixed>
 * @extends Response<S,H>
 */
class ExportResponse extends Response {
	private $returnstring;

	public function __construct($returnstring) {
		parent::__construct();

		$user = OC::$server->getUserSession()->getUser();
		if (is_null($user)) {
			throw new HintException('User not logged in');
		}

		$userName = $user->getDisplayName();
		$themingDefaults = \OCP\Server::get(ThemingDefaults::class);
		$dateTime = \OCP\Server::get(IDateTimeFormatter::class);
		$productName = $themingDefaults->getName();

		$export_name = '"' . $productName . ' Bookmarks (' . $userName . ') (' . $dateTime->formatDate(time()) . ').html"';
		$this->addHeader('Cache-Control', 'private');
		$this->addHeader('Content-Type', ' application/stream');
		$this->addHeader('Content-Length', strlen($returnstring));
		$this->addHeader('Content-Disposition', 'attachment; filename=' . $export_name);
		$this->returnstring = $returnstring;
	}

	public function render() {
		return $this->returnstring;
	}
}
