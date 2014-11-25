<?php

namespace OCA\Bookmarks\Controller\Lib;

use OCP\AppFramework\Http\Response;

class ExportResponse extends Response {

	private $returnstring;

	public function __construct($returnstring) {
		$user_name = trim(\OCP\User::getDisplayName()) != '' ?
				\OCP\User::getDisplayName() : \OCP\User::getUser();
		$export_name = '"ownCloud Bookmarks (' . $user_name . ') (' . date('Y-m-d') . ').html"';
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