<?php


namespace OCA\Bookmarks\Migration;


use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\Share;
use OCA\Bookmarks\Db\SharedFolder;
use OCA\Bookmarks\Db\SharedFolderMapper;
use OCA\Bookmarks\Db\ShareMapper;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Service\FolderService;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\Share\IShare;

class GroupSharesUpdateRepairStep implements IRepairStep {
	/**
	 * @var IDBConnection
	 */
	private $db;
	/**
	 * @var \OCP\IGroupManager
	 */
	private $groupManager;
	/**
	 * @var FolderService
	 */
	private $folders;
	/**
	 * @var ShareMapper
	 */
	private $shareMapper;
	/**
	 * @var FolderMapper
	 */
	private $folderMapper;
	/**
	 * @var SharedFolderMapper
	 */
	private $sharedFolderMapper;

	public function __construct(IDBConnection $db, \OCP\IGroupManager $groupManager, FolderService $folders, ShareMapper $shareMapper, FolderMapper $folderMapper, SharedFolderMapper $sharedFolderMapper) {
		$this->db = $db;
		$this->groupManager = $groupManager;
		$this->folders = $folders;
		$this->shareMapper = $shareMapper;
		$this->folderMapper = $folderMapper;
		$this->sharedFolderMapper = $sharedFolderMapper;
	}

	/**
	 * Returns the step's name
	 */
	public function getName() {
		return 'Update bookmark group shares';
	}

	/**
	 * @param IOutput $output
	 * @throws \OCA\Bookmarks\Exception\UnsupportedOperation
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function run(IOutput $output) {
		$deleted = 0;
		$added = 0;
		$groups = 0;
		$deletedShares = 0;

		// find group shares
		$qb = $this->db->getQueryBuilder();
		$qb->select('s.id', 's.participant', 's.folder_id', 's.owner')
			->from('bookmarks_shares', 's')
			->where($qb->expr()->eq('s.type', $qb->createPositionalParameter(IShare::TYPE_GROUP)));
		$groupShares = $qb->execute();

		while ($groupShare = $groupShares->fetch()) {

			// find users in share
			$qb = $this->db->getQueryBuilder();
			$qb->select('sf.user_id')
				->from('bookmarks_shared_folders', 'sf')
				->join('sf', 'bookmarks_shared_to_shares', 't', $qb->expr()->eq('t.shared_folder_id', 'sf.id'))
				->where($qb->expr()->eq('t.share_id', $qb->createPositionalParameter($groupShare['id'])));
			$usersInShare = $qb->execute()->fetchAll(\PDO::FETCH_COLUMN);

			$group = $this->groupManager->get($groupShare['participant']);
			if ($group === null) {
				$this->folders->deleteShare($groupShare['id']);
				$deletedShares++;
				continue;
			}
			$usersInGroup = array_filter(array_map(static function ($user) {
				return $user->getUID();
			}, $group->getUsers()), static function ($userId) use ($groupShare) {
				return $userId !== $groupShare['owner'];
			});

			$notInShareUsers = array_diff($usersInGroup, $usersInShare);
			$notInGroupUsers = array_diff($usersInShare, $usersInGroup);

			foreach ($notInGroupUsers as $userId) {
				$this->folders->deleteSharedFolderOrFolder($userId, $groupShare['folder_id']);
				$sharedFolder = $this->sharedFolderMapper->findByFolderAndUser($groupShare['folder_id'], $userId);
				$this->sharedFolderMapper->delete($sharedFolder);
				$deleted++;
			}

			foreach ($notInShareUsers as $userId) {
				/**
				 * @var Share $share
				 */
				$share = $this->shareMapper->find($groupShare['id']);
				/**
				 * @var Folder $folder
				 */
				$folder = $this->folderMapper->find($groupShare['folder_id']);
				$this->folders->addSharedFolder($share, $folder, $userId);

				$added++;
			}
			$groups++;
		}
		$output->info("Removed $deleted users and added $added users to $groups groups");
		$output->info("Removed $deletedShares shares");
	}
}
