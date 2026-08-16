<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service\Previewers;

use Exception;
use OCA\Bookmarks\Contract\IBookmarkPreviewer;
use OCA\Bookmarks\Contract\IImage;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Image;
use OCP\IBinaryFinder;
use OCP\IConfig;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;
use function exec;

class PageresBookmarkPreviewer implements IBookmarkPreviewer {
	public const CACHE_PREFIX = 'bookmarks.PageresPreviewService';
	public const CAPTURE_MAX_RETRIES = 3;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * @var ITempManager
	 */
	private $tempManager;
	/**
	 * @var IConfig
	 */
	private $config;
	private IBinaryFinder $binaryFinder;

	public function __construct(
		ITempManager $tempManager,
		LoggerInterface $logger,
		IConfig $config,
		IBinaryFinder $binaryFinder,
	) {
		$this->tempManager = $tempManager;
		$this->logger = $logger;
		$this->config = $config;
		$this->binaryFinder = $binaryFinder;
	}

	/**
	 * @param Bookmark|null $bookmark
	 *
	 * @return Image|null
	 */
	public function getImage($bookmark, $cacheOnly = false): ?IImage {
		if (!isset($bookmark)) {
			return null;
		}

		$serverPath = $this->binaryFinder->findBinaryPath('pageres');
		if ($serverPath === false || $cacheOnly) {
			return null;
		}

		$url = $bookmark->getUrl();

		// Fetch image from remote server
		return $this->fetchImage($serverPath, $url);
	}

	/**
	 * The setting is a space-separated list of NAME=VALUE assignments. Values are
	 * quoted so they cannot terminate the assignment and inject further commands,
	 * and anything that isn't a well-formed assignment is dropped rather than
	 * spliced into the command line.
	 *
	 * @return string Empty, or assignments followed by a single trailing space.
	 */
	private function buildEnvPrefix(string $env): string {
		if (trim($env) === '') {
			return '';
		}

		preg_match_all(
			'/(?<name>[A-Za-z_][A-Za-z0-9_]*)=(?:"(?<dq>[^"]*)"|\'(?<sq>[^\']*)\'|(?<bare>\S*))/',
			$env,
			$matches,
			PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL
		);

		$assignments = [];
		foreach ($matches as $match) {
			$value = $match['dq'] ?? $match['sq'] ?? $match['bare'] ?? '';
			$assignments[] = $match['name'] . '=' . escapeshellarg($value);
		}

		// Whatever the regex didn't consume was not an assignment, so it would have
		// been executed as part of the command. Tell the admin instead of running it.
		$remainder = preg_replace(
			'/(?<name>[A-Za-z_][A-Za-z0-9_]*)=(?:"[^"]*"|\'[^\']*\'|\S*)/',
			'',
			$env
		);
		if (trim((string)$remainder) !== '') {
			$this->logger->warning(
				'Ignoring unparsable content in the previews.pageres.env setting; only NAME=VALUE assignments are supported.',
				['app' => 'bookmarks']
			);
		}

		return $assignments === [] ? '' : implode(' ', $assignments) . ' ';
	}

	/**
	 * @param string $serverPath
	 * @param string $url
	 *
	 * @return Image
	 *
	 * @throws Exception
	 */
	protected function fetchImage(string $serverPath, string $url): Image {
		$tempPath = $this->tempManager->getTemporaryFile('.png');
		$tempDir = dirname($tempPath);
		$tempFile = basename($tempPath, '.png');
		$command = $serverPath;
		$escapedUrl = escapeshellarg($url);
		$env = $this->buildEnvPrefix($this->config->getAppValue('bookmarks', 'previews.pageres.env'));

		$cmd = "cd {$tempDir} && {$env}{$command} {$escapedUrl} 1024x768"
			. ' --delay=4 --filename=' . escapeshellarg($tempFile) . ' --crop --overwrite 2>&1';

		$retries = 0;
		$output = [];
		while ($retries < self::CAPTURE_MAX_RETRIES) {
			$output = [];
			@exec($cmd, $output, $returnCode);

			if ($returnCode === 0 && is_file($tempPath)) {
				$content = file_get_contents($tempPath);
				unlink($tempPath);

				return new Image('image/png', $content);
			} else {
				$this->logger->debug('Executing pageres failed');
				$this->logger->debug(implode("\n", $output));
			}

			$retries++;
		}

		throw new Exception("Pageres Error\nCommand: {$cmd}\nOutput: " . implode(' ' . PHP_EOL, $output) . PHP_EOL);
	}
}
