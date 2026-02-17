<?php

namespace OpenTokTest;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use OpenTok\Archive;
use OpenTok\Exception\InvalidArgumentException;
use OpenTok\Stream;
use OpenTok\StreamMode;
use OpenTok\Util\Client;
use PHPUnit\Framework\TestCase;

class ArchiveTest extends TestCase
{

    public $historyContainer;
    // Fixtures
    protected $archiveData;
    protected $API_KEY;
    protected $API_SECRET;

    protected $archive;
    protected $client;

    protected static $mockBasePath;

    public static function setUpBeforeClass(): void
    {
        self::$mockBasePath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'mock' . DIRECTORY_SEPARATOR;
    }

    public function setupArchives($streamMode, $quantization = false): void
    {
        // Set up fixtures
        $this->archiveData = ['createdAt' => 1394394801000, 'duration' => 0, 'id' => '063e72a4-64b4-43c8-9da5-eca071daab89', 'name' => 'showtime', 'partnerId' => 854511, 'reason' => '', 'sessionId' => '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-', 'size' => 0, 'status' => 'started', 'url' => null, 'hasVideo' => false, 'hasAudio' => true, 'outputMode' => 'composed', 'resolution' => '640x480', 'streamMode' => $streamMode, 'multiArchiveTag' => true, 'maxBitrate' => 400000];

        if ($quantization) {
            unset($this->archiveData['maxBitrate']);
            $this->archiveData['quantizationParameter'] = 40;
        }

        $this->archive = new Archive($this->archiveData, ['apiKey' => $this->API_KEY, 'apiSecret' => $this->API_SECRET, 'client' => $this->client]);
    }

