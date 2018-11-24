<?php

namespace OCA\Bookmarks\Controller\Lib\Http;

use OCP\Http\Client\IClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7;

class Client implements ClientInterface {
	protected $nextcloudClient;

	public function __construct(IClient $nextcloudClient) {
		$this->nextcloudClient = $nextcloudClient;
	}

	/**
	 * Sends a PSR-7 request and returns a PSR-7 response.
	 *
	 * Every technically correct HTTP response MUST be returned as-is, even if it represents an HTTP
	 * error response or a redirect instruction. The only special case is 1xx responses, which MUST
	 * be assembled in the HTTP client.
	 *
	 * The client MAY do modifications to the Request before sending it. Because PSR-7 objects are
	 * immutable, one cannot assume that the object passed to ClientInterface::sendRequest() will be the same
	 * object that is actually sent. For example, the Request object that is returned by an exception MAY
	 * be a different object than the one passed to sendRequest, so comparison by reference (===) is not possible.
	 *
	 * {@link https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message-meta.md#why-value-objects}
	 *
	 * @param RequestInterface $request
	 *
	 * @return ResponseInterface
	 *
	 * @throws \Psr\Http\Client\ClientException If an error happens while processing the request.
	 */
	public function sendRequest(RequestInterface $request) : ResponseInterface {
		if ($request->getMethod() === 'GET' || $request->getMethod() === 'OPTIONS') {
			$ncRes = $this->nextcloudClient->{strtolower($request->getMethod())}($request->getUri(), ['timeout' => 10]);
			$res = new Response();

			foreach ($ncRes->getHeaders() as $key => $value) {
				$res = $res->withHeader($key, $value);
			}

			return $res
			->withStatus($ncRes->getStatusCode())
			->withBody(Psr7\stream_for($ncRes->getBody()));
		} else {
			throw new \Exception('Can only send GET or OPTIONS requests'); // XXX: How should Streams be sent using nextcloud?
		}
	}
}
