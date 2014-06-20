<?php

use Guzzle\Plugin\Mock\MockPlugin;

use OpenTok\Archive;
use OpenTok\Util\Client;

class ArchiveTest extends PHPUnit_Framework_TestCase {

    // Fixtures
    protected $archiveJson;
    protected $API_KEY;
    protected $API_SECRET;

    protected $archive;
    protected $client;

    protected static $mockBasePath;

    public static function setUpBeforeClass()
    {
        self::$mockBasePath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'mock' . DIRECTORY_SEPARATOR;
    }

    public function setUp()
    {
        // Set up fixtures
        $this->archiveJson = array(
            'createdAt' => 1394394801000,
            'duration' => 0,
            'id' => '063e72a4-64b4-43c8-9da5-eca071daab89',
            'name' => 'showtime',
            'partnerId' => 854511,
            'reason' => '',
            'sessionId' => '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-',
            'size' => 0,
            'status' => 'started',
            'url' => null
        );
        $this->API_KEY = defined('API_KEY') ? API_KEY : '12345678';
        $this->API_SECRET = defined('API_SECRET') ? API_SECRET : '0123456789abcdef0123456789abcdef0123456789';

        $this->client = new Client();
        $this->archive = new Archive($this->archiveJson, array(
            'apiKey' => $this->API_KEY,
            'apiSecret' => $this->API_SECRET,
            'client' => $this->client
        ));
    }

    public function testInitializes()
    {
        // Arrange
        // Act
        // Assert
        $this->assertInstanceOf('OpenTok\Archive', $this->archive);
    }

    public function testReadsProperties()
    {
        $this->assertEquals($this->archiveJson['createdAt'], $this->archive->createdAt);
        $this->assertEquals($this->archiveJson['duration'], $this->archive->duration);
        $this->assertEquals($this->archiveJson['id'], $this->archive->id);
        $this->assertEquals($this->archiveJson['name'], $this->archive->name);
        $this->assertEquals($this->archiveJson['partnerId'], $this->archive->partnerId);
        $this->assertEquals($this->archiveJson['reason'], $this->archive->reason);
        $this->assertEquals($this->archiveJson['sessionId'], $this->archive->sessionId);
        $this->assertEquals($this->archiveJson['size'], $this->archive->size);
        $this->assertEquals($this->archiveJson['status'], $this->archive->status);
        $this->assertEquals($this->archiveJson['url'], $this->archive->url);
    }

    public function testStopsArchive()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/partner/APIKEY/archive/ARCHIVEID/stop'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        // Act
        $this->archive->stop();

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/partner/'.$this->API_KEY.'/archive/'.$this->archiveJson['id'].'/stop', $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $contentType = $request->getHeader('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeader('X-TB-PARTNER-AUTH');
        $this->assertNotEmpty($authString);
        $this->assertEquals($this->API_KEY.':'.$this->API_SECRET, $authString);

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/2.2.2', $userAgent->__toString());

        // TODO: test the properties of the actual archive object
        $this->assertEquals('stopped', $this->archive->status);

    }

     public function testDeletesArchive()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/partner/APIKEY/archive/ARCHIVEID/delete'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        // Act
        // TODO: should this test be run on an archive object whose fixture has status 'available'
        // instead of 'started'?
        $success = $this->archive->delete();

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('DELETE', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/partner/'.$this->API_KEY.'/archive/'.$this->archiveJson['id'], $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $contentType = $request->getHeader('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeader('X-TB-PARTNER-AUTH');
        $this->assertNotEmpty($authString);
        $this->assertEquals($this->API_KEY.':'.$this->API_SECRET, $authString);

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/2.2.2', $userAgent->__toString());

        $this->assertTrue($success);
        // TODO: assert that all properties of the archive object were cleared

    }

    public function testAllowsUnknownProperties()
    {
      // Set up fixtures
      $archiveJson = array(
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
      );
      $apiKey = defined('API_KEY') ? API_KEY : '12345678';
      $apiSecret = defined('API_SECRET') ? API_SECRET : '0123456789abcdef0123456789abcdef0123456789';

      $client = new Client();
      $archive = new Archive($this->archiveJson, array(
          'apiKey' => $this->API_KEY,
          'apiSecret' => $this->API_SECRET,
          'client' => $this->client
      ));

      $this->assertInstanceOf('OpenTok\Archive', $archive);
    }

    // TODO: test deleted archive can not be stopped or deleted again
}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
