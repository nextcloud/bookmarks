<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
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
	public const THOUSAND_YEARS = 60 * 60 * 24 * 365 * 1000;

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
		return $doctype === self::DOCTYPE;
	}

	/**
	 * Parses a Netscape Bookmark File Format HTML string to a PHP value.
	 *
	 * @param string $input A Netscape Bookmark File Format HTML string
	 * @param bool $ignorePersonalToolbarFolder If we should ignore the personal toolbar bookmark folder
	 * @param bool $useDateTimeObjects If we should return \DateTime objects
	 *
	 * @return mixed A PHP value
	 *
	 * @throws HtmlParseError
	 */
	public function parse($input, $ignorePersonalToolbarFolder = true, $useDateTimeObjects = true) {
		$document = new DOMDocument();
		$document->preserveWhiteSpace = false;
		if (empty($input)) {
			throw new HtmlParseError("The input shouldn't be empty");
		}
		if ($document->loadHTML($input, LIBXML_PARSEHUGE) === false) {
			throw new HtmlParseError('The HTML value does not appear to be valid Netscape Bookmark File Format HTML.');
		}
		$this->xpath = new DOMXPath($document);
		$this->ignorePersonalToolbarFolder = $ignorePersonalToolbarFolder;
		$this->useDateTimeObjects = $useDateTimeObjects;

		// set root folder
		$this->currentFolder = ['bookmarks' => [], 'children' => []];
		$this->folderDepth[] = & $this->currentFolder;

		$this->traverse();
		return empty($this->bookmarks) ? null : $this->bookmarks;
	}

	private function traverse(?DOMNode $node = null): void {
		if ($node !== null) {
			$entries = $node->childNodes;
		} else {
			$query = './*';
			$entries = $this->xpath->query($query, null);
		}
		if (!$entries) {
			return;
		}

		foreach ($entries as $entry) {
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
			foreach ($node->attributes as $attribute) {
				$attributes[strtolower($attribute->nodeName)] = $attribute->nodeValue;
			}
		}
		$lastModified = null;
		if (isset($attributes['time_added'])) {
			$attributes['add_date'] = $attributes['time_added'];
		}
		if ($this->useDateTimeObjects) {
			if (isset($attributes['add_date'])) {
				$added = new DateTime();
				if ((int)$attributes['add_date'] > self::THOUSAND_YEARS) {
					// Google exports dates in miliseconds. This way we only lose the first year of UNIX Epoch.
					// This is invalid once we hit 2970. So, quite a long time.
					$added->setTimestamp(((int)($attributes['add_date']) / 1000));
				} else {
					$added->setTimestamp((int)$attributes['add_date']);
				}
				$attributes['add_date'] = $added;
			}
			if (isset($attributes['last_modified'])) {
				$modified = new DateTime();
				$modified->setTimestamp($attributes['last_modified'] instanceof DateTime ? $attributes['last_modified']->getTimestamp() : (int)$attributes['last_modified']);
				$attributes['last_modified'] = $modified;
			}
		} else {
			if (isset($attributes['add_date'])) {
				if ((int)$attributes['add_date'] > self::THOUSAND_YEARS) {
					// Google exports dates in miliseconds. This way we only lose the first year of UNIX Epoch.
					// This is invalid once we hit 2970. So, quite a long time.
					$attributes['add_date'] = ((int)($attributes['add_date']) / 1000);
				} else {
					$attributes['add_date'] = (int)$attributes['add_date'];
				}
			}
			if (isset($attributes['last_modified'])) {
				$attributes['last_modified'] = $attributes['last_modified'] instanceof DateTime ? $attributes['last_modified']->getTimestamp() : (int)$attributes['last_modified'];
				if ((int)$attributes['last_modified'] > self::THOUSAND_YEARS) {
					// Google exports dates in miliseconds. This way we only lose the first year of UNIX Epoch.
					// This is invalid once we hit 2970. So, quite a long time.
					$attributes['last_modified'] = ((int)($attributes['last_modified']) / 1000);
				} else {
					$attributes['last_modified'] = (int)$attributes['last_modified'];
				}
			}
		}
		if (isset($attributes['tags'])) {
			$attributes['tags'] = explode(',', $attributes['tags']);
		}
		return $attributes;
	}
}
