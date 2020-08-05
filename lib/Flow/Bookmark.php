<?php


namespace OCA\Bookmarks\Flow;


use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Events\BeforeDeleteEvent;
use OCA\Bookmarks\Events\ChangeEvent;
use OCA\Bookmarks\Events\CreateEvent;
use OCA\Bookmarks\Events\MoveEvent;
use OCA\Bookmarks\Events\UpdateEvent;
use OCA\Bookmarks\Service\Authorizer;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\EventDispatcher\Event;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\WorkflowEngine\EntityContext\IUrl;
use OCP\WorkflowEngine\GenericEntityEvent;
use OCP\WorkflowEngine\IEntity;
use OCP\WorkflowEngine\IRuleMatcher;

class Bookmark implements IEntity, IUrl {
	public const EVENT_DELETE = BeforeDeleteEvent::class;
	public const EVENT_CREATE = CreateEvent::class;
	public const EVENT_MOVE = MoveEvent::class;
	public const EVENT_UPDATE = UpdateEvent::class;
	/**
	 * @var IL10N
	 */
	private $l;
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;
	/**
	 * @var BookmarkMapper
	 */
	private $bookmarkMapper;
	/**
	 * @var \OCA\Bookmarks\Db\Bookmark
	 */
	private $bookmark;
	/**
	 * @var Authorizer
	 */
	private $authorizer;

	/**
	 * Bookmark constructor.
	 *
	 * @param IL10N $l
	 * @param IURLGenerator $urlGenerator
	 * @param BookmarkMapper $bookmarkMapper
	 * @param Authorizer $authorizer
	 */
	public function __construct(IL10N $l, IURLGenerator $urlGenerator, BookmarkMapper $bookmarkMapper, Authorizer $authorizer) {
		$this->l = $l;
		$this->urlGenerator = $urlGenerator;
		$this->bookmarkMapper = $bookmarkMapper;
		$this->authorizer = $authorizer;
	}


	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l->t('Bookmark');
	}

	/**
	 * @inheritDoc
	 */
	public function getIcon(): string {
		return $this->urlGenerator->imagePath('bookmarks', 'bookmarks-black.svg');
	}

	/**
	 * @inheritDoc
	 */
	public function getEvents(): array {
		return [
			new GenericEntityEvent($this->l->t('Bookmark deleted'), self::EVENT_DELETE),
			new GenericEntityEvent($this->l->t('Bookmark created'), self::EVENT_CREATE),
			new GenericEntityEvent($this->l->t('Bookmark moved'), self::EVENT_MOVE),
			new GenericEntityEvent($this->l->t('Bookmark updated'), self::EVENT_UPDATE),
		];
	}

	/**
	 * @inheritDoc
	 */
	public function prepareRuleMatcher(IRuleMatcher $ruleMatcher, string $eventName, Event $event): void {
		if (!$event instanceof ChangeEvent) {
			return;
		}
		if ($event->getType() !== 'bookmark') {
			return;
		}
		try {
			$this->bookmark = $this->bookmarkMapper->find($event->getId());
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return;
		}
		$ruleMatcher->setEntitySubject($this, $this->bookmark);
	}

	/**
	 * @inheritDoc
	 */
	public function isLegitimatedForUserId(string $userId): bool {
		if ($this->bookmark->getUserId() === $userId) {
			return true;
		}
		$permissions = $this->authorizer->getUserPermissionsForBookmark($userId, $this->bookmark->getId());
		if (Authorizer::hasPermission(Authorizer::PERM_READ, $permissions)) {
			return true;
		}
		return false;
	}

	public function getUrl(): string {
		return $this->bookmark->getUrl();
	}
}
