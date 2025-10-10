<?php

namespace OpenTokTest\Util;

use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use OpenTok\Exception\SignalAuthenticationException;
use OpenTok\Exception\SignalConnectionException;
use OpenTok\Exception\SignalUnexpectedValueException;
use OpenTok\Util\Client;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function testCanAddAudioStreamToWebsocket(): void
    {
        $mock = new MockHandler([
            $this->getResponse('connect')
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleHttpClient(['handler' => $handlerStack]);

        $client = new Client();
        $client->configure('asdf', 'asdf', 'http://localhost/', ['client' => $guzzle]);

        $websocketDummy = [
            'uri' => 'ws://test'
        ];

        $response = $client->connectAudio('ddd', 'sarar55r', $websocketDummy);
        $this->assertEquals('063e72a4-64b4-43c8-9da5-eca071daab89', $response['id']);
        $this->assertEquals('7aebb3a4-3d86-4962-b317-afb73e05439d', $response['connectionId']);
    }

    public function testHandlesSignalErrorHandles400Response(): void
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

    public function testHandlesSignalErrorHandles403Response(): void
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

    public function testHandlesSignalErrorHandles404Response(): void
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

    public function testHandlesSignalErrorHandles413Response(): void
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
