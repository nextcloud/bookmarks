<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db\Share;
use OCP\Share\IShare;

class ShareTest extends TestCase {
	public static function participantProvider(): array {
		return [
			[IShare::TYPE_USER, 'share_test_nonexistent_user'],
			[IShare::TYPE_GROUP, 'share_test_nonexistent_group'],
			[IShare::TYPE_CIRCLE, 'share_test_nonexistent_circle'],
		];
	}

	/**
	 * @dataProvider participantProvider
	 */
	public function testToArrayWithNonexistentParticipant(int $type, string $participant): void {
		$share = new Share();
		$share->setFolderId(1);
		$share->setOwner('share_test_owner');
		$share->setParticipant($participant);
		$share->setType($type);
		$share->setCanWrite(false);
		$share->setCanShare(false);

		$this->assertEquals($participant, $share->toArray()['participantDisplayName']);
	}
}
