<?php

/**
 * @copyright Copyright (c) 2016 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\Bookmarks\Tests;

use PHPUnit\Framework;


class TestCase extends Framework\TestCase {
	protected function cleanUp(): void {
		parent::setUp();
		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks');
		$query->execute();
		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_tags');
		$query->execute();
		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_folders');
		$query->execute();
		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_root_folders');
		$query->execute();
		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_folders_public');
		$query->execute();
		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_tree');
		$query->execute();
		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_shares');
		$query->execute();
		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_shared_folders');
		$query->execute();
	}
}
