<?php


namespace OCA\Bookmarks\Contract;


interface IImage {
	public function getContentType(): string;

	public function getData();
}
