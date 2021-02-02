<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks;

use InvalidArgumentException;

class QueryParameters {
	public const CONJ_AND = 'and';
	public const CONJ_OR = 'or';

	private $limit = 10;
	private $offset = 0;
	private $sortBy;
	private $conjunction = self::CONJ_AND;
	private $folder;
	private $url;
	private $untagged = false;
	private $unavailable = false;
	private $archived = false;
	private $deleted = false;
	private $search = [];
	private $tags = [];

	/**
	 * @return array
	 */
	public function getSearch(): array {
		return $this->search;
	}

	/**
	 * @param array $search
	 *
	 * @return static
	 */
	public function setSearch(array $search): self {
		$this->search = $search;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getTags(): array {
		return $this->tags;
	}

	/**
	 * @param array $tags
	 *
	 * @return static
	 */
	public function setTags(array $tags): self {
		$this->tags = $tags;
		return $this;
	}


	/**
	 * @return int
	 */
	public function getLimit(): int {
		return $this->limit;
	}

	/**
	 * @param int $limit
	 *
	 * @return static
	 */
	public function setLimit(int $limit): self {
		$this->limit = $limit;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getOffset(): int {
		return $this->offset;
	}

	/**
	 * @param int $offset
	 *
	 * @return static
	 */
	public function setOffset(int $offset): self {
		$this->offset = $offset;
		return $this;
	}

	/**
	 * @param string|null $default
	 * @param array|null $columns
	 * @return string|null
	 */
	public function getSortBy(string $default = null, array $columns = null): ?string {
		if (isset($default) && !isset($this->sortBy)) {
			return $default;
		}
		if (isset($default, $columns) && !in_array($this->sortBy, $columns, true)) {
			return $default;
		}
		return $this->sortBy;
	}

	/**
	 * @param string $sortBy
	 *
	 * @return static
	 */
	public function setSortBy(string $sortBy): self {
		$this->sortBy = $sortBy;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getConjunction(): string {
		return $this->conjunction;
	}

	/**
	 * @param string $conjunction
	 *
	 * @return static
	 */
	public function setConjunction(string $conjunction): self {
		if ($conjunction !== self::CONJ_AND && $conjunction !== self::CONJ_OR) {
			throw new InvalidArgumentException("Conjunction value must be 'and' or 'or'");
		}
		$this->conjunction = $conjunction;
		return $this;
	}

	/**
	 * @return null|int
	 */
	public function getFolder(): ?int {
		return $this->folder;
	}

	/**
	 * @param int $folder
	 *
	 * @return static
	 */
	public function setFolder(int $folder): self {
		$this->folder = $folder;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getUrl(): ?string {
		return $this->url;
	}

	/**
	 * @param string $url
	 *
	 * @return static
	 */
	public function setUrl(string $url): self {
		$this->url = $url;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getUnavailable(): bool {
		return $this->unavailable;
	}

	/**
	 * @param boolean $unavailable
	 *
	 * @return static
	 */
	public function setUnavailable(bool $unavailable): self {
		$this->unavailable = $unavailable;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getDeleted(): bool {
		return $this->deleted;
	}

	/**
	 * @param boolean $deleted
	 * @return QueryParameters
	 */
	public function setDeleted(bool $deleted): QueryParameters {
		$this->deleted = $deleted;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getArchived(): bool {
		return $this->archived;
	}

	/**
	 * @param boolean $archived
	 *
	 * @return static
	 */
	public function setArchived(bool $archived): self {
		$this->archived = $archived;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function getUntagged(): bool {
		return $this->untagged;
	}

	/**
	 * @param bool $untagged
	 *
	 * @return static
	 */
	public function setUntagged(bool $untagged): self {
		$this->untagged = $untagged;
		return $this;
	}
}
