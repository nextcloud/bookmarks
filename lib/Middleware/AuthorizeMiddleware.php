<?php
namespace OCA\Bookmarks\Middleware;

use OCA\Bookmarks\Controller\BookmarkController;
use OCA\Bookmarks\Controller\FoldersController;
use OCA\Bookmarks\Controller\InternalBookmarkController;
use OCA\Bookmarks\Controller\InternalFoldersController;
use OCA\Bookmarks\Service\Authorizer;
use \OCP\AppFramework\Middleware;
use OCP\IRequest;


class AuthorizeMiddleware extends Middleware {

	private $userId;

	/**
	 * @var IRequest
	 */
	private $request;

	/**
	 * @var Authorizer
	 */
	private $authorizer;

	public function __construct($userId, IRequest $request, Authorizer $authorizer) {
		$this->userId = $userId;
		$this->request = $request;
		$this->authorizer = $authorizer;
	}

	public function beforeController($controller, $methodName) {

		$permissions = Authorizer::PERM_NONE;
		if (!($controller instanceof BookmarkController || $controller instanceof InternalBookmarkController)) {
			$id = $this->request->getParam('id');
			if (isset($id)) {
				$permissions = $this->authorizer->getPermissionsForBookmark($id);
			}else{
				$id = $this->request->getParam('folder');
				if (isset($id)) {
					$permissions = $this->authorizer->getPermissionsForFolder($id);
				}else {
					$ids = $this->request->getParam('folders');
					$permissions = Authorizer::PERM_ALL;
					foreach($ids as $id) {
						$permissions &= $this->authorizer->getPermissionsForFolder($id);
					}
				}
			}
		}
		if ($controller instanceof FoldersController || $controller instanceof InternalFoldersController) {
			$id = $this->request->getParam('folderId');
			$permissions = $this->authorizer->getPermissionsForFolder($id);
		}
		$controller->setPermissions($permissions);
	}
}
