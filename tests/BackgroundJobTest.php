<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\BackgroundJobs\CrawlJob;
use OC\BackgroundJob\JobList;
use OCA\Bookmarks\BackgroundJobs\FileCacheGCJob;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\PublicFolderMapper;
use OCA\Bookmarks\Db\TagMapper;
use OCA\Bookmarks\Service\BookmarkPreviewer;
use OCA\Bookmarks\Service\CrawlService;
use OCA\Bookmarks\Service\FileCache;
use OCA\Bookmarks\Service\UrlNormalizer;
use OCP\AppFramework\App;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Class Test_BackgroundJob
 */
class BackgroundJobTest extends TestCase {
	/**
	 * @var BookmarkMapper
	 */
	private $bookmarkMapper;
	/**
	 * @var CrawlJob
	 */
	private $previewsJob;
	/**
	 * @var JobList
	 */
	private $jobList;
	/**
	 * @var mixed|\stdClass
	 */
	private $settings;
	/**
	 * @var string
	 */
	private $userId;
	/**
	 * @var FileCacheGCJob
	 */
	private $gcJob;

	private $bookmarks = [];

	/**
	 * @var IAppData
	 */
	private $appData;

	/**
	 * @var FileCache
	 */
	private $fileCache;

	/**
	 * @var ITimeFactory
	 */
	private $timeFactory;
	/**
	 * @var int
	 */
	private $time;
	/**
	 * @var \OCP\AppFramework\IAppContainer
	 */
	private $container;

	protected function setUp() :void {
		parent::setUp();

		$app = new App('bookmarks');
		$container = $this->container = $app->getContainer();

		// prepare
		$this->settings = $container->get(IConfig::class);
		$this->settings->setAppValue('bookmarks', 'privacy.enableScraping', 'true');
		$this->timeFactory = $this->createStub(ITimeFactory::class);

		$this->bookmarkMapper = new BookmarkMapper(
			$container->get(\OCP\IDBConnection::class),
			$container->get(\OCP\EventDispatcher\IEventDispatcher::class),
			$container->get(UrlNormalizer::class),
			$this->settings,
			$container->get(PublicFolderMapper::class),
			$container->get(TagMapper::class),
			$this->timeFactory
		);
		$this->previewsJob = new CrawlJob(
			$this->settings,
			$this->bookmarkMapper,
			$container->get(CrawlService::class),
			$this->timeFactory
		);
		$this->appData = $container->get(IAppData::class);
		$this->fileCache = new FileCache($this->appData, $this->timeFactory);
		$this->gcJob = new FileCacheGCJob($this->fileCache, $container->get(LoggerInterface::class), $this->timeFactory);
		$this->jobList = $container->get(JobList::class);

		$this->userId = 'test';

		$this->cleanUp();
		$this->bookmarks = array_map(function ($bm) {
			return $this->bookmarkMapper->insert($bm);
		}, $this->singleBookmarksProvider());
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function testPreviewsJob() : void {
		$this->fileCache->clear();
		$oldCacheSize = 0;

		$this->timeFactory->method('getTime')
			->willReturn(time());

		// generate cached previews
		$this->previewsJob->setId(1);
		$this->previewsJob->setLastRun(0);
		$this->previewsJob->execute($this->jobList);

		$folder = $this->appData->getFolder('cache');
		$newCacheSize = count($folder->getDirectoryListing());
		// should have cached something
		self::assertGreaterThan($oldCacheSize, $newCacheSize);
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function testGCJob() : void {
		$this->fileCache->clear();
		$initialCacheSize = 0;

		$this->timeFactory->method('getTime')
			->willReturn(time());

		// generate cached previews
		$this->previewsJob->setId(1);
		$this->previewsJob->setLastRun(0);
		$this->previewsJob->execute($this->jobList);

		$folder = $this->appData->getFolder('cache');
		$cacheSize = count($folder->getDirectoryListing());
		// should have cached something
		self::assertGreaterThan($initialCacheSize, $cacheSize);

		// fast-forward to a time when the previews should be garbage collected
		$time = time() + FileCache::TIMEOUT + 60*60*24;
		$this->timeFactory = $this->createStub(ITimeFactory::class);
		$this->timeFactory->method('getTime')
			->willReturn($time);
		$this->fileCache = new FileCache($this->appData, $this->timeFactory);
		$this->gcJob = new FileCacheGCJob($this->fileCache, $this->container->get(LoggerInterface::class), $this->timeFactory);

		// run GC job
		$this->gcJob->setId(3);
		$this->gcJob->setLastRun(0);
		$this->gcJob->execute($this->jobList);

		$newCacheSize = count($folder->getDirectoryListing());
		// should have cleaned up the pending cache entries
		self::assertLessThan($cacheSize, $newCacheSize);
	}

	/**
	 * @return array
	 */
	public function singleBookmarksProvider() {
		return array_map(function ($props) {
			return Bookmark::fromArray($props);
		}, [
			'Simple URL with title and description' => ['url' => 'https://google.com/', 'title' => 'Google', 'description' => 'Search engine'],
			'Simple URL with title' => ['url' => 'https://nextcloud.com/', 'title' => 'Nextcloud'],
			'Simple URL' => ['url' => 'https://php.net/'],
			'URL with unicode' => ['url' => 'https://de.wikipedia.org/wiki/%C3%9C'],
			'Non-existent URL' => ['url' => 'https://http://www.bllaala.com/'],
		]);
	}
}
