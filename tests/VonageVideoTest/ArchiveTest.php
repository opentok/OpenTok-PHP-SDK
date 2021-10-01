<?php

namespace VonageVideoTest;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use OpenTok\Archive;
use OpenTok\Util\Client;
use PHPUnit\Framework\TestCase;

class ArchiveTest extends TestCase
{
    /**
     * @var array
     */
    protected $archiveData;

    /**
     * @var string
     */
    protected $API_KEY;

    /**
     * @var string
     */
    protected $API_SECRET;

    /**
     * @var Archive
     */
    protected $archive;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    protected static $mockBasePath;

    /**
     * @var array<array>
     */
    private $historyContainer;

    public static function setUpBeforeClass(): void
    {
        self::$mockBasePath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'mock' . DIRECTORY_SEPARATOR;
    }

    public function setupArchives(): void
    {
        $this->archiveData = [
            'createdAt' => 1394394801000,
            'duration' => 0,
            'id' => '063e72a4-64b4-43c8-9da5-eca071daab89',
            'name' => 'showtime',
            'partnerId' => 854511,
            'reason' => '',
            'sessionId' => '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-',
            'size' => 0,
            'status' => 'started',
            'url' => null,
            'hasVideo' => false,
            'hasAudio' => true,
            'outputMode' => 'composed',
            'resolution' => '640x480'
        ];

        $this->archive = new Archive($this->archiveData, [
            'apiKey' => $this->API_KEY,
            'apiSecret' => $this->API_SECRET,
            'client' => $this->client
        ]);
    }

    /**
     * @param array<array> $mocks
     */
    private function setupOpenTokWithMocks(array $mocks): void
    {
        $this->API_KEY = defined('API_KEY') ? API_KEY : '12345678';
        $this->API_SECRET = defined('API_SECRET') ? API_SECRET : '0123456789abcdef0123456789abcdef0123456789';

        $responses = TestHelpers::mocksToResponses($mocks, self::$mockBasePath);

        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);

        $clientOptions = [
            'handler' => $handlerStack
        ];

        $this->client = new Client();
        #
        $this->client->configure(
            $this->API_KEY,
            $this->API_SECRET,
            'https://api.opentok.com',
            $clientOptions
        );

        // Push history onto handler stack *after* configuring client to
        // ensure auth header is added before history handler is invoked
        $this->historyContainer = [];
        $history = Middleware::history($this->historyContainer);

