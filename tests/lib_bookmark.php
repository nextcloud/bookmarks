<?php

OC_App::loadApp('bookmarks');
class Test_LibBookmarks_Bookmarks extends UnitTestCase {

	function testAddBM() {
		$this->assertCount(0, OC_Bookmarks_Bookmarks::findBookmarks(0, 'id', array(), true, -1));
		OC_Bookmarks_Bookmarks::addBookmark(
			'http://owncloud.org', 'Owncloud project', array('oc', 'cloud'), 'An Awesome project');
		$this->assertCount(1, OC_Bookmarks_Bookmarks::findBookmarks(0, 'id', array(), true, -1));
	}

	function testFindTags() {
// 		$uid=uniqid();
		$this->assertEqual(OC_Bookmarks_Bookmarks::findTags(), array());

		OC_Bookmarks_Bookmarks::addBookmark(
			'http://owncloud.org', 'Owncloud project', array('oc', 'cloud'), 'An Awesome project');
		$this->assertEqual(array(0=>array('tag' => 'cloud', 'nbr'=>1), 1=>array('tag' => 'oc', 'nbr'=>1)),
			OC_Bookmarks_Bookmarks::findTags());
	}

  protected function tearDown() {
		$query = OC_DB::prepare('DELETE FROM *PREFIX*bookmarks WHERE `user_id` = \'\' ');
		$query->execute();
  }

}