<?php


namespace OCA\Bookmarks\Service;


use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\PublicFolder;
use OCA\Bookmarks\Db\PublicFolderMapper;
use OCA\Bookmarks\Db\Share;
use OCA\Bookmarks\Db\SharedFolder;
use OCA\Bookmarks\Db\SharedFolderMapper;
use OCA\Bookmarks\Db\ShareMapper;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\HtmlParseError;
use OCA\Bookmarks\Exception\UnauthorizedAccessError;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\Share\IShare;

class FolderService {
	/**
	 * @var FolderMapper
	 */
	private $folderMapper;
	/**
	 * @var TreeMapper
	 */
	private $treeMapper;
	/**
	 * @var ShareMapper
	 */
	private $shareMapper;
	/**
	 * @var SharedFolderMapper
	 */
	private $sharedFolderMapper;
	/**
	 * @var PublicFolderMapper
	 */
	private $publicFolderMapper;
	/**
	 * @var IGroupManager
	 */
	private $groupManager;
	/**
	 * @var HtmlImporter
	 */
	private $htmlImporter;
	/**
	 * @var IL10N
	 */
	private $l10n;

	/**
	 * FolderService constructor.
	 *
	 * @param FolderMapper $folderMapper
	 * @param TreeMapper $treeMapper
	 * @param ShareMapper $shareMapper
	 * @param SharedFolderMapper $sharedFolderMapper
	 * @param PublicFolderMapper $publicFolderMapper
	 * @param IGroupManager $groupManager
	 * @param HtmlImporter $htmlImporter
	 * @param IL10N $l10n
	 */
	public function __construct(FolderMapper $folderMapper, TreeMapper $treeMapper, ShareMapper $shareMapper, SharedFolderMapper $sharedFolderMapper, PublicFolderMapper $publicFolderMapper, IGroupManager $groupManager, \OCA\Bookmarks\Service\HtmlImporter $htmlImporter, IL10N $l10n) {
		$this->folderMapper = $folderMapper;
		$this->treeMapper = $treeMapper;
		$this->shareMapper = $shareMapper;
		$this->sharedFolderMapper = $sharedFolderMapper;
		$this->publicFolderMapper = $publicFolderMapper;
		$this->groupManager = $groupManager;
		$this->htmlImporter = $htmlImporter;
		$this->l10n = $l10n;
	}

	/**
	 * @param $userId
	 * @param $title
	 * @param $parent_folder
	 * @return Folder
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws DoesNotExistException
	 */
	public function create($title, $parentFolderId): Folder {
		/**
		 * @var $parentFolder Folder
		 */
		$parentFolder = $this->folderMapper->find($parentFolderId);
		$folder = new Folder();
		$folder->setTitle($title);
		$folder->setUserId($parentFolder->getUserId());

		$this->folderMapper->insert($folder);
		$this->treeMapper->move(TreeMapper::TYPE_FOLDER, $folder->getId(), $parentFolderId);
		return $folder;
	}

	/**
	 * @param Folder $folder
	 * @param $userId
	 * @return Share|null
	 */
	public function findShareByDescendantAndUser(Folder $folder, $userId): ?Share {
		/**
		 * @var $shares Share[]
		 */
		$shares = $this->shareMapper->findByOwnerAndUser($folder->getUserId(), $userId);
		foreach ($shares as $share) {
			if ($share->getFolderId() === $folder->getId() || $this->treeMapper->hasDescendant($share->getFolderId(), TreeMapper::TYPE_FOLDER, $folder->getId())) {
				return $share;
			}
		}
		return null;
	}

	/**
	 * @param $userId
	 * @param $folderId
	 * @return Folder|SharedFolder
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findSharedFolderOrFolder($userId, $folderId) {
		/**
		 * @var $folder Folder
		 */
		$folder = $this->folderMapper->find($folderId);
		if ($userId === null || $userId === $folder->getUserId()) {
			return $folder;
		}

		try {
			/**
			 * @var $sharedFolder SharedFolder
			 */
			$sharedFolder = $this->sharedFolderMapper->findByFolderAndUser($folder->getId(), $userId);
			return $sharedFolder;
		} catch (DoesNotExistException $e) {
			// noop
		}

