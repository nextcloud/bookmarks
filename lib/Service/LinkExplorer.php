<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use Marcelklehr\LinkPreview\Client as LinkPreview;
use OCA\Bookmarks\Http\Client;
use OCA\Bookmarks\Http\RequestFactory;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use Rowbot\URL\Exception\TypeError;
use Rowbot\URL\URL;

class LinkExplorer {
	private $linkPreview;

	private $logger;

	/**
	 * @var string
	 */
	private $enabled;

	public function __construct(IClientService $clientService, LoggerInterface $logger, IConfig $config) {
		$client = $clientService->newClient();
		$this->linkPreview = new LinkPreview(new Client($client), new RequestFactory());
		$this->linkPreview->getParser('general')->setMinimumImageDimensions(150, 550);
		$this->logger = $logger;
		$this->enabled = $config->getAppValue('bookmarks', 'privacy.enableScraping', 'false');
	}

	/**
	 * @brief Load Url and receive Metadata (Title)
	 * @param string $url Url to load and analyze
	 * @return array Metadata for url;
	 */
	public function get($url): array {
		$data = ['url' => $url];

		if ($this->enabled === 'false') {
			return $data;
		}

		// Use LinkPreview to get the meta data
		try {
			libxml_use_internal_errors(false);
			$preview = $this->linkPreview->getLink($url)->getPreview();
		} catch (\Throwable $e) {
			$this->logger->debug($e->getMessage(), ['app' => 'bookmarks']);
			return $data;
		}

		$data = $preview->toArray();

		if (!isset($data)) {
			return ['url' => $url];
		}

		$data['url'] = (string)$preview->getUrl();
		if (isset($data['image'])) {
			if (isset($data['image']['small'])) {
				try {
					$data['image']['small'] = $this->resolveUrl($data['image']['small'], $data['url']);
				} catch (TypeError $e) {
					// noop
				}
			}
			if (isset($data['image']['large'])) {
				try {
					$data['image']['large'] = $this->resolveUrl($data['image']['large'], $data['url']);
				} catch (TypeError $e) {
					// noop
				}
			}
			if (isset($data['image']['favicon'])) {
				try {
					$data['image']['favicon'] = $this->resolveUrl($data['image']['favicon'], $data['url']);
				} catch (TypeError $e) {
					// noop
				}
			}
		}

		return $data;
	}

	/**
	 * @throws TypeError
	 */
	private function resolveUrl(string $link, string $base) : string {
		$url = new URL($link, $base);
		return $url->href;
	}
}
