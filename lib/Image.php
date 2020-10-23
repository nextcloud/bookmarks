<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks;

use OCA\Bookmarks\Contract\IImage;

class Image implements IImage {
	/**
	 * @var string
	 */
	private $type;
	private $data;

	/**
	 * Image constructor.
	 *
	 * @param string $type
	 * @param $data
	 */
	public function __construct(string $type, $data) {
		$this->type = $type;
		$this->data = $data;
	}

	public static function deserialize(string $json): Image {
		$image = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		return new Image($image['contentType'], $image['data'] ? base64_decode($image['data']) : null);
	}

	/**
	 * @return string
	 */
	public function getContentType(): string {
		return $this->type;
	}

	/**
	 * @return mixed
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * @return false|string
	 */
	public function serialize() {
		return json_encode([
			'contentType' => $this->getContentType(),
			'data' => base64_encode($this->getData()),
		], JSON_THROW_ON_ERROR);
	}
}