		return $folder;
	}

	/**
	 * @param $userId
	 * @param $folderId
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function deleteSharedFolderOrFolder($userId, $folderId): void {
		/**
		 * @var $folder Folder
		 */
		$folder = $this->folderMapper->find($folderId);

		if ($userId === null || $userId === $folder->getUserId()) {
			$this->treeMapper->deleteEntry(TreeMapper::TYPE_FOLDER, $folder->getId());
			$shares = $this->shareMapper->findByFolder($folderId);
			foreach ($shares as $share) {
				$this->deleteShare($share->getId());
			}
			$publicFolders = $this->publicFolderMapper->findByFolder($folderId);
			foreach ($publicFolders as $publicFolder) {
				$this->publicFolderMapper->delete($publicFolder);
			}
			return;
		}

		try {
			// folder is shared folder
			/**
			 * @var $sharedFolder SharedFolder
			 */
			$sharedFolder = $this->sharedFolderMapper->findByFolderAndUser($folder->getId(), $userId);
			$this->treeMapper->deleteEntry(TreeMapper::TYPE_SHARE, $sharedFolder->getId());
			return;
		} catch (DoesNotExistException $e) {
			// noop
		}

		// folder is subfolder of share
		$this->treeMapper->deleteEntry(TreeMapper::TYPE_FOLDER, $folder->getId());
	}

	/**
	 * @param $userId
	 * @param $folderId
	 * @param null $title
	 * @param null $parent_folder
	 * @return Folder|SharedFolder
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function updateSharedFolderOrFolder($userId, $folderId, $title = null, $parent_folder = null) {
		/**
		 * @var $folder Folder
		 */
		$folder = $this->folderMapper->find($folderId);

		if ($userId !== null || $userId !== $folder->getUserId()) {
			try {
				// folder is shared folder
				/**
				 * @var $sharedFolder SharedFolder
				 */
				$sharedFolder = $this->sharedFolderMapper->findByFolderAndUser($folder->getId(), $userId);
				if (isset($title)) {
					$sharedFolder->setTitle($title);
					$this->sharedFolderMapper->update($sharedFolder);
				}
				if (isset($parent_folder)) {
					$this->treeMapper->move(TreeMapper::TYPE_SHARE, $sharedFolder->getId(), $parent_folder);
				}
				return $sharedFolder;
			} catch (DoesNotExistException $e) {
				// noop
			}
		}

		if (isset($title)) {
			$folder->setTitle($title);
			$this->folderMapper->update($folder);
		}
		if (isset($parent_folder)) {
			$this->treeMapper->move(TreeMapper::TYPE_FOLDER, $folder->getId(), $parent_folder);
		}
		return $folder;
	}

	/**
	 * @param $folderId
	 * @return string
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function createFolderPublicToken($folderId): string {
		$this->folderMapper->find($folderId);
		try {
			$publicFolder = $this->publicFolderMapper->findByFolder($folderId);
		} catch (DoesNotExistException $e) {
			$publicFolder = new PublicFolder();
			$publicFolder->setFolderId($folderId);
			$this->publicFolderMapper->insert($publicFolder);
		}
		return $publicFolder->getId();
	}

	/**
	 * @param $folderId
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function deleteFolderPublicToken($folderId): void {
		$publicFolder = $this->publicFolderMapper->findByFolder($folderId);
		$this->publicFolderMapper->delete($publicFolder);
	}

	/**
	 * @param $folderId
	 * @param $participant
	 * @param int $type
	 * @param bool $canWrite
	 * @param bool $canShare
	 * @return Share
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function createShare($folderId, $participant, int $type, $canWrite = false, $canShare = false): Share {
		/**
		 * @var $folder Folder
		 */
		$folder = $this->folderMapper->find($folderId);

		$share = new Share();
		$share->setFolderId($folderId);
		$share->setOwner($folder->getUserId());
		$share->setParticipant($participant);
		if ($type !== IShare::TYPE_USER && $type !== IShare::TYPE_GROUP) {
			throw new UnsupportedOperation('Only users and groups are allowed as participants');
		}
		$share->setType($type);
		$share->setCanWrite($canWrite);
		$share->setCanShare($canShare);
		$this->shareMapper->insert($share);

		if ($type === IShare::TYPE_USER) {
			if ($participant === $folder->getUserId()) {
				throw new UnsupportedOperation('Cannot share with oneself');
			}
			$this->addSharedFolder($share, $folder, $participant);
		} else if ($type === IShare::TYPE_GROUP) {
			$group = $this->groupManager->get($participant);
			if ($group === null) {
				throw new DoesNotExistException('Group does not exist');
			}
			$users = $group->getUsers();
			foreach ($users as $user) {
				// If I'm part of the group, don't add it twice
				if ($user->getUID() === $folder->getUserId()) {
					continue;
				}
				// If this folder is already shared with the user, don't add it twice.
				try {
					$this->sharedFolderMapper->findByFolderAndUser($folder->getId(), $user->getUID());
					continue;
				}catch (DoesNotExistException $e) {
					// do nothing
				}

				$this->addSharedFolder($share, $folder, $user->getUID());
			}
		}
		return $share;
	}

	/**
	 * @param Share $share
	 * @param Folder $folder
	 * @param string $userId
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function addSharedFolder(Share $share, Folder $folder, string $userId): void {
		$sharedFolder = new SharedFolder();
		$sharedFolder->setTitle($folder->getTitle());
		$sharedFolder->setFolderId($folder->getId());
		$sharedFolder->setUserId($userId);
		$rootFolder = $this->folderMapper->findRootFolder($userId);
		$sharedFolder = $this->sharedFolderMapper->insert($sharedFolder);
		$this->sharedFolderMapper->mount($sharedFolder->getId(), $share->getId());
		$this->treeMapper->move(TreeMapper::TYPE_SHARE, $sharedFolder->getId(), $rootFolder->getId());
	}

	/**
	 * @param $shareId
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function deleteShare($shareId) {
		$share = $this->shareMapper->find($shareId);
		$sharedFolders = $this->sharedFolderMapper->findByShare($shareId);
		foreach ($sharedFolders as $sharedFolder) {
			$this->sharedFolderMapper->delete($sharedFolder);
			$this->treeMapper->deleteEntry(TreeMapper::TYPE_SHARE, $sharedFolder->getId());
		}
		$this->shareMapper->delete($share);
	}

	/**
	 * @param string $userId
	 * @param $file
	 * @param null $folder
	 * @return array
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws AlreadyExistsError
	 * @throws HtmlParseError
	 * @throws UnauthorizedAccessError
	 * @throws UserLimitExceededError
	 */
	public function importFile(string $userId, $file, $folder): array {
		$importFolderId = $folder;
		return $this->htmlImporter->importFile($userId, $file, $importFolderId);
	}
}
