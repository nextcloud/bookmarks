<?php
namespace OCA\Bookmarks\Controller\Lib;

use Marcelklehr\LinkPreview\Client as LinkPreview;
use Marcelklehr\LinkPreview\Exceptions\ConnectionErrorException;
use phpUri;

class LinkExplorer {

    /**
	 * @brief Load Url and receive Metadata (Title)
	 * @param string $url Url to load and analyze
	 * @return array Metadata for url;
	 */
	public function get($url) {
		$data = ['url' => $url];

		// Use LinkPreview to get the meta data
		$previewClient = new LinkPreview($url);
		$previewClient->getParser('general')->setMinimumImageDimension(0,0);
		try {
			libxml_use_internal_errors(false);
            $preview = $previewClient->getPreview('general');
		} catch (\Marcelklehr\LinkPreview\Exceptions\ConnectionErrorException $e) {
			\OCP\Util::writeLog('bookmarks', $e, \OCP\Util::WARN);
			return $data;
		}

		$data = $preview->toArray();
		if (!isset($data)) {
			return ['url' => $url];
		}

		$data['url'] = (string) $previewClient->getUrl();
		$data['image'] = phpUri::parse($data['url'])->join($data['cover']);
		$data['favicon'] = phpUri::parse($data['url'])->join($data['favicon']);

		return $data;
	}

}
