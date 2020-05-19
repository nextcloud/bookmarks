<?php

/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Stefan Klemm <mail@stefan-klemm.de>
 * @copyright Stefan Klemm 2014
 */

namespace OCA\Bookmarks\Controller;

use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\PublicFolder;
use OCA\Bookmarks\Db\PublicFolderMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;

class WebViewController extends Controller {

	/** @var string */
	private $userId;

	/**
	 * @var IL10N
	 */
	private $l;

	/**
	 * @var PublicFolderMapper
	 */
	private $publicFolderMapper;

	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var FolderMapper
	 */
	private $folderMapper;


	/**
	 * WebViewController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param $userId
	 * @param IL10N $l
	 * @param PublicFolderMapper $publicFolderMapper
	 * @param IUserManager $userManager
	 * @param FolderMapper $folderMapper
	 */
	public function __construct($appName, $request, $userId, IL10N $l, PublicFolderMapper $publicFolderMapper, IUserManager $userManager, \OCA\Bookmarks\Db\FolderMapper $folderMapper) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->l = $l;
		$this->publicFolderMapper = $publicFolderMapper;
		$this->userManager = $userManager;
		$this->folderMapper = $folderMapper;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index() {
		return new TemplateResponse($this->appName, 'main', []);
	}

	/**
	 * @param string $token
	 * @return Response
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function link(string $token) {
		$title = 'No title found';
		$userName = 'Unknown';
		try {
			/**
			 * @var $publicFolder PublicFolder
			 */
			$publicFolder = $this->publicFolderMapper->find($token);
			/**
			 * @var $folder Folder
			 */
			$folder = $this->folderMapper->find($publicFolder->getFolderId());
			$title = $folder->getTitle();
			$user = $this->userManager->get($folder->getUserId());
			if ($user !== null) {
				$userName = $user->getDisplayName();
			}
		} catch (DoesNotExistException $e) {
			return new NotFoundResponse();
		} catch (MultipleObjectsReturnedException $e) {
			return new NotFoundResponse();
		}

		$res = new PublicTemplateResponse($this->appName, 'main', []);
		$res->setHeaderTitle($title);
		$res->setHeaderDetails($this->l->t('Bookmarks shared by %s', [$userName]));
		$res->setFooterVisible(false);
		return $res;
	}
}