    private function setupOTWithMocks(array $mocks): void
    {
        $this->API_KEY = defined('API_KEY') ? API_KEY : '12345678';
        $this->API_SECRET = defined('API_SECRET') ? API_SECRET : 'b60d0b2568f3ea9731bd9d3f71be263ce19f802f';

        $responses = is_array($mocks) ? TestHelpers::mocksToResponses($mocks, self::$mockBasePath) : [];

        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $clientOptions = [
            'handler' => $handlerStack
        ];

        $this->client = new Client();
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

    private function setupOT()
    {
        return $this->setupOTWithMocks([]);
    }

    public function testInitializes(): void
    {
        // Arrange
        $this->setupOT();
        $this->setupArchives(StreamMode::AUTO);
        // Act
        // Assert
        $this->assertInstanceOf(Archive::class, $this->archive);
    }

    public function testReadsProperties(): void
    {
        $this->setupOT();
        $this->setupArchives(StreamMode::AUTO);

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
        $this->assertEquals($this->archiveData['streamMode'], $this->archive->streamMode);
        $this->assertEquals($this->archiveData['multiArchiveTag'], $this->archive->multiArchiveTag);
        $this->assertEquals($this->archiveData['maxBitrate'], $this->archive->maxBitrate);

       $this->setupArchives(StreamMode::AUTO, true);
       $this->assertEquals($this->archiveData['quantizationParameter'], $this->archive->quantizationParameter);
    }

    public function testStopsArchive(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/ARCHIVEID/stop'
        ]]);
        $this->setupArchives(StreamMode::AUTO);

        // Act
        $this->archive->stop();

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper((string) $request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive/'.$this->archiveData['id'].'/stop', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        // TODO: test the properties of the actual archive object
        $this->assertEquals('stopped', $this->archive->status);

    }

    public function testCannotAddStreamToArchiveInAutoMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/ARCHIVEID/get'
        ]]);

        $this->setupArchives(StreamMode::AUTO);

        $this->archive->addStreamToArchive(
            '5dfds4-asdda4asf4',
            true,
            true
        );
    }

    public function testCannotAddStreamToArchiveWithNoAudioAndVideo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/ARCHIVEID/get'
        ]]);

        $this->setupArchives(StreamMode::MANUAL);

        $this->archive->addStreamToArchive(
            '5dfds4-asdda4asf4',
            false,
            false
        );
    }

    public function testCanAddStreamToArchive(): void
    {
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/ARCHIVEID/get'
        ]]);

        $this->setupArchives(StreamMode::MANUAL);

        $return = $this->archive->addStreamToArchive(
            '5dfds4-asdda4asf4',
            true,
            true
        );
        $this->assertTrue($return);
    }

    public function testCanRemoveStreamFromArchive(): void
    {
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/ARCHIVEID/get'
        ]]);

        $this->setupArchives(StreamMode::MANUAL);

        $return = $this->archive->removeStreamFromArchive(
            '5dfds4-asdda4asf4'
        );
        $this->assertTrue($return);
    }

    public function testDeletesArchive(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 204
        ]]);
        $this->setupArchives(StreamMode::AUTO);

        // Act
        // TODO: should this test be run on an archive object whose fixture has status 'available'
        // instead of 'started'?
        $success = $this->archive->delete();

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('DELETE', strtoupper((string) $request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive/'.$this->archiveData['id'], $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $this->assertTrue($success);
        // TODO: assert that all properties of the archive object were cleared
    }

    public function testAllowsUnknownProperties(): void
    {
        $this->setupOT();

        // Set up fixtures
        $archiveData = ['createdAt' => 1394394801000, 'duration' => 0, 'id' => '063e72a4-64b4-43c8-9da5-eca071daab89', 'name' => 'showtime', 'partnerId' => 854511, 'reason' => '', 'sessionId' => '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-', 'size' => 0, 'status' => 'started', 'url' => null, 'notarealproperty' => 'not a real value'];

        $archive = new Archive($archiveData, ['apiKey' => $this->API_KEY, 'apiSecret' => $this->API_SECRET, 'client' => $this->client]);

        $this->assertInstanceOf(Archive::class, $archive);
    }

    public function testRejectsBadArchiveData(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->setupOT();

        // Set up fixtures
        $badArchiveData = ['createdAt' => 'imnotanumber', 'duration' => 0, 'id' => '063e72a4-64b4-43c8-9da5-eca071daab89', 'name' => 'showtime', 'partnerId' => 854511, 'reason' => '', 'sessionId' => '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-', 'size' => 0, 'status' => 'started', 'url' => null];

        new Archive($badArchiveData, ['apiKey' => $this->API_KEY, 'apiSecret' => $this->API_SECRET, 'client' => $this->client]);
    }

    public function testAllowsPausedStatus(): void
    {
        $this->setupOT();

        // Set up fixtures
        $archiveData = ['createdAt' => 1394394801000, 'duration' => 0, 'id' => '063e72a4-64b4-43c8-9da5-eca071daab89', 'name' => 'showtime', 'partnerId' => 854511, 'reason' => '', 'sessionId' => '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-', 'size' => 0, 'status' => 'paused', 'url' => null];

        $archive = new Archive($archiveData, ['apiKey' => $this->API_KEY, 'apiSecret' => $this->API_SECRET, 'client' => $this->client]);

        $this->assertInstanceOf(Archive::class, $archive);
        $this->assertEquals($archiveData['status'], $archive->status);
    }

    public function testSerializesToJson(): void
    {
        // Arrange
        $this->setupOT();
        $this->setupArchives(StreamMode::AUTO);

        // Act
        $archiveJson = $this->archive->toJson();

        // Assert
        $this->assertIsString($archiveJson);
        $this->assertNotNull(json_encode($archiveJson));
    }

    public function testSerializedToArray(): void
    {
        // Arrange
        $this->setupOT();
        $this->setupArchives(StreamMode::AUTO);

        // Act
        $archiveArray = $this->archive->toArray();

        // Assert
        $this->assertIsArray($archiveArray);
        $this->assertEquals($this->archiveData, $archiveArray);
    }
}

