<?php

namespace OpenTokTest;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use OpenTok\Archive;
use OpenTok\Broadcast;
use OpenTok\Exception\InvalidArgumentException;
use OpenTok\StreamMode;
use OpenTok\Util\Client;
use PHPUnit\Framework\TestCase;

class BroadcastTest extends TestCase
{

    // Fixtures
    protected $broadcastData;
    protected $API_KEY;
    protected $API_SECRET;

    protected $broadcast;
    protected $client;

    protected static $mockBasePath;

    public static function setUpBeforeClass(): void
    {
        self::$mockBasePath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'mock' . DIRECTORY_SEPARATOR;
    }

    public function setupBroadcasts($streamMode)
    {
        // Set up fixtures
        $this->broadcastData = array(
            'id' => '063e72a4-64b4-43c8-9da5-eca071daab89',
            'createdAt' => 1394394801000,
            'updatedAt' => 1394394801000,
            'partnerId' => 685,
            'sessionId' => '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-',
            'layout' => [
                'type' => 'custom',
                'stylesheet' => 'a layout stylesheet',
                'streenshareType' => 'some options'
            ],
            'maxDuration' => 5400,
            'resolution' => '640x480',
            'streamMode' => $streamMode
        );

        $this->broadcast = new Broadcast($this->broadcastData, array(
            'apiKey' => $this->API_KEY,
            'apiSecret' => $this->API_SECRET,
            'client' => $this->client
        ));
    }

    private function setupOTWithMocks($mocks)
    {
        $this->API_KEY = defined('API_KEY') ? API_KEY : '12345678';
        $this->API_SECRET = defined('API_SECRET') ? API_SECRET : '0123456789abcdef0123456789abcdef0123456789';

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

    public function testInitializes()
    {
        // Arrange
        $this->setupOT();
        $this->setupBroadcasts(StreamMode::AUTO);
        // Act
        // Assert
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
}

