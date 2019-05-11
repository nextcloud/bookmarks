<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\UrlNormalizer;

class Test_UrlNormalizer extends TestCase {
	private $url;

	protected function setUp() {
		parent::setUp();
		$this->url = new UrlNormalizer();
	}

	public function testGetSorting() {
		$data = [
			['sindresorhus.com', 'sindresorhus.com'],
			['sindresorhus.com ', 'sindresorhus.com'],
			['sindresorhus.com.', 'sindresorhus.com'],
			['HTTP://sindresorhus.com', 'http://sindresorhus.com'],
			['//sindresorhus.com', '//sindresorhus.com'],
			['http://sindresorhus.com', 'http://sindresorhus.com'],
			['http://sindresorhus.com:80', 'http://sindresorhus.com'],
			['https://sindresorhus.com:443', 'https://sindresorhus.com'],
			['ftp://sindresorhus.com:21', 'ftp://sindresorhus.com'],
			['http://www.sindresorhus.com', 'http://www.sindresorhus.com'],
			['www.com', 'www.com'],
			['http://www.www.sindresorhus.com', 'http://www.www.sindresorhus.com'],
			['www.sindresorhus.com', 'www.sindresorhus.com'],
			['http://sindresorhus.com/foo/', 'http://sindresorhus.com/foo/'],
			['sindresorhus.com/?foo=bar baz', 'sindresorhus.com/?foo=bar%20baz'],
			['https://foo.com/?foo=http://bar.com', 'https://foo.com/?foo=http%3A%2F%2Fbar.com'],
			['http://sindresorhus.com/%7Efoo/', 'http://sindresorhus.com/~foo/'],
			['http://sindresorhus.com/foo/######/blablabla', 'http://sindresorhus.com/foo/######/blablabla'],
		  ['https://mylink.com/#/#/#/#/#/', 'https://mylink.com/#/#/#/#/#/'],
			['http://google.com####/foobar', 'http://google.com/####/foobar'],
			['http://sindresorhus.com/?', 'http://sindresorhus.com'],
			['http://êxample.com', 'http://xn--xample-hva.com'],
			['http://sindresorhus.com/?b=bar&a=foo', 'http://sindresorhus.com/?a=foo&b=bar'],
			['http://sindresorhus.com/?foo=bar*|<>:"', 'http://sindresorhus.com/?foo=bar*%7C%3C%3E%3A%22'],
			['http://sindresorhus.com:5000', 'http://sindresorhus.com:5000'],
			['//sindresorhus.com:80/', '//sindresorhus.com:80'],
			['http://sindresorhus.com/foo#bar', 'http://sindresorhus.com/foo#bar'],
			['http://sindresorhus.com/foo/bar/../baz', 'http://sindresorhus.com/foo/baz'],
			['http://sindresorhus.com/foo/bar/./baz', 'http://sindresorhus.com/foo/bar/baz'],
			['https://i.vimeocdn.com/filter/overlay?src0=https://i.vimeocdn.com/video/598160082_1280x720.jpg&src1=https://f.vimeocdn.com/images_v6/share/play_icon_overlay.png', 'https://i.vimeocdn.com/filter/overlay?src0=https%3A%2F%2Fi.vimeocdn.com%2Fvideo%2F598160082_1280x720.jpg&src1=https%3A%2F%2Ff.vimeocdn.com%2Fimages_v6%2Fshare%2Fplay_icon_overlay.png'],

		  // authorization
			['https://user:password@www.sindresorhus.com', 'https://user:password@www.sindresorhus.com'],
			['https://user:password@www.sindresorhus.com/@user', 'https://user:password@www.sindresorhus.com/%40user'],
			['http://user:password@www.êxample.com', 'http://user:password@www.xn--xample-hva.com'],

			// query params
			['http://sindresorhus.com/?a=Z&b=Y&c=X&d=W', 'http://sindresorhus.com/?a=Z&b=Y&c=X&d=W'],
			['http://sindresorhus.com/?b=Y&c=X&a=Z&d=W', 'http://sindresorhus.com/?a=Z&b=Y&c=X&d=W'],
			['http://sindresorhus.com/?a=Z&d=W&b=Y&c=X', 'http://sindresorhus.com/?a=Z&b=Y&c=X&d=W'],
		  ['https://www.tivocommunity.com/community/index.php?threads/av-jack-wiring.502081/', 'https://www.tivocommunity.com/community/index.php?threads/av-jack-wiring.502081/'],
			// encoding
			['http://sindresorhus.com/foo%0cbar/?a=Z&d=W&b=Y&c=X%0c', 'http://sindresorhus.com/foo%0cbar/?a=Z&b=Y&c=X%0c&d=W'],

			['http://sindresorhus.com////foo/bar', 'http://sindresorhus.com/foo/bar'],
			['http://sindresorhus.com////foo////bar', 'http://sindresorhus.com/foo/bar'],
			['//sindresorhus.com//foo', '//sindresorhus.com//foo'], // cannot normalize path if we don't know the protocol
			['http://sindresorhus.com:5000///foo', 'http://sindresorhus.com:5000/foo'],
			['http://sindresorhus.com///foo', 'http://sindresorhus.com/foo'],
			['http://sindresorhus.com:5000//foo', 'http://sindresorhus.com:5000/foo'],
			['http://sindresorhus.com//foo', 'http://sindresorhus.com/foo']
		];
		foreach ($data as $item) {
			$this->assertEquals($item[1], $url->normlize($item[0]));
			// idempotence
			$this->assertEquals($item[1], $url->normlize($url->normlize($item[0])));
		}
	}
}
