<?php
/*
 * (c) Pedro Rodrigues <relvas.rodrigues@gmail.com>
 */
namespace OCA\Bookmarks;

/**
 * Bookmark parser
 *
 * @author Pedro Rodrigues <relvas.rodrigues@gmail.com>
 */
class BookmarksParser {
	/**
	 * Netscape Bookmark File Format DOCTYPE
	 */
	const DOCTYPE = 'NETSCAPE-Bookmark-file-1';
	/**
	 * DOMXPath
	 *
	 * @var \DOMXPath
	 */
	private $xpath;
	/**
	 * An array of bookmarks
	 *
	 * @var array
	 */
	public $bookmarks = [];
	/**
	 * The parent bookmark folder
	 *
	 * @var array
	 */
	private $parentFolder = [];
	/**
	 * The current folder
	 *
	 * @var array
	 */
	public $currentFolder = [];
	/**
	 * Folder depth
	 *
	 * @var array
	 */
	private $folderDepth = [];
	/**
	 * If we should use \DateTime objects
	 *
	 * @var bool
	 */
	private $useDateTimeObjects = true;
	/**
	 * If the Personal Toolbar Folder should be ignored
	 *
	 * @var bool
	 */
	private $ignorePersonalToolbarFolder = true;
	/**
	 * If tags should be included
	 *
	 * @var bool
	 */
	private $includeFolderTags = true;
	/**
	 * Constructor
	 *
	 * @param bool $useInternalErrors Use internal errors
	 */
	public function __construct($useInternalErrors = true) {
		libxml_use_internal_errors($useInternalErrors);
	}
	/**
	 * Check if doctype file is valid for parsing
	 *
	 * @param string $doctype Document Doctype
	 *
	 * @return boolean
	 */
	public static function isValid($doctype) {
		return self::DOCTYPE === $doctype;
	}
	/**
	 * Parses a Netscape Bookmark File Format HTML string to a PHP value.
	 *
	 * @param string $input                       A Netscape Bookmark File Format HTML string
	 * @param bool   $ignorePersonalToolbarFolder If we should ignore the personal toolbar bookmark folder
	 * @param bool   $includeFolderTags           If we should include folter tags
	 * @param bool   $useDateTimeObjects          If we should return \DateTime objects
	 *
	 * @return mixed  A PHP value
	 *
	 * @throws ParseException If the HTML is not valid
	 */
	public function parse($input, $ignorePersonalToolbarFolder = true, $includeFolderTags = true, $useDateTimeObjects = true) {
		$document = new \DOMDocument();
		$document->preserveWhiteSpace = false;
		if (empty($input)) {
			throw new \Exception("The input shouldn't be empty");
		}
		if (false === $document->loadHTML($input, \LIBXML_PARSEHUGE)) {
			throw new \Exception('The HTML value does not appear to be valid Netscape Bookmark File Format HTML.');
		}
		$this->xpath = new \DOMXPath($document);
		$this->ignorePersonalToolbarFolder = $ignorePersonalToolbarFolder;
		$this->includeFolderTags = $includeFolderTags;
		$this->useDateTimeObjects = $useDateTimeObjects;

		// set root folder
		$this->currentFolder = ['bookmarks' => [], 'children' => []];
		$this->folderDepth[] =& $this->currentFolder;

		$this->traverse();
		return empty($this->bookmarks) ? null : $this->bookmarks;
	}
	/**
	 * Traverses a DOMNode
	 *
	 * @param \DOMNode $node
	 */
	private function traverse(\DOMNode $node = null) {
		$query = './*';
		$entries = $this->xpath->query($query, $node ?: null);
		foreach ($entries as $entry) {
			switch ($entry->nodeName) {
				case 'dl':
					$this->traverse($entry);
					if (count($this->folderDepth) > 1) {
						$this->closeFolder();
					}
					break;
				case 'a':
					$this->addBookmark($entry);
					break;
				case 'dd':
					$this->addDescription($entry);
					if ($entry->hasChildNodes()) {
						$this->traverse($entry);
					}
					break;
				case 'h3':
					$this->addFolder($entry);
					break;
				default:
					if ($entry->hasChildNodes()) {
						$this->traverse($entry);
					}
			}
		}
	}
	/**
	 * Add a folder from a \DOMNode
	 *
	 * @param \DOMNode $node
	 */
	private function addFolder(\DOMNode $node) {
		$folder = [
			'title' => $node->textContent,
			'children' => [],
			'bookmarks' => []
		];
		$folder = array_merge($folder, $this->getAttributes($node));
		if (isset($folder['personal_toolbar_folder']) && $this->ignorePersonalToolbarFolder) {
			return;
		}
		$this->currentFolder['children'][] =& $folder;
		$this->folderDepth[] =& $folder;
		$this->currentFolder =& $folder;
	}
	/**
	 * Close current folder
	 */
	private function closeFolder() {
		array_pop($this->folderDepth);
		$this->currentFolder =& $this->folderDepth[count($this->folderDepth) - 1];
	}
	/**
	 * Add a bookmark from a \DOMNode
	 *
	 * @param \DOMNode $node
	 */
	private function addBookmark(\DOMNode $node) {
		$bookmark = [
			'title' => $node->textContent,
			'description' => '',
			'tags' => []
		];
		$bookmark = array_merge($bookmark, $this->getAttributes($node));
		if ($this->includeFolderTags) {
			$tags = $this->getCurrentFolderTags($this->currentFolder);
			if (!empty($tags)) {
				$bookmark['tags'] = $tags;
			}
		}
		$this->currentFolder['bookmarks'][] =& $bookmark;
		$this->bookmarks[] = $bookmark;
	}
	/**
	 * Add a bookmark from a \DOMNode
	 *
	 * @param \DOMNode $node
	 */
	private function addDescription(\DOMNode $node) {
		$count = count($this->bookmarks);
		if ($count === 0) {
			return;
		}
		$bookmark = $this->bookmarks[$count-1];
		$bookmark['description'] = $node->textContent;
	}
	/**
	 * Get attributes of a \DOMNode
	 *
	 * @param \DOMNode $node
	 * @return array
	 */
	private function getAttributes(\DOMNode $node) {
		$attributes = [];
		$length = $node->attributes->length;
		for ($i = 0; $i < $length; ++$i) {
			$attributes[strtolower($node->attributes->item($i)->name)] = $node->attributes->item($i)->value;
		}
		if ($this->useDateTimeObjects) {
			if (isset($attributes['add_date'])) {
				$addDate = new \DateTime();
				$addDate->setTimestamp($attributes['add_date']);
				$attributes['add_date'] = $addDate;
			}
			if (isset($attributes['time_added'])) {
				$addDate = new \DateTime();
				$addDate->setTimestamp($attributes['add_date']);
				$attributes['add_date'] = $addDate;
			}
			if (isset($attributes['last_modified'])) {
				$lastModified = new \DateTime();
				$lastModified->setTimestamp($attributes['last_modified']);
				$attributes['last_modified'] = $lastModified;
			}
		}
		if (isset($attributes['tags'])) {
			$attributes['tags'] = explode(',', $attributes['tags']);
		}
		return $attributes;
	}
	/**
	 * Get current folder tags
	 *
	 * @return array
	 */
	private function getCurrentFolderTags() {
		$tags = [];
		array_walk_recursive($this->currentFolder, function ($tag, $key) use (&$tags) {
			if ('name' === $key) {
				$tags[] = $tag;
			}
		});
		return $tags;
	}
}
