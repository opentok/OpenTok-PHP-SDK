<?php
class OpenTokTest extends Guzzle\Tests\GuzzleTestCase
{
    protected $API_KEY;
    protected $API_SECRET;
    protected $opentok;
    protected $client;

    public function setUp()
    {
        // TODO: define the fake credentials somewhere outside the test code
        $this->API_KEY = API_KEY || '12345678';
        $this->API_SECRET = API_SECRET || '0123456789abcdef0123456789abcdef0123456789';

        $this->client = new Guzzle\Http\Client();
        $this->opentok = new OpenTok\OpenTok($this->API_KEY, $this->API_SECRET, array('client' => $this->client));

    }

    public function testCanBeInitialized()
    {
        // Arrange
        // Act
        // Assert
        $this->assertInstanceOf('OpenTok\OpenTok', $this->opentok);
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

    public function testCreatesSession()
    {
        // Arrange
        $this->setMockResponse($this->client, '/session/create/no-p2p_location-127.0.0.1');

        // Act
        $session = $this->opentok->createSession('127.0.0.1', array( 'p2p.preference' => 'disabled' ) );

        // Assert
        $requests = self::$mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEqual('POST', strtoupper($request->getMethod()));
        $this->assertEqual('/session/create', $request->getPath());
        $this->assertEqual('api.opentok.com', $request->getHost());
        $this->assertEqual('https', $request->getScheme());

        $authString = $request->getHeader('X-TB-PARTNER-AUTH');
        $this->assertNotEmpty($authString);
        $this->assertEquals($this->API_KEY.':'.$this->API_SECRET, $authString);

        $location = $request->getPostField('location');
        $this->assertEquals('127.0.0.1', $location);

        $p2p_preference = $request->getPostField('p2p.preference');
        $this->assertEquals('disabled', $p2p_preference);

        $this->assertInstanceOf('OpenTok\Session', $session);
        // TODO: assert that $session->sessionId matches the format of a sessionId
        // TODO: assert that the length for the $session-sessionId is reasonable
        // TODO: decode the $sessionId and look for the correct parameters (apiKey, apiSecret, etc)

        return $session;
    }

    /**
     * @depends testCreatesSession
     */
    public function testGeneratesToken(OpenTok\Session $session) {
        // Arrange

        // Act
        $token = $this->opentok->generateToken($session->sessionId);

        // Assert
        $this->assertInternalType('string', $token);
        // TODO: assert the length of the token is correct
        // TODO: decode the token and verify its parts (including its signature)
    }
}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
