<?php


namespace OCA\Bookmarks;


class QueryParameters {
	public const CONJ_AND = 'and';
	public const CONJ_OR = 'or';

	private $limit = 10;
	private $offset = 0;
	private $sortBy = null;
	private $conjunction = self::CONJ_AND;

	/**
	 * @return int
	 */
	public function getLimit(): int {
		return $this->limit;
	}

	/**
	 * @param int $limit
	 * @return QueryParameters
	 */
	public function setLimit(int $limit): QueryParameters {
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
	 * @return QueryParameters
	 */
	public function setOffset(int $offset): QueryParameters {
		$this->offset = $offset;
		return $this;
	}

	/**
	 * @param string|null $default
	 * @param array|null $columns
	 * @return string
	 */
	public function getSortBy(string $default = null, array $columns = null): string {
		if (isset($default) && !isset($this->sortBy)) {
			return $default;
		}
		if (isset($columns) && !in_array($this->sortBy, $columns, true)) {
			return $default;
		}
		return $this->sortBy;
	}

	/**
	 * @param string $sortBy
	 * @return QueryParameters
	 */
	public function setSortBy(string $sortBy): QueryParameters {
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
	 * @return QueryParameters
	 */
	public function setConjunction(string $conjunction): QueryParameters {
		if ($conjunction !== self::CONJ_AND && $conjunction !== self::CONJ_OR) {
			throw new \InvalidArgumentException("Conjunction value must be 'and' or 'or'");
		}
		$this->conjunction = $conjunction;
		return $this;
	}


}
