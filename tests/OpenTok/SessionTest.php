<?php

use OpenTok\OpenTok;
use OpenTok\Session;

use OpenTok\TestHelpers;

class SessionTest extends PHPUnit_Framework_TestCase
{

    protected $API_KEY;
    protected $API_SECRET;
    protected $opentok;

    public function setUp()
    {
        $this->API_KEY = (null !== API_KEY) ? API_KEY : '12345678';
        $this->API_SECRET = (null !== API_SECRET) ? API_SECRET : '0123456789abcdef0123456789abcdef0123456789';
        $this->opentok = new OpenTok($this->API_KEY, $this->API_SECRET);
    }

    public function testIsValidLocation()
    {
        $this->assertTrue(Session::isValidLocation('12.34.56.78'));
        $this->assertFalse(Session::isValidLocation('12.34'));
    }

    public function testSessionWithId()
    {
        $sessionId = 'SESSIONID';
        $session = new Session($this->opentok, $sessionId);
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals(false, $session->getP2p());
        $this->assertEmpty($session->getLocation());
    }

    public function testSessionWithIdAndLocation()
    {
        $sessionId = 'SESSIONID';
        $location = '12.34.56.78';
        $session = new Session($this->opentok, $sessionId, array( 'location' => $location ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals(false, $session->getP2p());
        $this->assertEquals($location, $session->getLocation());
    }

    public function testSessionWithIdAndP2p()
    {
        $sessionId = 'SESSIONID';
        $p2p = true;
        $session = new Session($this->opentok, $sessionId, array( 'p2p' => $p2p ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($p2p, $session->getP2p());
        $this->assertEmpty($session->getLocation());

        $p2p = false;
        $session = new Session($this->opentok, $sessionId, array( 'p2p' => $p2p ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($p2p, $session->getP2p());
        $this->assertEmpty($session->getLocation());
    }

    public function testSessionWithIdAndLocationAndP2p()
    {
        $sessionId = 'SESSIONID';
        $location = '12.34.56.78';
        $p2p = true;
        $session = new Session($this->opentok, $sessionId, array( 'location' => $location, 'p2p' => $p2p ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($p2p, $session->getP2p());
        $this->assertEquals($location, $session->getLocation());

        $p2p = false;
        $session = new Session($this->opentok, $sessionId, array( 'location' => $location, 'p2p' => $p2p ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($p2p, $session->getP2p());
        $this->assertEquals($location, $session->getLocation());
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
            array('SESSIONID', array( 'p2p' => 'NOTABOOL' ) ),
            array('SESSIONID', array( 'location' => '127.0.0.1', 'p2p' => 'NOTABOOL' ) ),
            array('SESSIONID', array( 'location' => 'NOTALOCATION', 'p2p' => true ) )
        );
    }

    public function testInitializationWithExtraneousParams()
    {
        $sessionId = 'SESSIONID';
        $session = new Session($this->opentok, $sessionId, array( 'notrealproperty' => 'notrealvalue' ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEmpty($session->getLocation());
        $this->assertEmpty($session->getP2p());
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
        $decodedToken = TestHelpers::decodeToken($token);
        $this->assertEquals($sessionId, $decodedToken['session_id']);
        $this->assertEquals($bogusApiKey, $decodedToken['partner_id']);
        $this->assertNotEmpty($decodedToken['nonce']);
        $this->assertNotEmpty($decodedToken['create_time']);
        $this->assertArrayNotHasKey('connection_data', $decodedToken);
        // TODO: should all tokens have a role of publisher even if this wasn't specified?
        //$this->assertNotEmpty($decodedToken['role']);
        // TODO: should all tokens have a default expire time even if it wasn't specified?
        //$this->assertNotEmpty($decodedToken['expire_time']);

        $this->assertNotEmpty($decodedToken['sig']);
        $this->assertEquals(hash_hmac('sha1', $decodedToken['dataString'], $bogusApiSecret), $decodedToken['sig']);
    }
}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