        $handlerStack->push($history);
    }

    private function setupOpenTok(): void
    {
        $this->setupOpenTokWithMocks([]);
    }

    public function testInitializes(): void
    {
        $this->setupOpenTok();
        $this->setupArchives();

        $this->assertInstanceOf(Archive::class, $this->archive);
    }

    public function testReadsProperties(): void
    {
        $this->setupOpenTok();
        $this->setupArchives();

        $this->assertEquals($this->archiveData['createdAt'], $this->archive->createdAt);
        $this->assertEquals($this->archiveData['duration'], $this->archive->duration);
        $this->assertEquals($this->archiveData['id'], $this->archive->id);
        $this->assertEquals($this->archiveData['name'], $this->archive->name);
        $this->assertEquals($this->archiveData['partnerId'], $this->archive->partnerId);
        $this->assertEquals($this->archiveData['reason'], $this->archive->reason);
        $this->assertEquals($this->archiveData['sessionId'], $this->archive->sessionId);
        $this->assertEquals($this->archiveData['size'], $this->archive->size);
        $this->assertEquals($this->archiveData['status'], $this->archive->status);
        $this->assertEquals($this->archiveData['url'], $this->archive->url);
        $this->assertEquals($this->archiveData['hasVideo'], $this->archive->hasVideo);
        $this->assertEquals($this->archiveData['hasAudio'], $this->archive->hasAudio);
        $this->assertEquals($this->archiveData['outputMode'], $this->archive->outputMode);
        $this->assertEquals($this->archiveData['resolution'], $this->archive->resolution);
    }

    public function testStopsArchive(): void
    {
        $this->setupOpenTokWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/ARCHIVEID/stop'
        ]]);


        $this->setupArchives();

        $this->archive->stop();

        $this->assertCount(1, $this->historyContainer);
        ray($this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals(
            '/v2/project/'
            . $this->API_KEY
            . '/archive/'
            . $this->archiveData['id']
            . '/stop',
            $request->getUri()->getPath()
        );

        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);
        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');

        $this->assertEquals(
            true,
            TestHelpers::validateVonageVideoAuthHeader(
                $this->API_KEY,
                $this->API_SECRET,
                $authString
            )
        );

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);
        $this->assertEquals('stopped', $this->archive->status);
    }

    public function testCanStartArchiveInManualStreamMode(): void
    {
        $this->markTestIncomplete('TODO: placeholder, write this test');
    }

    public function testDeletesArchive(): void
    {
        $this->setupOpenTokWithMocks([[
            'code' => 204
        ]]);

        $this->setupArchives();

        $success = $this->archive->delete();

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];

        $this->assertEquals('DELETE', strtoupper($request->getMethod()));
        $this->assertEquals(
            '/v2/project/' . $this->API_KEY . '/archive/' . $this->archiveData['id'],
            $request->getUri()->getPath()
        );

        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(
            true,
            TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString)
        );

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $this->assertTrue($success);
    }

    public function testAllowsUnknownProperties(): void
    {
        $this->setupOpenTok();

        $archiveData = [
            'createdAt' => 1394394801000,
            'duration' => 0,
            'id' => '063e72a4-64b4-43c8-9da5-eca071daab89',
            'name' => 'showtime',
            'partnerId' => 854511,
            'reason' => '',
            'sessionId' => '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-',
            'size' => 0,
            'status' => 'started',
            'url' => null,
            'notarealproperty' => 'not a real value'
        ];

        $archive = new Archive($archiveData, [
            'apiKey' => $this->API_KEY,
            'apiSecret' => $this->API_SECRET,
            'client' => $this->client
        ]);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
    }

    public function testRejectsBadArchiveData(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->setupOpenTok();

        $badArchiveData = [
            'createdAt' => 'imnotanumber',
            'duration' => 0,
            'id' => '063e72a4-64b4-43c8-9da5-eca071daab89',
            'name' => 'showtime',
            'partnerId' => 854511,
            'reason' => '',
            'sessionId' => '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-',
            'size' => 0,
            'status' => 'started',
            'url' => null
        ];

        $archive = new Archive($badArchiveData, [
            'apiKey' => $this->API_KEY,
            'apiSecret' => $this->API_SECRET,
            'client' => $this->client
        ]);
    }

    public function testAllowsPausedStatus(): void
    {
        $this->setupOpenTok();

        $archiveData = [
            'createdAt' => 1394394801000,
            'duration' => 0,
            'id' => '063e72a4-64b4-43c8-9da5-eca071daab89',
            'name' => 'showtime',
            'partnerId' => 854511,
            'reason' => '',
            'sessionId' => '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-',
            'size' => 0,
            'status' => 'paused',
            'url' => null,
        ];

        $archive = new Archive($archiveData, array(
            'apiKey' => $this->API_KEY,
            'apiSecret' => $this->API_SECRET,
            'client' => $this->client
        ));

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        $this->assertEquals($archiveData['status'], $archive->status);
    }

    public function testSerializesToJson(): void
    {
        $this->setupOpenTok();
        $this->setupArchives();

        $archiveJson = $this->archive->toJson();

        $this->assertIsString($archiveJson);
        $this->assertNotNull(json_encode($archiveJson));
    }

    public function testSerializedToArray(): void
    {
        $this->setupOpenTok();
        $this->setupArchives();

        $archiveArray = $this->archive->jsonSerialize();

        $this->assertIsArray($archiveArray);
        $this->assertEquals($this->archiveData, $archiveArray);
    }
}
