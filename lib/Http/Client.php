<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Http;

use Exception;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Response;
use OCP\Http\Client\IClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client implements ClientInterface {
	protected $nextcloudClient;

	public function __construct(IClient $nextcloudClient) {
		$this->nextcloudClient = $nextcloudClient;
	}

	/**
	 * 	 * Sends a PSR-7 request and returns a PSR-7 response.
	 * 	 *
	 * 	 * Every technically correct HTTP response MUST be returned as-is, even if it represents an HTTP
	 * 	 * error response or a redirect instruction. The only special case is 1xx responses, which MUST
	 * 	 * be assembled in the HTTP client.
	 * 	 *
	 * 	 * The client MAY do modifications to the Request before sending it. Because PSR-7 objects are
	 * 	 * immutable, one cannot assume that the object passed to ClientInterface::sendRequest() will be the same
	 * 	 * object that is actually sent. For example, the Request object that is returned by an exception MAY
	 * 	 * be a different object than the one passed to sendRequest, so comparison by reference (===) is not possible.
	 * 	 *
	 * 	 * {@link
	 * 	 * https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message-meta.md#why-value-objects}
	 * 	 *
	 *
	 * @param RequestInterface $request
	 *
	 * @return ResponseInterface
	 *
	 * @throws Exception
	 */
	public function sendRequest(RequestInterface $request): ResponseInterface {
		$method = $request->getMethod();
		if ($method === 'GET' || $method === 'OPTIONS') {
			$ncRes = $this->nextcloudClient->{strtolower($request->getMethod())}($request->getUri(), ['timeout' => 10]);
			$res = new Response();

			foreach ($ncRes->getHeaders() as $key => $value) {
				$res = $res->withHeader($key, $value);
			}

			return $res
				->withStatus($ncRes->getStatusCode())
				->withBody(Psr7\Utils::streamFor($ncRes->getBody()));
		}

		throw new Exception('Can only send GET or OPTIONS requests'); // XXX: How should Streams be sent using nextcloud?
	}
}
