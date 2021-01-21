<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

/*
 * (c) Pedro Rodrigues <relvas.rodrigues@gmail.com>
 */

namespace OCA\Bookmarks\Service;

use DateTime;
use DOMDocument;
use DOMNode;
use DOMXPath;
use OCA\Bookmarks\Exception\HtmlParseError;
use const LIBXML_PARSEHUGE;

/**
 * Bookmark parser
 *
 * @author Pedro Rodrigues <relvas.rodrigues@gmail.com>
 */
class BookmarksParser {
	/**
	 * Netscape Bookmark File Format DOCTYPE
	 */
	public const DOCTYPE = 'NETSCAPE-Bookmark-file-1';
	/**
	 * DOMXPath
	 *
	 * @var DOMXPath
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
	public static function isValid($doctype): bool {
		return self::DOCTYPE === $doctype;
	}

	/**
	 * Parses a Netscape Bookmark File Format HTML string to a PHP value.
	 *
	 * @param string $input A Netscape Bookmark File Format HTML string
	 * @param bool $ignorePersonalToolbarFolder If we should ignore the personal toolbar bookmark folder
	 * @param bool $includeFolderTags If we should include folter tags
	 * @param bool $useDateTimeObjects If we should return \DateTime objects
	 *
	 * @return mixed  A PHP value
	 *
	 * @throws HtmlParseError
	 */
	public function parse($input, $ignorePersonalToolbarFolder = true, $includeFolderTags = true, $useDateTimeObjects = true) {
		$document = new DOMDocument();
		$document->preserveWhiteSpace = false;
		if (empty($input)) {
			throw new HtmlParseError("The input shouldn't be empty");
		}
		if (false === $document->loadHTML($input, LIBXML_PARSEHUGE)) {
			throw new HtmlParseError('The HTML value does not appear to be valid Netscape Bookmark File Format HTML.');
		}
		$this->xpath = new DOMXPath($document);
		$this->ignorePersonalToolbarFolder = $ignorePersonalToolbarFolder;
		$this->includeFolderTags = $includeFolderTags;
		$this->useDateTimeObjects = $useDateTimeObjects;

		// set root folder
		$this->currentFolder = ['bookmarks' => [], 'children' => []];
		$this->folderDepth[] = & $this->currentFolder;

		$this->traverse();
		return empty($this->bookmarks) ? null : $this->bookmarks;
	}

	/**
	 * Traverses a DOMNode
	 *
	 * @param DOMNode|null $node
	 */
	private function traverse(DOMNode $node = null): void {
		$query = './*';
		$entries = $this->xpath->query($query, $node ?: null);
		if (!$entries) {
			return;
		}
		for ($i = 0; $i < $entries->length; $i++) {
			$entry = $entries->item($i);
			if ($entry === null) {
				continue;
			}
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
	 * @param DOMNode $node
	 */
	private function addFolder(DOMNode $node): void {
		$folder = [
			'title' => $node->textContent,
			'children' => [],
			'bookmarks' => [],
		];
		$folder = array_merge($folder, $this->getAttributes($node));
		if (isset($folder['personal_toolbar_folder']) && $this->ignorePersonalToolbarFolder) {
			return;
		}
		$this->currentFolder['children'][] = & $folder;
		$this->folderDepth[] = & $folder;
		$this->currentFolder = & $folder;
	}

	/**
	 * Close current folder
	 */
	private function closeFolder(): void {
		array_pop($this->folderDepth);
		$this->currentFolder = & $this->folderDepth[count($this->folderDepth) - 1];
	}

	/**
	 * Add a bookmark from a \DOMNode
	 *
	 * @param DOMNode $node
	 */
	private function addBookmark(DOMNode $node): void {
		$bookmark = [
			'title' => $node->textContent,
			'description' => '',
			'tags' => [],
		];
		$bookmark = array_merge($bookmark, $this->getAttributes($node));
		if ($this->includeFolderTags) {
			$tags = $this->getCurrentFolderTags();
			if (!empty($tags)) {
				$bookmark['tags'] = $tags;
			}
		}
		$this->currentFolder['bookmarks'][] = & $bookmark;
		$this->bookmarks[] = & $bookmark;
	}

	/**
	 * Add a bookmark from a \DOMNode
	 *
	 * @param DOMNode $node
	 */
	private function addDescription(DOMNode $node): void {
		$count = count($this->bookmarks);
		if ($count === 0) {
			return;
		}
		$bookmark = & $this->bookmarks[$count - 1];
		$bookmark['description'] = $node->textContent;
	}

	/**
	 * Get attributes of a \DOMNode
	 *
	 * @param DOMNode $node
	 *
	 * @return array
	 *
	 * @psalm-return array<string, mixed>
	 */
	private function getAttributes(DOMNode $node): array {
		$attributes = [];
		if ($node->attributes) {
			$length = $node->attributes->length;
			for ($i = 0; $i < $length; ++$i) {
				$item = $node->attributes->item($i);
				if ($item === null) {
					continue;
				}
				$attributes[strtolower($item->nodeName)] = $item->nodeValue;
			}
		}
		$lastModified = null;
		if (isset($attributes['time_added'])) {
			$attributes['add_date'] = $attributes['time_added'];
		}
		if ($this->useDateTimeObjects) {
			if (isset($attributes['add_date'])) {
				$added = new DateTime();
				$added->setTimestamp((int)$attributes['add_date']);
				$attributes['add_date'] = $added;
			}
			if (isset($attributes['last_modified'])) {
				$modified = new DateTime();
				$modified->setTimestamp((int)$attributes['last_modified']);
				$attributes['last_modified'] = $modified;
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
	private function getCurrentFolderTags(): array {
		$tags = [];
		array_walk_recursive($this->currentFolder, static function ($tag, $key) use (&$tags) {
			if ('name' === $key) {
				$tags[] = $tag;
			}
		});
		return $tags;
	}
}
