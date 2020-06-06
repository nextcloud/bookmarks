<?php

namespace OCA\Bookmarks\Http;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

class RequestFactory implements RequestFactoryInterface {
	/**
	 * Create a new request.
	 *
	 * @param string $method The HTTP method associated with the request.
	 * @param UriInterface|string $uri The URI associated with the request.
	 * @return RequestInterface
	 */
	public function createRequest(string $method, $uri): RequestInterface {
		return new Request($method, $uri);
	}
}
