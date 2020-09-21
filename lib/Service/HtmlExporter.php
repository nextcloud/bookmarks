<?php


namespace OCA\Bookmarks\Service;

use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\TagMapper;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Exception\UnauthorizedAccessError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Util;

/**
 * Class HtmlExporter
 *
 * @package OCA\Bookmarks\Service
 */
class HtmlExporter {

	/**
	 * @var BookmarkMapper
	 */
	protected $bookmarkMapper;

	/**
	 * @var FolderMapper
	 */
	protected $folderMapper;

	/**
	 * @var TagMapper
	 */
	protected $tagMapper;
	/**
	 * @var TreeMapper
	 */
	private $treeMapper;

	/**
	 * ImportService constructor.
	 *
	 * @param BookmarkMapper $bookmarkMapper
	 * @param FolderMapper $folderMapper
	 * @param TagMapper $tagMapper
	 * @param TreeMapper $treeMapper
	 */
	public function __construct(BookmarkMapper $bookmarkMapper, FolderMapper $folderMapper, TagMapper $tagMapper, TreeMapper $treeMapper) {
		$this->bookmarkMapper = $bookmarkMapper;
		$this->folderMapper = $folderMapper;
		$this->tagMapper = $tagMapper;
		$this->treeMapper = $treeMapper;
	}

	/**
	 * @param int $userId
	 * @param int $folderId
	 * @return string
	 * @throws UnauthorizedAccessError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function exportFolder($userId, int $folderId): string {
		$file = '<!DOCTYPE NETSCAPE-Bookmark-file-1>
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
<TITLE>Bookmarks</TITLE>';

		$file .= $this->serializeFolder($userId, $folderId, true);

		return $file;
	}

	/**
	 * @param int $userId
	 * @param int $id
	 * @param bool $onlyContent
	 * @return string
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnauthorizedAccessError
	 */
	protected function serializeFolder($userId, int $id, bool $onlyContent = false): string {
		if ($onlyContent) {
			$output = '';
		} else {
			$folder = $this->folderMapper->find($id);
			$output = '<DT><h3>' . htmlspecialchars($folder->getTitle()) . '</h3>' . "\n"
				. '<DL><p>';
		}
		$childBookmarks = $this->treeMapper->findChildren(TreeMapper::TYPE_BOOKMARK, $id);
		foreach ($childBookmarks as $bookmark) {
			// discards records with no URL. This should not happen but
			// a database could have old entries
			if ($bookmark->getUrl() === '') {
				continue;
			}

			$tags = $this->tagMapper->findByBookmark($bookmark->getId());

			$tags = Util::sanitizeHTML(implode(',', $tags));
			$title = trim($bookmark->getTitle());
			$url = Util::sanitizeHTML($bookmark->getUrl());
			if ($title === '') {
				$title = $url;
			}
			$title = Util::sanitizeHTML($title);
			$description = Util::sanitizeHTML($bookmark->getDescription());

			$output .= '<DT><A HREF="' . $url . '" TAGS="' . $tags . '" ADD_DATE="' . $bookmark->getAdded() . '">' . $title . '</A>' . "\n";
			if ($description !== '') {
				$output .= '<DD>' . $description . '</DD>';
			}
			$output .= "\n";
		}

		$childFolders = $this->treeMapper->findChildren(TreeMapper::TYPE_FOLDER, $id);
		foreach ($childFolders as $childFolder) {
			$output .= $this->serializeFolder($userId, $childFolder->getId());
		}

		$output .= '</p></DL>';
		return $output;
	}
}
