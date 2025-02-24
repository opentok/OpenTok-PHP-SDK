<?php

namespace OpenTokTest;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use OpenTok\Broadcast;
use OpenTok\Exception\InvalidArgumentException;
use OpenTok\StreamMode;
use OpenTok\Util\Client;
use PHPUnit\Framework\TestCase;

class BroadcastTest extends TestCase
{
    protected $API_KEY;
    protected $API_SECRET;

    protected $broadcast;
    protected $broadcastData;
    protected $client;

    protected static $mockBasePath;
    /**
     * @var array
     */
    private $historyContainer;

    public function setUp(): void
    {
        $this->broadcastData = [
            'id' => '063e72a4-64b4-43c8-9da5-eca071daab89',
            'createdAt' => 1394394801000,
            'updatedAt' => 1394394801000,
            'partnerId' => 685,
            'sessionId' => '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-',
            'multiBroadcastTag' => 'broadcast-1234b',
            'layout' => [
                'type' => 'custom',
                'stylesheet' => 'a layout stylesheet',
                'screenshareType' => 'some options'
            ],
            'maxDuration' => 5400,
            'resolution' => '640x480',
            'streamMode' => StreamMode::AUTO,
            'status' => 'started',
            'hasAudio' => true,
            'hasVideo' => true
        ];
    }

    public static function setUpBeforeClass(): void
    {
        self::$mockBasePath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'mock' . DIRECTORY_SEPARATOR;
    }

    public function setupBroadcasts($streamMode)
    {
        $data = $this->broadcastData;
        $data['streamMode'] = $streamMode;

        $this->broadcast = new Broadcast($data, array(
            'apiKey' => $this->API_KEY,
            'apiSecret' => $this->API_SECRET,
            'client' => $this->client
        ));
    }

    private function setupOTWithMocks($mocks)
    {
        $this->API_KEY = defined('API_KEY') ? API_KEY : '12345678';
        $this->API_SECRET = defined('API_SECRET') ? API_SECRET : 'b60d0b2568f3ea9731bd9d3f71be263ce19f802f';

        if (is_array($mocks)) {
            $responses = TestHelpers::mocksToResponses($mocks, self::$mockBasePath);
        } else {
            $responses = [];
        }

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
        $this->setupOT();
        $this->setupBroadcasts(StreamMode::AUTO);
        $this->assertInstanceOf(Broadcast::class, $this->broadcast);

    }

    public function testCannotAddStreamToBroadcastInAutoMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/broadcast/BROADCASTID/get'
        ]]);

        $this->setupBroadcasts(StreamMode::AUTO);

        $this->broadcast->addStreamToBroadcast(
            '5dfds4-asdda4asf4',
            true,
            true
        );
    }

    public function testCannotAddStreamToBroadcastWithNoAudioAndVideo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/broadcast/BROADCASTID/get'
        ]]);

        $this->setupBroadcasts(StreamMode::MANUAL);

        $this->broadcast->addStreamToBroadcast(
            '5dfds4-asdda4asf4',
            false,
            false
        );
    }

    public function testCanAddStreamToBroadcast(): void
    {
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/broadcast/BROADCASTID/get'
        ]]);

        $this->setupBroadcasts(StreamMode::MANUAL);

        $return = $this->broadcast->addStreamToBroadcast(
            '5dfds4-asdda4asf4',
            true,
            true
        );
        $this->assertTrue($return);
    }

    public function testCanRemoveStreamFromBroadcast(): void
    {
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/broadcast/BROADCASTID/get'
        ]]);

        $this->setupBroadcasts(StreamMode::MANUAL);

        $return = $this->broadcast->removeStreamFromBroadcast(
            '5dfds4-asdda4asf4'
        );
        $this->assertTrue($return);
    }

    public function testCannotRemoveStreamFromBroadcastOnAuto(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/broadcast/BROADCASTID/get'
        ]]);

        $this->setupBroadcasts(StreamMode::AUTO);

        $return = $this->broadcast->removeStreamFromBroadcast(
            '5dfds4-asdda4asf4'
        );
    }

    public function testGetters(): void
    {
        $broadcastObject = new Broadcast($this->broadcastData, [
            'apiKey' => 'abc',
            'apiSecret' => 'efg',
            'client' => $this->client
        ]);

        $this->assertTrue($broadcastObject->hasAudio);
        $this->assertTrue($broadcastObject->hasVideo);
        $this->assertEquals('broadcast-1234b', $broadcastObject->multiBroadcastTag);
        $this->assertEquals('started', $broadcastObject->status);
        $this->assertNull($broadcastObject->wrongKey);
    }
}

