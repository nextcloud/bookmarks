<?php
namespace OCA\Bookmarks\Controller\Lib;

use Marcelklehr\LinkPreview\Client as LinkPreview;
use Marcelklehr\LinkPreview\Exceptions\ConnectionErrorException;
use OCP\Http\Client\IClientService;
use OCA\Bookmarks\Controller\Lib\Http\RequestFactory;
use OCA\Bookmarks\Controller\Lib\Http\Client;
use phpUri;

class LinkExplorer {
	private $linkPreview;

	public function __construct(IClientService $clientService) {
		$client = $clientService->newClient();
		$this->linkPreview = new LinkPreview(new Client($client), new RequestFactory());
		$this->linkPreview->getParser('general')->setMinimumImageDimensions(150, 550);
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
			\OCP\Util::writeLog('bookmarks', $e, \OCP\Util::DEBUG);
			return $data;
		}

		$data = $preview->toArray();

		\OCP\Util::writeLog('bookmarks', 'getImage for URL: '.$url.' '.var_export($data, true), \OCP\Util::DEBUG);

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
