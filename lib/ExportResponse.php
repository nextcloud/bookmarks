<?php

/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks;

use OC;
use OC\HintException;
use OCP\AppFramework\Http\Response;

class ExportResponse extends Response {
	private $returnstring;

	public function __construct($returnstring) {
		parent::__construct();

		$user = OC::$server->getUserSession()->getUser();
		if (is_null($user)) {
			throw new HintException('User not logged in');
		}

		$userName = $user->getDisplayName();
		$productName = OC::$server->getThemingDefaults()->getName();
		$dateTime = OC::$server->getDateTimeFormatter();

		$export_name = '"' . $productName . ' Bookmarks (' . $userName . ') (' . $dateTime->formatDate(time()) . ').html"';
		$this->addHeader("Cache-Control", "private");
		$this->addHeader("Content-Type", " application/stream");
		$this->addHeader("Content-Length", strlen($returnstring));
		$this->addHeader("Content-Disposition", "attachment; filename=" . $export_name);
		$this->returnstring = $returnstring;
	}

	public function render() {
		return $this->returnstring;
	}
}
