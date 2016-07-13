<?php

use OpenTok\OpenTok;
use OpenTok\Session;
use OpenTok\MediaMode;
use OpenTok\ArchiveMode;

use OpenTok\TestHelpers;

class SessionTest extends PHPUnit_Framework_TestCase
{

    protected $API_KEY;
    protected $API_SECRET;
    protected $opentok;

    public function setUp()
    {
        $this->API_KEY = defined('API_KEY') ? API_KEY : '12345678';
        $this->API_SECRET = defined('API_SECRET') ? API_SECRET : '0123456789abcdef0123456789abcdef0123456789';
        $this->opentok = new OpenTok($this->API_KEY, $this->API_SECRET);
    }

    public function testSessionWithId()
    {
        $sessionId = 'SESSIONID';
        $session = new Session($this->opentok, $sessionId);
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals(MediaMode::ROUTED, $session->getMediaMode());
        $this->assertEmpty($session->getLocation());
    }

    public function testSessionWithIdAndLocation()
    {
        $sessionId = 'SESSIONID';
        $location = '12.34.56.78';
        $session = new Session($this->opentok, $sessionId, array( 'location' => $location ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals(MediaMode::ROUTED, $session->getMediaMode());
        $this->assertEquals($location, $session->getLocation());
    }

    public function testSessionWithIdAndMediaMode()
    {
        $sessionId = 'SESSIONID';
        $mediaMode = MediaMode::RELAYED;
        $session = new Session($this->opentok, $sessionId, array( 'mediaMode' => $mediaMode ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($mediaMode, $session->getMediaMode());
        $this->assertEmpty($session->getLocation());

        $mediaMode = MediaMode::ROUTED;
        $session = new Session($this->opentok, $sessionId, array( 'mediaMode' => $mediaMode ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($mediaMode, $session->getMediaMode());
        $this->assertEmpty($session->getLocation());
    }

    public function testSessionWithIdAndLocationAndMediaMode()
    {
        $sessionId = 'SESSIONID';
        $location = '12.34.56.78';
        $mediaMode = MediaMode::RELAYED;
        $session = new Session($this->opentok, $sessionId, array( 'location' => $location, 'mediaMode' => $mediaMode ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($mediaMode, $session->getMediaMode());
        $this->assertEquals($location, $session->getLocation());

        $mediaMode = MediaMode::ROUTED;
        $session = new Session($this->opentok, $sessionId, array( 'location' => $location, 'mediaMode' => $mediaMode ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($mediaMode, $session->getMediaMode());
        $this->assertEquals($location, $session->getLocation());
    }

    public function testSessionWithArchiveMode()
    {
        $sessionId = 'SESSIONID';
        $archiveMode = ArchiveMode::ALWAYS;
        $session = new Session($this->opentok, $sessionId, array( 'archiveMode' => $archiveMode ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($archiveMode, $session->getArchiveMode());

        $archiveMode = ArchiveMode::MANUAL;
        $session = new Session($this->opentok, $sessionId, array( 'archiveMode' => $archiveMode ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($archiveMode, $session->getArchiveMode());
    }

    /**
     * @dataProvider badParameterProvider
     * @expectedException InvalidArgumentException
     */
    public function testInitializationWithBadParams($sessionId, $props)
    {
        if (!$props || empty($props)) {
            $session = new Session($this->opentok, $sessionId);
        } else {
            $session = new Session($this->opentok, $sessionId, $props);
        }
    }

    public function badParameterProvider()
    {
        return array(
            array(array(), array()),
            array('SESSIONID', array( 'location' => 'NOTALOCATION') ),
            array('SESSIONID', array( 'mediaMode' => 'NOTAMODE' ) ),
            array('SESSIONID', array( 'location' => '127.0.0.1', 'mediaMode' => 'NOTAMODE' ) ),
            array('SESSIONID', array( 'location' => 'NOTALOCATION', 'mediaMode' => MediaMode::RELAYED ) )
        );
    }

    public function testInitializationWithExtraneousParams()
    {
        $sessionId = 'SESSIONID';
        $session = new Session($this->opentok, $sessionId, array( 'notrealproperty' => 'notrealvalue' ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEmpty($session->getLocation());
        $this->assertEquals(MediaMode::ROUTED, $session->getMediaMode());
    }

    public function testCastingToString()
    {
        $sessionId = 'SESSIONID';
        $session = new Session($this->opentok, $sessionId);
        $this->assertEquals($sessionId, (string)$session);
    }

    public function testGeneratesToken()
    {
        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);
        $session = new Session($opentok, $sessionId);

        $token = $session->generateToken();

        $this->assertInternalType('string', $token);
        $decodedToken = TestHelpers::decodeToken($token, $bogusApiSecret);
        $this->assertEquals($sessionId, $decodedToken->sub);
        $this->assertEquals($bogusApiKey, $decodedToken->iss);
        $this->assertNotEmpty($decodedToken->jti);
        $this->assertNotEmpty($decodedToken->iat);
        // TODO: should all tokens have a role of publisher even if this wasn't specified?
        //$this->assertNotEmpty($decodedToken->role);
        // TODO: should all tokens have a default expire time even if it wasn't specified?
        //$this->assertNotEmpty($decodedToken->exp);
    }
}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
