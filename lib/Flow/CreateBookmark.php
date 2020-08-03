<?php


namespace OCA\Bookmarks\Flow;


use OCA\Bookmarks\Service\BookmarkService;
use OCA\WorkflowEngine\Entity\File;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Util;
use OCP\WorkflowEngine\EntityContext\IUrl;
use OCP\WorkflowEngine\IOperation;
use OCP\WorkflowEngine\IRuleMatcher;
use OCP\WorkflowEngine\ISpecificOperation;

class CreateBookmark implements IOperation {

	private const REGEX_URL = "%^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@|\d{1,3}(?:\.\d{1,3}){3}|(?:(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)(?:\.(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)*(?:\.[a-z\x{00a1}-\x{ffff}]{2,6}))(?::\d+)?(?:[^\s]*)?$%ium";

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
	 * @var \OCP\IURLGenerator
	 */
	private $urlGenerator;
	/**
	 * @var \OCP\ILogger
	 */
	private $logger;

	public function __construct(IL10N $l, BookmarkService $bookmarks, IUserSession $session, \OCP\IURLGenerator $urlGenerator, \OCP\ILogger $logger) {
		$this->l = $l;
		$this->bookmarks = $bookmarks;
		$this->session = $session;
		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
	}

	public static function register(IEventDispatcher $dispatcher): void {
		if (interface_exists('\OCP\WorkflowEngine\IManager')) {
			@include_once __DIR__ . '/../../vendor/autoload.php';
			$dispatcher->addListener(\OCP\WorkflowEngine\IManager::EVENT_NAME_REG_OPERATION, static function ($event) {
				$operation = \OC::$server->query(CreateBookmark::class);
				$event->getSubject()->registerOperation($operation);
				Util::addScript('bookmarks', 'flow');
			});
			$dispatcher->addListener(\OCP\WorkflowEngine\IManager::EVENT_NAME_REG_ENTITY, static function ($event) {
				$entity = \OC::$server->query(Bookmark::class);
				$event->getSubject()->registerEntity($entity);
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
				$this->handleFile($eventName, $event, $user);
				continue;
			}

			if ($entity instanceof IUrl) {
				$this->bookmarks->create($user->getUID(), $entity->getUrl());
				continue;
			}
		}
	}

	private function handleFile(string $eventName, Event $event, IUser $user): void {
		if($eventName === '\OCP\Files::postRename') {
			/** @var Node $node */
			[, $node] = $event->getSubject();
		} else {
			$node = $event->getSubject();
		}
		/** @var Node $node */

		// '', admin, 'files', 'path/to/file.txt'
		[,$userId, $folder, $path] = explode('/', $node->getPath(), 4);
		if ($folder !== 'files' || $node instanceof Folder) {
			return;
		}

		// on convert text files
		if($node->getMimePart() !== 'text') {
			return;
		}


		try {
			$view = new \OC\Files\View('/' . $userId . '/files');
			$text = $view->file_get_contents($path);
		} catch (\Exception $e) {
			return;
		}

		if(preg_match_all(self::REGEX_URL, $text, $matches) === FALSE) {
			return;
		}

		foreach($matches[0] as $url) {
			$this->bookmarks->create($user->getUID(), $url);
		}
	}
}
