<?php
class OpenTokTest extends PHPUnit_Framework_TestCase
{
    public function testCanBeInitialized()
    {
        // Arrange
        // TODO: separate test suites or groups for integration tests and unit tests
        $opentok = new OpenTok\OpenTok(API_KEY, API_SECRET);

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
        // TODO: assert that a POST request matching the expectations was sent
        $this->assertInstanceOf('OpenTok\Session', $session);
        // TODO: assert that $session->sessionId matches the format of a sessionId
        // TODO: assert that the length for the $session-sessionId is reasonable
        // TODO: decode the $sessionId and look for the correct parameters (apiKey, apiSecret, etc)

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
        $this->assertInternalType('string', $token);
        // TODO: assert the length of the token is correct
        // TODO: decode the token and verify its parts (including its signature)
    }
}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
