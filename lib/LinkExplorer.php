<?php
namespace OCA\Bookmarks;

use Marcelklehr\LinkPreview\Client as LinkPreview;
use Marcelklehr\LinkPreview\Exceptions\ConnectionErrorException;
use OCP\ILogger;
use OCP\Http\Client\IClientService;
use OCA\Bookmarks\Http\RequestFactory;
use OCA\Bookmarks\Http\Client;
use phpUri;

class LinkExplorer {
	private $linkPreview;

	private $logger;

	public function __construct(IClientService $clientService, ILogger $logger) {
		$client = $clientService->newClient();
		$this->linkPreview = new LinkPreview(new Client($client), new RequestFactory());
		$this->linkPreview->getParser('general')->setMinimumImageDimensions(150, 550);
		$this->logger = $logger;
	}

	/**
	 * @brief Load Url and receive Metadata (Title)
	 * @param string $url Url to load and analyze
	 * @return array Metadata for url;
	 */
	public function get($url) {
		$data = ['url' => $url];

		// Use LinkPreview to get the meta data
		try {
			libxml_use_internal_errors(false);
			$preview = $this->linkPreview->getLink($url)->getPreview();
		} catch (\Exception $e) {
			$this->logger->debug($e, ['app' => 'bookmarks']);
			return $data;
		}

		$data = $preview->toArray();

		$this->logger->debug('getImage for URL: '.$url.' '.var_export($data, true), ['app' => 'bookmarks']);

		if (!isset($data)) {
			return ['url' => $url];
		}

		$data['url'] = (string) $preview->getUrl();
		if (isset($data['image'])) {
			if (isset($data['image']['small'])) {
				$data['image']['small'] = phpUri::parse($data['url'])->join($data['image']['small']);
			}
			if (isset($data['image']['large'])) {
				$data['image']['large'] = phpUri::parse($data['url'])->join($data['image']['large']);
			}
			if (isset($data['image']['favicon'])) {
				$data['image']['favicon'] = phpUri::parse($data['url'])->join($data['image']['favicon']);
			}
		}

		return $data;
	}
}
