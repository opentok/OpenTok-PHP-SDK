<?php

namespace OpenTokTest\Util;

use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use OpenTok\Exception\SignalAuthenticationException;
use OpenTok\Exception\SignalConnectionException;
use OpenTok\Exception\SignalNetworkConnectionException;
use OpenTok\Exception\SignalUnexpectedValueException;
use OpenTok\Util\Client;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;

class ClientTest extends TestCase
{
    public function testHandlesSignalErrorHandlesNoResponse()
    {
        $this->expectException(SignalNetworkConnectionException::class);
        $this->expectExceptionMessage('Unable to communicate with host');

        $mock = new MockHandler([
            new RequestException('Unable to communicate with host', new Request('GET', 'signals')),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleHttpClient(['handler' => $handlerStack]);

        $client = new Client();
        $client->configure('asdf', 'asdf', 'http://localhost/', ['client' => $guzzle]);
        $client->signal('sessionabcd', ['type' => 'foo', 'data' => 'bar'], 'connection1234');
    }

    public function testHandlesSignalErrorHandles400Response()
    {
        $this->expectException(SignalUnexpectedValueException::class);

        $mock = new MockHandler([
            $this->getResponse('signal-failure-payload', 400)
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleHttpClient(['handler' => $handlerStack]);

        $client = new Client();
        $client->configure('asdf', 'asdf', 'http://localhost/', ['client' => $guzzle]);
        $client->signal('sessionabcd', ['type' => 'foo', 'data' => 'bar'], 'connection1234');
    }

    public function testHandlesSignalErrorHandles403Response()
    {
        $this->expectException(SignalAuthenticationException::class);

        $mock = new MockHandler([
            $this->getResponse('signal-failure-invalid-token', 403)
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleHttpClient(['handler' => $handlerStack]);

        $client = new Client();
        $client->configure('asdf', 'asdf', 'http://localhost/', ['client' => $guzzle]);
        $client->signal('sessionabcd', ['type' => 'foo', 'data' => 'bar'], 'connection1234');
    }

    public function testHandlesSignalErrorHandles404Response()
    {
        $this->expectException(SignalConnectionException::class);
        $this->expectExceptionMessage('The client specified by the connectionId property is not connected to the session.');

        $mock = new MockHandler([
            $this->getResponse('signal-failure-no-clients', 404)
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleHttpClient(['handler' => $handlerStack]);

        $client = new Client();
        $client->configure('asdf', 'asdf', 'http://localhost/', ['client' => $guzzle]);
        $client->signal('sessionabcd', ['type' => 'foo', 'data' => 'bar'], 'connection1234');
    }

    public function testHandlesSignalErrorHandles413Response()
    {
        $this->expectException(SignalUnexpectedValueException::class);
        $this->expectExceptionMessage('The type string exceeds the maximum length (128 bytes), or the data string exceeds the maximum size (8 kB).');

        $mock = new MockHandler([
            $this->getResponse('signal-failure', 413)
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleHttpClient(['handler' => $handlerStack]);

        $client = new Client();
        $client->configure('asdf', 'asdf', 'http://localhost/', ['client' => $guzzle]);
        $client->signal('sessionabcd', ['type' => 'foo', 'data' => 'bar'], 'connection1234');
    }

    /**
     * Get the API response we'd expect for a call to the API.
     */
    protected function getResponse(string $type = 'success', int $status = 200): Response
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            fopen(__DIR__ . '/responses/' . $type . '.json', 'rb')
        );
    }
}
