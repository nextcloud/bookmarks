<?php
/*
 * Copyright (c) 2022. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCA\Notes\Service\Note;
use OCA\Notes\Service\NotesService as OriginalNotesService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Collaboration\Resources\IManager;
use OCP\Collaboration\Resources\ResourceException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class NotesService {
	private const REGEX_URL = "%(https?|ftp)://(\S+(:\S*)?@|\d{1,3}(\.\d{1,3}){3}|(([a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)(\.([a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)*(\.[a-z\x{00a1}-\x{ffff}]{2,6}))(:\d+)?([^\s]*)?%ium";
	/**
	 * @var BookmarkService
	 */
	private $bookmarks;
	/**
	 * @var IManager
	 */
	private $resourceManager;

	/**
	 * @var IUserSession
	 */
	private $session;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var FolderService
	 */
	private $folders;
	/**
	 * @var IL10N
	 */
	private $l;

	public function __construct(BookmarkService $bookmarks, IManager $resourceManager, IUserSession $session, IConfig $config, FolderService $folders, IL10N $l) {
		$this->bookmarks = $bookmarks;
		$this->resourceManager = $resourceManager;
		$this->session = $session;
		$this->config = $config;
		$this->folders = $folders;
		$this->l = $l;
	}

	/**
	 * @throws \Exception
	 */
	public function extractBookmarksFromNotes(IUser $user) {
		$notes = $this->getNotes($user);
		$notesBookmarksFolder = $this->getOrCreateNotesBookmarksFolder($user);
		if ($notesBookmarksFolder === null) {
			return;
		}
		foreach ($notes as $note) {
			$noteContent = $note->getContent();
			if (preg_match_all(self::REGEX_URL, $noteContent, $matches) === false) {
				continue;
			}

			foreach ($matches[0] as $url) {
				try {
					$bookmark = $this->bookmarks->findByUrl($user->getUID(), $url);
					$this->bookmarks->addToFolder($notesBookmarksFolder->getId(), $bookmark->getId());
				} catch (UrlParseError $e) {
					continue;
				} catch (DoesNotExistException $e) {
					try {
						$bookmark = $this->bookmarks->create($user->getUID(), $url, null, null, null, [$notesBookmarksFolder->getId()]);
					} catch (AlreadyExistsError|UnsupportedOperation|UrlParseError|UserLimitExceededError|DoesNotExistException|MultipleObjectsReturnedException $e) {
						continue;
					}
				}
				$this->linkBookmarkWithNote($user, $bookmark, $note);
			}
		}
	}

	public function getOrCreateNotesBookmarksFolder(IUser $user) : ?Folder {
		$folderId = $this->config->getAppValue('bookmarks', 'notesIntegration.folderId', '');
		try {
			if ($folderId === '') {
				$folder = $this->folders->create($this->l->t('Links from Notes'), $this->folders->getRootFolder($user->getUID())->getId());
				$this->config->setAppValue('bookmarks', 'notesIntegration.folderId', (string)$folder->getId());
				return $folder;
			} else {
				return $this->folders->findById((int)$folderId);
			}
		} catch (UnsupportedOperation|DoesNotExistException|MultipleObjectsReturnedException $e) {
			return null;
		}
	}

	public function linkBookmarkWithNote(IUser $user, Bookmark $bookmark, Note $note) : void {
		try {
			$this->resourceManager->getResourceForUser('bookmarks', (string)$bookmark->getId(), $user);
			return;
		} catch (ResourceException $e) {
			// noop
		}
		$bookmarkResource = $this->resourceManager->createResource('bookmarks', (string)$bookmark->getId());
		$noteResource = $this->resourceManager->createResource('file', (string)$note->getId());
		$collection = $this->resourceManager->newCollection($note->getTitle());
		$collection->addResource($bookmarkResource);
		$collection->addResource($noteResource);
	}

	/**
	 * @param string $userId
	 * @return Note[]
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function getNotes(IUser $user) : array {
		$this->session->setUser($user); // Needed because Notes loads tags and ITags loads user from session
		return $this->getNotesService()->getAll($user->getUID())['notes'];
	}

	public function isAvailable(): bool {
		return class_exists(OriginalNotesService::class);
	}

	/**
	 * @return OriginalNotesService
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 * @throws \Exception
	 */
	private function getNotesService() : OriginalNotesService {
		if (!$this->isAvailable()) {
			throw new \Exception('Notes App is not available');
		}
		return \OC::$server->get(OriginalNotesService::class);
	}
}
