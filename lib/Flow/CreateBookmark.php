<?php


namespace OCA\Bookmarks\Flow;


use OCA\Bookmarks\Service\BookmarkService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\GenericEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\Util;
use OCP\WorkflowEngine\EntityContext\IUrl;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IOperation;
use OCP\WorkflowEngine\IRuleMatcher;
use OCP\WorkflowEngine\ISpecificOperation;

class CreateBookmark implements IOperation {

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

	public function __construct(IL10N $l, BookmarkService $bookmarks, IUserSession $session, \OCP\IURLGenerator $urlGenerator) {
		$this->l = $l;
		$this->bookmarks = $bookmarks;
		$this->session = $session;
		$this->urlGenerator = $urlGenerator;
	}

	public static function register(IEventDispatcher $dispatcher): void {
		if (interface_exists('\OCP\WorkflowEngine\IManager')) {
			$dispatcher->addListener(\OCP\WorkflowEngine\IManager::EVENT_NAME_REG_OPERATION, static function ($event) {
				$operation = \OC::$server->query(CreateBookmark::class);
				$event->getSubject()->registerOperation($operation);
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
		$flows = $ruleMatcher->getFlows(false);
		foreach ($flows as $flow) {
			$entity = $ruleMatcher->getEntity();
			if (!($entity instanceof IUrl)) {
				continue;
			}
			$user = $this->session->getUser();
			if ($user === null) {
				continue;
			}
			$this->bookmarks->create($user->getUID(), $entity->getUrl());

		}
	}
}
