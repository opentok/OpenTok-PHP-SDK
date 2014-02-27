<?php

use OpenTok\Session;

class SessionTest extends PHPUnit_Framework_TestCase
{

    protected $session;
    protected $sessionId;
    protected $properties;

    public function setUp()
    {
        $this->sessionId = '';
        $this->properties = array();
        $this->session = new Session($this->sessionId, $this->properties);
    }

    public function testPropertiesExist()
    {
        $this->assertEquals($this->sessionId, $this->session->sessionId);
        $this->assertEquals($this->properties, $this->session->sessionProperties);
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
