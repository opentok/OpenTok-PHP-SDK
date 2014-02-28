<?php

use OpenTok\Session;

class SessionTest extends PHPUnit_Framework_TestCase
{

    protected $session;
    protected $sessionId;

    public function setUp()
    {
        $this->sessionId = 'SESSIONID';
        $this->session = new Session($this->sessionId);
    }

    public function testGettingSessionId()
    {
        $this->assertEquals($this->sessionId, $this->session->getSessionId());
    }

    public function testCastingToString()
    {
        $this->assertEquals($this->sessionId, (string)$this->session);
    }
}
