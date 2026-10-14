<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Service\BookmarkPreviewer;
use OCA\Bookmarks\Service\CrawlService;
use OCA\Bookmarks\Service\FaviconPreviewer;
use OCA\Bookmarks\Service\UserSettingsService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class CrawlServiceTest extends TestCase {
	private BookmarkMapper $bookmarkMapper;
	private BookmarkPreviewer $bookmarkPreviewer;
	private FaviconPreviewer $faviconPreviewer;
	private IClientService $clientService;
	private IClient $client;
	private IConfig $config;
	private IRootFolder $rootFolder;
	private IL10N $l10n;
	private LoggerInterface $logger;
	private UserSettingsService $userSettings;
	private CrawlService $crawlService;
	private Folder $userFolder;
	private File $file;

	protected function setUp(): void {
		parent::setUp();

		$this->bookmarkMapper = $this->createMock(BookmarkMapper::class);
		$this->bookmarkPreviewer = $this->createMock(BookmarkPreviewer::class);
		$this->faviconPreviewer = $this->createMock(FaviconPreviewer::class);
		$this->client = $this->createMock(IClient::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->clientService->method('newClient')->willReturn($this->client);
		$this->config = $this->createMock(IConfig::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->userSettings = $this->createMock(UserSettingsService::class);

		$this->userFolder = $this->createMock(Folder::class);
		$this->file = $this->createMock(File::class);

		$this->rootFolder->method('getUserFolder')->with('testuser')->willReturn($this->userFolder);
		$this->userSettings->method('get')->with('archive.enabled')->willReturn('true');
		$this->config->method('getUserValue')->willReturn('Bookmarks');

		$this->crawlService = new CrawlService(
			$this->bookmarkMapper,
			$this->bookmarkPreviewer,
			$this->faviconPreviewer,
			$this->clientService,
			$this->config,
			$this->rootFolder,
			$this->l10n,
			$this->logger,
			$this->userSettings
		);
	}

	public function testArchiveFileRejectsMissingContentLengthWhenBodyOversized(): void {
		$bookmark = new Bookmark();
		$bookmark->setUrl('https://example.com/oversized.bin');
		$bookmark->setUserId('testuser');
		$bookmark->setTitle('Oversized File');

		$oversizedBody = str_repeat('A', CrawlService::MAX_BODY_LENGTH + 1024);

		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getHeader')->willReturnCallback(function ($header) {
			if ($header === 'Content-Type') {
				return 'application/octet-stream';
			}
			if ($header === 'Content-Length') {
				return ''; // Missing Content-Length header (chunked)
			}
			return '';
		});
		$response->method('getBody')->willReturn($oversizedBody);

		$this->client->method('get')->willReturn($response);

		// An oversized body MUST NOT be written to user storage
		$this->userFolder->expects($this->never())->method('newFile');

		$this->crawlService->crawl($bookmark);
	}

	public function testArchiveFileRejectsSpoofedContentLengthWhenBodyOversized(): void {
		$bookmark = new Bookmark();
		$bookmark->setUrl('https://example.com/spoofed.bin');
		$bookmark->setUserId('testuser');
		$bookmark->setTitle('Spoofed Size File');

		$oversizedBody = str_repeat('A', CrawlService::MAX_BODY_LENGTH + 1024);

		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getHeader')->willReturnCallback(function ($header) {
			if ($header === 'Content-Type') {
				return 'application/octet-stream';
			}
			if ($header === 'Content-Length') {
				return '10'; // Falsified small Content-Length header
			}
			return '';
		});
		$response->method('getBody')->willReturn($oversizedBody);

		$this->client->method('get')->willReturn($response);

		// An oversized body MUST NOT be written to user storage
		$this->userFolder->expects($this->never())->method('newFile');

		$this->crawlService->crawl($bookmark);
	}

	public function testArchiveFileSavesValidBodyWithinLimit(): void {
		$bookmark = new Bookmark();
		$bookmark->setUrl('https://example.com/valid.bin');
		$bookmark->setUserId('testuser');
		$bookmark->setTitle('Valid File');

		$validBody = 'small file payload';

		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getHeader')->willReturnCallback(function ($header) {
			if ($header === 'Content-Type') {
				return 'application/octet-stream';
			}
			if ($header === 'Content-Length') {
				return (string)strlen('small file payload');
			}
			return '';
		});
		$response->method('getBody')->willReturn($validBody);

		$this->client->method('get')->willReturn($response);

		$this->userFolder->method('nodeExists')->willReturn(false);
		$this->userFolder->expects($this->once())->method('newFile')->willReturn($this->file);
		$this->file->expects($this->once())->method('putContent')->with($validBody);
		$this->file->method('getId')->willReturn(12345);

		$this->crawlService->crawl($bookmark);

		$this->assertEquals(12345, $bookmark->getArchivedFile());
	}
}
