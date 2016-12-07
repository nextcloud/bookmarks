<?php

/**
 * @copyright Copyright (c) 2016 Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @copyright Copyright (c) 2014 Stefan Klemm <mail@stefan-klemm.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Stefan Klemm <mail@stefan-klemm.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Bookmarks\Controller\Lib;

use OC\HintException;
use OCP\AppFramework\Http\Response;

class ExportResponse extends Response {

	private $returnstring;

	public function __construct($returnstring) {
		$user = \OC::$server->getUserSession()->getUser();
		if(is_null($user)) {
			throw new HintException('User not logged in');
		}

		$userName = $user->getDisplayName();
		$productName = \OC::$server->getThemingDefaults()->getName();

		$export_name = '"' . $productName . ' Bookmarks (' . $userName . ') (' . date('Y-m-d') . ').html"';
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
