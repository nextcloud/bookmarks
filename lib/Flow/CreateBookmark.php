<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Flow;

use OC;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCA\Bookmarks\Service\BookmarkService;
use OCA\WorkflowEngine\Entity\File;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Lock\LockedException;
use OCP\Util;
use OCP\WorkflowEngine\EntityContext\IUrl;
use OCP\WorkflowEngine\Events\RegisterEntitiesEvent;
use OCP\WorkflowEngine\Events\RegisterOperationsEvent;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IOperation;
use OCP\WorkflowEngine\IRuleMatcher;
use Psr\Log\LoggerInterface;

class CreateBookmark implements IOperation {
	private const REGEX_URL = "%(https?|ftp)://(\S+(:\S*)?@|\d{1,3}(\.\d{1,3}){3}|(([a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)(\.([a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)*(\.[a-z\x{00a1}-\x{ffff}]{2,6}))(:\d+)?([^\s]*)?%ium";

	/**
	 * @var IL10N
	 */
	private $l;
	/**
	 * @var BookmarkService
	 */
	private $bookmarks;
	/**
	 * @var IUserSession
	 */
	private $session;
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;
	/**
	 * @var LoggerInterface
	 */
	private $logger;
	private IRootFolder $rootFolder;

	public function __construct(IL10N $l, BookmarkService $bookmarks, IUserSession $session, IURLGenerator $urlGenerator, LoggerInterface $logger, IRootFolder $rootFolder) {
		$this->l = $l;
		$this->bookmarks = $bookmarks;
		$this->session = $session;
		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
		$this->rootFolder = $rootFolder;
	}

	public static function register(IEventDispatcher $dispatcher): void {
		if (interface_exists(IManager::class)) {
			$dispatcher->addListener(RegisterOperationsEvent::class, static function (RegisterOperationsEvent $event) {
				$operation = OC::$server->query(CreateBookmark::class);
				$event->registerOperation($operation);
				Util::addScript('bookmarks', 'flow');
			});
			$dispatcher->addListener(RegisterEntitiesEvent::class, static function (RegisterEntitiesEvent $event) {
				$entity = OC::$server->query(Bookmark::class);
				$event->registerEntity($entity);
				Util::addScript('bookmarks', 'flow');
			});
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getDisplayName(): string {
		return $this->l->t('Create bookmark');
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string {
		return $this->l->t('Takes a link and adds it to your collection of bookmarks.');
	}

	/**
	 * @inheritDoc
	 */
	public function getIcon(): string {
		return $this->urlGenerator->imagePath('bookmarks', 'bookmarks.svg');
	}

	/**
	 * @inheritDoc
	 */
	public function isAvailableForScope(int $scope): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function validateOperation(string $name, array $checks, string $operation): void {
		// TODO: Implement validateOperation() method.
	}

	/**
	 * @inheritDoc
	 */
	public function onEvent(string $eventName, Event $event, IRuleMatcher $ruleMatcher): void {
		$flows = $ruleMatcher->getFlows(true);
		foreach ($flows as $flow) {
			$user = $this->session->getUser();
			if ($user === null) {
				continue;
			}

			$entity = $ruleMatcher->getEntity();

			if ($entity instanceof File) {
				$node = current($this->rootFolder->getById($entity->exportContextIDs()['nodeId']));
				if ($node !== null) {
					$this->handleFile($node, $user);
				}
				continue;
			}

			if ($entity instanceof IUrl) {
				$this->bookmarks->create($user->getUID(), $entity->getUrl());
				continue;
			}
		}
	}

	private function handleFile(Node $node, IUser $user): void {
		if (!$node instanceof \OCP\Files\File) {
			return;
		}

		try {
			$text = $node->getContent();
		} catch (NotPermittedException|LockedException $e) {
			return;
		}

		if (preg_match_all(self::REGEX_URL, $text, $matches) === false) {
			return;
		}

		foreach ($matches[0] as $url) {
			try {
				$this->bookmarks->create($user->getUID(), $url);
			} catch (AlreadyExistsError|UnsupportedOperation|UrlParseError|UserLimitExceededError|DoesNotExistException|MultipleObjectsReturnedException $e) {
				return;
			}
		}
	}
}
