<?php


namespace OCA\Bookmarks\Service;


use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\TagMapper;
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
	 * ImportService constructor.
	 *
	 * @param BookmarkMapper $bookmarkMapper
	 * @param FolderMapper $folderMapper
	 * @param TagMapper $tagMapper
	 */
	public function __construct(BookmarkMapper $bookmarkMapper, FolderMapper $folderMapper, TagMapper $tagMapper) {
		$this->bookmarkMapper = $bookmarkMapper;
		$this->folderMapper = $folderMapper;
		$this->tagMapper = $tagMapper;
	}

	/**
	 * @param int $userId
	 * @param int $folderId
	 * @return string
	 * @throws UnauthorizedAccessError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function exportFolder($userId, int $folderId = -1): string {
		$file = "<!DOCTYPE NETSCAPE-Bookmark-file-1>
<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=UTF-8\">
<TITLE>Bookmarks</TITLE>";

		$file .= $this->serializeFolder($userId, $folderId);

		return $file;
	}

	/**
	 * @param int $userId
	 * @param int $id
	 * @return string
	 * @throws UnauthorizedAccessError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	protected function serializeFolder($userId, int $id): string {
		if ($id !== -1) {
			$folder = $this->folderMapper->find($id);
			if ($folder->getUserId() !== $userId) {
				throw new UnauthorizedAccessError('Insufficient permissions for folder '.$id);
			}
			$output = '<DT><h3>' . htmlspecialchars($folder->getTitle()) . '</h3>' . "\n"
				. '<DL><p>';
		} else {
			$output = '<H1>Bookmarks</h1>' . "\n"
				. '<DL><p>';
		}

		$childBookmarks = $id !== -1 ? $this->bookmarkMapper->findByFolder($id) : $this->bookmarkMapper->findByRootFolder($userId);
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
			if (strlen($description) > 0) {
				$output .= '<DD>' . $description . '</DD>';
			}
			$output .= "\n";
		}

		$childFolders = $this->folderMapper->findByParentFolder($id);
		foreach ($childFolders as $childFolder) {
			$output .= $this->serializeFolder($userId, $childFolder->getId());
		}

		$output .= '</p></DL>';
		return $output;
	}

}
