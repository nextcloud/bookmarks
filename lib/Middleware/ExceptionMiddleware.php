<?php
/*
 * Copyright (c) 2021. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Middleware;

use OCA\Bookmarks\Exception\UnauthenticatedError;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use \OCP\AppFramework\Middleware;

class ExceptionMiddleware extends Middleware {
	public function afterException($controller, $methodName, \Exception $exception): DataResponse {
		if ($exception instanceof UnauthenticatedError) {
			$res = new DataResponse(['status' => 'error', 'data' => 'Please authenticate first'], Http::STATUS_UNAUTHORIZED);
			$res->addHeader('WWW-Authenticate', 'Basic realm="Nextcloud Bookmarks", charset="UTF-8"');
			return $res;
		}
		throw $exception;
	}
}
