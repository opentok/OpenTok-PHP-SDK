<?php

use OpenTok\Session;

class SessionTest extends PHPUnit_Framework_TestCase
{

    public function testIsValidLocation()
    {
        $this->assertTrue(Session::isValidLocation('12.34.56.78'));
        $this->assertFalse(Session::isValidLocation('12.34'));
    }

    public function testSessionWithId()
    {
        $sessionId = 'SESSIONID';
        $session = new Session($sessionId);
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals(false, $session->getP2p());
        $this->assertEmpty($session->getLocation());
    }

    public function testSessionWithIdAndLocation()
    {
        $sessionId = 'SESSIONID';
        $location = '12.34.56.78';
        $session = new Session($sessionId, array( 'location' => $location ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals(false, $session->getP2p());
        $this->assertEquals($location, $session->getLocation());
    }

    public function testSessionWithIdAndP2p()
    {
        $sessionId = 'SESSIONID';
        $p2p = true;
        $session = new Session($sessionId, array( 'p2p' => $p2p ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($p2p, $session->getP2p());
        $this->assertEmpty($session->getLocation());

        $p2p = false;
        $session = new Session($sessionId, array( 'p2p' => $p2p ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($p2p, $session->getP2p());
        $this->assertEmpty($session->getLocation());
    }

    public function testSessionWithIdAndLocationAndP2p()
    {
        $sessionId = 'SESSIONID';
        $location = '12.34.56.78';
        $p2p = true;
        $session = new Session($sessionId, array( 'location' => $location, 'p2p' => $p2p ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($p2p, $session->getP2p());
        $this->assertEquals($location, $session->getLocation());

        $p2p = false;
        $session = new Session($sessionId, array( 'location' => $location, 'p2p' => $p2p ));
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
            $session = new Session($sessionId);
        } else {
            $session = new Session($sessionId, $props);
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
        $session = new Session($sessionId, array( 'notrealproperty' => 'notrealvalue' ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEmpty($session->getLocation());
        $this->assertEmpty($session->getP2p());
    }

    public function testCastingToString()
    {
        $sessionId = 'SESSIONID';
        $session = new Session($sessionId);
        $this->assertEquals($sessionId, (string)$session);
    }
}
