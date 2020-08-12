<?php
namespace OCA\Bookmarks\Activity;


use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\TreeMapper;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class Provider implements IProvider {

	/**
	 * @var IFactory
	 */
	private $languageFactory;
	/**
	 * @var IURLGenerator
	 */
	private $url;
	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var IManager
	 */
	private $activityManager;
	/**
	 * @var \OCP\IL10N
	 */
	private $l;
	/**
	 * @var TreeMapper
	 */
	private $treeMapper;

	public function __construct(IFactory $languageFactory, IURLGenerator $url, IUserManager $userManager, IManager $activityManager, TreeMapper $treeMapper) {
		$this->languageFactory = $languageFactory;
		$this->url = $url;
		$this->userManager = $userManager;
		$this->activityManager = $activityManager;
		$this->treeMapper = $treeMapper;
	}

	/**
	 * @inheritDoc
	 */
	public function parse($language, IEvent $event, IEvent $previousEvent = null) {
		if ($event->getApp() !== 'bookmarks') {
			throw new \InvalidArgumentException();
		}

		$this->l = $this->languageFactory->get('bookmarks', $language);

		$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('bookmarks', 'bookmarks-black.svg')));

		$subjectParameters = $event->getSubjectParameters();

		$isSharee = $event->getAffectedUser() === $this->activityManager->getCurrentUserId();
		$sharee = isset($subjectParameters['sharee']) ? $this->userManager->get($subjectParameters['sharee']) : null;
		if ($sharee !== null) {
			$shareeName = $sharee->getDisplayName();
		}else{
			$shareeName = null;
		}

		$isAuthor = $event->getAuthor() === $this->activityManager->getCurrentUserId();
		$author = $this->userManager->get($event->getAuthor());
		if ($author !== null) {
			$authorName = $author->getDisplayName();
		}else{
			$authorName = null;
		}

		switch($event->getSubject()) {
			case 'bookmark_created':
				if ($isAuthor) {
					$event->setParsedSubject($this->l->t('You bookmarked "%s"', [
						$subjectParameters['bookmark']
					]));
				}elseif ($authorName){
					$event->setParsedSubject($this->l->t('%1$s bookmarked "%2$s"', [
						$authorName,
						$subjectParameters['bookmark'],
					]));
				}else {
					$event->setParsedSubject($this->l->t('Someone bookmarked "%s"', [
						$subjectParameters['bookmark']
					]));
				}
				break;
			case 'bookmark_deleted':
				if ($isAuthor) {
					$event->setParsedSubject($this->l->t('You deleted "%s"', [
						$subjectParameters['bookmark']
					]));
				}elseif ($authorName){
					$event->setParsedSubject($this->l->t('%1$s deleted "%1$s"', [
						$authorName,
						$subjectParameters['bookmark'],
					]));
				}else {
					$event->setParsedSubject($this->l->t('Someone deleted "%s"', [
						$subjectParameters['bookmark']
					]));
				}
				break;
			case 'folder_created':
				if ($isAuthor) {
					$event->setParsedSubject($this->l->t('You created folder "%s"', [
						$subjectParameters['folder']
					]));
				}elseif ($authorName){
					$event->setParsedSubject($this->l->t('%1$s created folder "%2$s"', [
						$authorName,
						$subjectParameters['folder']
					]));
				}else {
					$event->setParsedSubject($this->l->t('Someone created folder "%s"', [
						$subjectParameters['folder']
					]));
				}
				break;
			case 'folder_moved':
				if ($isAuthor) {
					$event->setParsedSubject($this->l->t('You moved folder "%s"', [
						$subjectParameters['folder']
					]));
				}elseif ($authorName){
					$event->setParsedSubject($this->l->t('%1$s moved folder "%2$s"', [
						$authorName,
						$subjectParameters['folder']
					]));
				}else {
					$event->setParsedSubject($this->l->t('Someone moved folder "%s"', [
						$subjectParameters['folder']
					]));
				}
				break;
			case 'folder_deleted':
				if ($isAuthor) {
					$event->setParsedSubject($this->l->t('You deleted folder "%s"', [
						$subjectParameters['folder']
					]));
				}elseif ($authorName){
					$event->setParsedSubject($this->l->t('%1$s deleted folder "%2$s"', [
						$authorName,
						$subjectParameters['folder'],
					]));
				}else {
					$event->setParsedSubject($this->l->t('Someone deleted folder "%s"', [
						$subjectParameters['folder']
					]));
				}
				break;
			case 'share_created':
				if ($isAuthor && $shareeName !== null) {
					$event->setParsedSubject($this->l->t('You shared folder "%1$s" with %2$s', [
						$subjectParameters['folder'],
						$shareeName
					]));
				}elseif ($isAuthor){
					$event->setParsedSubject($this->l->t('You shared folder "%s" with someone', [
						$subjectParameters['folder']
					]));
				}elseif ($authorName && $isSharee) {
					$event->setParsedSubject($this->l->t('%1$s shared folder "%2$s" with you', [
						$authorName,
						$subjectParameters['folder'],
					]));
				}elseif ($isSharee) {
					$event->setParsedSubject($this->l->t('Someone shared folder "%s" with you', [
						$subjectParameters['folder']
					]));
				}
				break;
			case 'share_deleted':
				if ($isAuthor && $shareeName) {
					$event->setParsedSubject($this->l->t('You unshared folder "%1$s" with %2$s', [
						$subjectParameters['folder'],
						$shareeName
					]));
				}elseif ($isAuthor){
					$event->setParsedSubject($this->l->t('You unshared folder "%s" with someone', [
						$subjectParameters['folder']
					]));
				}elseif ($authorName && $isSharee) {
					$event->setParsedSubject($this->l->t('%1$s unshared folder "%2$s" with you', [
						$subjectParameters['folder'],
						$authorName
					]));
				}elseif ($isSharee) {
					$event->setParsedSubject($this->l->t('Someone unshared folder "%s" with you', [
						$subjectParameters['folder']
					]));
				}
				break;
			default:
				throw new \InvalidArgumentException();
		}

		if ($event->getObjectType() === TreeMapper::TYPE_FOLDER && !str_contains($event->getSubject(), 'deleted')) {
			$event->setLink($this->url->linkToRouteAbsolute('bookmarks.web_view.indexfolder', ['folder' => $event->getObjectId()]));
		}
		if ($event->getObjectType() === TreeMapper::TYPE_BOOKMARK && !str_contains($event->getSubject(), 'deleted')) {
			/**
			 * @var $folders Folder[]
			 */
			$folders = $this->treeMapper->findParentsOf(TreeMapper::TYPE_BOOKMARK, $event->getObjectId());
			$folders = array_filter($folders, function($folder) {
				return $folder->getUserId() === $this->activityManager->getCurrentUserId();
			});
			if (isset($folders[0])) {
				$event->setLink($this->url->linkToRouteAbsolute('bookmarks.web_view.indexfolder', ['folder' => $folders[0]->getId()]));
			}
		}

		return $event;
	}
}
