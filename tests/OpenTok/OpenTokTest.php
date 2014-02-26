<?php
class OpenTokTest extends PHPUnit_Framework_TestCase
{
    public function testCanBeInitialized()
    {
        // Arrange
        // TODO: read an apikey and apisecret from config
        $opentok = new OpenTok\OpenTok('apikey', 'apisecret');

        // Act

        // Assert
        $this->assertInstanceOf('OpenTok\OpenTok', $opentok);

        return $opentok;
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Warning
     */
    public function testFailsOnInvalidInitialization()
    {
        // Arrange
        $opentok = new OpenTok\OpenTok();

        // Act

        // Assert
    }

    /**
     * @depends testCanBeInitialized
     */
    public function testCreatesSession(OpenTok\OpenTok $opentok)
    {
        // Arrange

        // Act
        // TODO: decouple from actually making a web request
        $session = $opentok->createSession('127.0.0.1');

        // Assert
        $this->assertInstanceOf('OpenTok\Session', $session);
        // TODO: assert that a POST request matching the expectations was sent

        return $session;
    }

    /**
     * @depends testCanBeInitialized
     * @depends testCreatesSession
     */
    public function testGeneratesToken(OpenTok\OpenTok $opentok, OpenTok\Session $session) {
        // Arrange

        // Act
        $token = $opentok->generateToken($session->sessionId);

        // Assert
        $this->assertInstanceOf('string', $token);
    }
}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
