<?php

namespace OpenTok\Util;

use Composer\InstalledVersions;
use Exception as GlobalException;
use GuzzleHttp\Utils;
use OpenTok\Layout;
use Firebase\JWT\JWT;
use OpenTok\MediaMode;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use OpenTok\Exception\Exception;
use OpenTok\Exception\DomainException;
use Psr\Http\Message\RequestInterface;
use OpenTok\Exception\ArchiveException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use OpenTok\Exception\BroadcastException;
use GuzzleHttp\Exception\RequestException;
use OpenTok\Exception\ArchiveDomainException;
use OpenTok\Exception\AuthenticationException;
use OpenTok\Exception\BroadcastDomainException;
use OpenTok\Exception\UnexpectedValueException;
use OpenTok\Exception\SignalConnectionException;
use OpenTok\Exception\SignalAuthenticationException;
use OpenTok\Exception\ArchiveAuthenticationException;
use OpenTok\Exception\SignalUnexpectedValueException;
use OpenTok\Exception\ArchiveUnexpectedValueException;
use OpenTok\Exception\BroadcastAuthenticationException;
use OpenTok\Exception\SignalNetworkConnectionException;
use OpenTok\Exception\BroadcastUnexpectedValueException;
use OpenTok\Exception\ForceDisconnectConnectionException;

use OpenTok\Exception\ForceDisconnectAuthenticationException;
use OpenTok\Exception\ForceDisconnectUnexpectedValueException;
use Vonage\JWT\TokenGenerator;

/**
 * @internal
 */
class Client
{
    public const OPENTOK_SDK_USER_AGENT_IDENTIFIER = 'OpenTok-PHP-SDK/';

    protected $apiKey;
    protected $apiSecret;
    protected $applicationId = null;
    protected $privateKeyPath = null;
    protected $configured = false;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * @var array|mixed
     */
    public $options;

    public function configure($apiKey, $apiSecret, $apiUrl, $options = array())
    {
        $this->options = $options;
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;

        if (array_key_exists('application_id', $options) || array_key_exists('private_key_path', $options)) {
            if (!is_null($options['application_id']) && !is_null($options['private_key_path'])) {
                $this->applicationId = $options['application_id'];
                $this->privateKeyPath = $options['private_key_path'];
                $apiUrl = 'https://video.api.vonage.com';
            }
        }

        if (isset($this->options['client'])) {
            $this->client = $options['client'];
        } else {
            $clientOptions = [
                'base_uri' => $apiUrl,
                'headers' => [
                    'User-Agent' => $this->buildUserAgentString()
                ],
            ];

            if (!empty($options['timeout'])) {
                $clientOptions['timeout'] = $options['timeout'];
            }

            if (empty($options['handler'])) {
                $handlerStack = HandlerStack::create();
            } else {
                $handlerStack = $options['handler'];
            }
            $clientOptions['handler'] = $handlerStack;

            $handler = Middleware::mapRequest(function (RequestInterface $request) {
                $authHeader = $this->createAuthHeader();
                return $request->withHeader('X-OPENTOK-AUTH', $authHeader);
            });
            $handlerStack->push($handler);

            $this->client = new \GuzzleHttp\Client($clientOptions);
        }

        $this->configured = true;
    }

    private function buildUserAgentString(): string
    {
        $userAgent = [];

        $userAgent[] = self::OPENTOK_SDK_USER_AGENT_IDENTIFIER
                       . InstalledVersions::getVersion('opentok/opentok');

        $userAgent[] = 'php/' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

        if (isset($this->options['app'])) {
            $app = $this->options['app'];
            if (isset($app['name'], $app['version'])) {
                // You must use both of these for custom agent strings
                $userAgent[] = $app['name'] . '/' . $app['version'];
            }
        }

        return implode(' ', $userAgent);
    }

    public function isConfigured()
    {
        return $this->configured;
    }

    private function createAuthHeader()
    {
        if (!is_null($this->applicationId) && !is_null($this->privateKeyPath)) {
            $projectRoot = dirname(__DIR__, 3); // Adjust the number of dirname() calls if necessary to match your
            // project structure.
            $privateKeyFullPath = $projectRoot . DIRECTORY_SEPARATOR . $this->privateKeyPath;
            $tokenGenerator = new TokenGenerator($this->applicationId, file_get_contents($privateKeyFullPath));
            return $tokenGenerator->generate();
        }

        $token = array(
            'ist' => 'project',
            'iss' => $this->apiKey,
            'iat' => time(), // this is in seconds
            'exp' => time() + (5 * 60),
            'jti' => uniqid('', true),
        );

        return JWT::encode($token, $this->apiSecret, 'HS256');
    }

    // General API Requests

    public function createSession($options)
    {
        $request = new Request('POST', '/session/create');
        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug(),
                'form_params' => $this->postFieldsForOptions($options)
            ]);
            $sessionXml = $this->getResponseXml($response);
        } catch (\RuntimeException $e) {
            // TODO: test if we have a parse exception and handle it, otherwise throw again
            throw $e;
        } catch (\Exception $e) {
            $this->handleException($e);
            return;
        }
        return $sessionXml;
    }

    // Formerly known as $response->xml() from guzzle 3
    private function getResponseXml($response)
    {
        $errorMessage = null;
        $internalErrors = libxml_use_internal_errors(true);
        if (\PHP_VERSION_ID < 80000) {
            $disableEntities = libxml_disable_entity_loader(true);
        }
        libxml_clear_errors();
        try {
            $body = $response->getBody();
            $xml = new \SimpleXMLElement((string) $body ?: '<root />', LIBXML_NONET);
            if ($error = libxml_get_last_error()) {
                $errorMessage = $error->message;
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
        }
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);
        if (\PHP_VERSION_ID < 80000) {
            libxml_disable_entity_loader($disableEntities);
        }
        if ($errorMessage) {
            throw new \RuntimeException('Unable to parse response body into XML: ' . $errorMessage);
        }
        return $xml;
    }

    public function startRender($payload)
    {
        $request = new Request('POST', '/v2/project/' . $this->apiKey . '/render');

        try {
            $response = $this->client->send($request, $payload);
            $renderJson = $response->getBody()->getContents();
        } catch (\Exception $e) {
            $this->handleRenderException($e);
        }

        return $renderJson;
    }

    public  function stopRender($renderId): bool
    {
        $request = new Request('DELETE', '/v2/project/' . $this->apiKey . '/render/' . $renderId);

        try {
            $response = $this->client->send($request);
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getRender($renderId): string
    {
        $request = new Request('POST', '/v2/project/' . $this->apiKey . '/render/' . $renderId);

        try {
            $response = $this->client->send($request);
            $renderJson = $response->getBody()->getContents();
        } catch (\Exception $e) {
            $this->handleRenderException($e);
        }

        return $renderJson;
    }

    public function listRenders($query)
    {
        $request = new Request('GET', '/v2/project/' . $this->apiKey . '/render?' . http_build_query($query));

        try {
            $response = $this->client->send($request);
            $renderJson = $response->getBody()->getContents();
        } catch (\Exception $e) {
            $this->handleRenderException($e);
        }

        return json_decode($renderJson, true);
    }

    public function startArchive(string $sessionId, array $options = []): array
    {
        $request = new Request('POST', '/v2/project/' . $this->apiKey . '/archive');

        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug(),
                'json' => array_merge(
                    ['sessionId' => $sessionId],
                    $options
                )
            ]);
            $archiveJson = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->handleArchiveException($e);
        }
        return $archiveJson;
    }

    public function stopArchive($archiveId)
    {
        // set up the request
        $request = new Request(
            'POST',
            '/v2/project/' . $this->apiKey . '/archive/' . $archiveId . '/stop',
            ['Content-Type' => 'application/json']
        );

        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug()
            ]);
            $archiveJson = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            // TODO: what happens with JSON parse errors?
            $this->handleArchiveException($e);
        }
        return $archiveJson;
    }

    public function getArchive($archiveId)
    {
        $request = new Request(
            'GET',
            '/v2/project/' . $this->apiKey . '/archive/' . $archiveId
        );
        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug()
            ]);
            $archiveJson = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->handleException($e);
            return;
        }
        return $archiveJson;
    }

    public function addStreamToArchive(string $archiveId, string $streamId, bool $hasAudio, bool $hasVideo): bool
    {
        Validators::validateArchiveId($archiveId);
        Validators::validateStreamId($streamId);

        $requestBody = [
            'addStream' => $streamId,
            'hasAudio' => $hasAudio,
            'hasVideo' => $hasVideo
        ];

        $request = new Request(
            'PATCH',
            '/v2/project/' . $this->apiKey . '/archive/' . $archiveId . '/streams',
            ['Content-Type' => 'application/json'],
            json_encode($requestBody)
        );

        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug()
            ]);

            if ($response->getStatusCode() !== 204) {
                json_decode($response->getBody(), true);
            }
        } catch (\Exception $e) {
            $this->handleException($e);
            return false;
        }

        return true;
    }

    public function removeStreamFromArchive(string $archiveId, string $streamId): bool
    {
        Validators::validateArchiveId($archiveId);
        Validators::validateStreamId($streamId);

        $requestBody = [
            'removeStream' => $streamId,
        ];

        $request = new Request(
            'PATCH',
            '/v2/project/' . $this->apiKey . '/archive/' . $archiveId . '/streams',
            ['Content-Type' => 'application/json'],
            json_encode($requestBody)
        );

        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug()
            ]);

            if ($response->getStatusCode() !== 204) {
                json_decode($response->getBody(), true);
            }
        } catch (\Exception $e) {
            $this->handleException($e);
            return false;
        }

        return true;
    }

    public function deleteArchive($archiveId)
    {
        $request = new Request(
            'DELETE',
            '/v2/project/' . $this->apiKey . '/archive/' . $archiveId,
            ['Content-Type' => 'application/json']
        );
        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug()
            ]);
            if ($response->getStatusCode() != 204) {
                json_decode($response->getBody(), true);
            }
        } catch (\Exception $e) {
            $this->handleException($e);
            return false;
        }
        return true;
    }

    public function forceDisconnect($sessionId, $connectionId)
    {
        $request = new Request(
            'DELETE',
            '/v2/project/' . $this->apiKey . '/session/' . $sessionId . '/connection/' . $connectionId,
            ['Content-Type' => 'application/json']
        );
        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug()
            ]);
            if ($response->getStatusCode() != 204) {
                json_decode($response->getBody(), true);
            }
        } catch (\Exception $e) {
            $this->handleForceDisconnectException($e);
            return false;
        }
        return true;
    }

    public function listArchives($offset, $count, $sessionId)
    {
        $request = new Request('GET', '/v2/project/' . $this->apiKey . '/archive');
        $queryParams = [];
        if ($offset != 0) {
            $queryParams['offset'] = $offset;
        }
        if (!empty($count)) {
            $queryParams['count'] = $count;
        }
        if (!empty($sessionId)) {
            $queryParams['sessionId'] = $sessionId;
        }
        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug(),
                'query' => $queryParams
            ]);
            $archiveListJson = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->handleException($e);
            return;
        }
        return $archiveListJson;
    }

    public function startBroadcast(string $sessionId, array $options): array
    {
        $request = new Request(
            'POST',
            '/v2/project/' . $this->apiKey . '/broadcast'
        );

        $optionsJson = [
            'sessionId' => $sessionId,
            'layout' => $options['layout']->jsonSerialize()
        ];
        unset($options['layout']);
        $optionsJson = array_merge($optionsJson, $options);

        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug(),
                'json' => $optionsJson
            ]);
            $broadcastJson = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->handleBroadcastException($e);
        }
        return $broadcastJson;
    }

    public function stopBroadcast($broadcastId)
    {
        $request = new Request(
            'POST',
            '/v2/project/' . $this->apiKey . '/broadcast/' . $broadcastId . '/stop',
            ['Content-Type' => 'application/json']
        );

        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug()
            ]);
            $broadcastJson = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->handleBroadcastException($e);
        }
        return $broadcastJson;
    }

    public function getBroadcast($broadcastId)
    {
        $request = new Request(
            'GET',
            '/v2/project/' . $this->apiKey . '/broadcast/' . $broadcastId
        );
        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug()
            ]);
            $broadcastJson = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->handleBroadcastException($e);
        }
        return $broadcastJson;
    }

    public function addStreamToBroadcast(string $broadcastId, string $streamId, bool $hasAudio, bool $hasVideo): bool
    {
        Validators::validateBroadcastId($broadcastId);
        Validators::validateStreamId($streamId);

        $requestBody = [
            'addStream' => $streamId,
            'hasAudio' => $hasAudio,
            'hasVideo' => $hasVideo
        ];

        $request = new Request(
            'PATCH',
            '/v2/project/' . $this->apiKey . '/broadcast/' . $broadcastId . '/streams',
            ['Content-Type' => 'application/json'],
            json_encode($requestBody)
        );

        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug()
            ]);

            if ($response->getStatusCode() !== 204) {
                json_decode($response->getBody(), true);
            }
        } catch (\Exception $e) {
            $this->handleException($e);
            return false;
        }

        return true;
    }

    public function removeStreamFromBroadcast(string $broadcastId, string $streamId): bool
    {
        Validators::validateBroadcastId($broadcastId);
        Validators::validateStreamId($streamId);

        $requestBody = [
            'removeStream' => $streamId,
        ];

        $request = new Request(
            'PATCH',
            '/v2/project/' . $this->apiKey . '/archive/' . $broadcastId . '/streams',
            ['Content-Type' => 'application/json'],
            json_encode($requestBody)
        );

        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug()
            ]);

            if ($response->getStatusCode() !== 204) {
                json_decode($response->getBody(), true);
            }
        } catch (\Exception $e) {
            $this->handleException($e);
            return false;
        }

        return true;
    }

    public function getLayout($resourceId, $resourceType = 'broadcast')
    {
        $request = new Request(
            'GET',
            '/v2/project/' . $this->apiKey . '/' . $resourceType . '/' . $resourceId . '/layout'
        );
        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug()
            ]);
            $layoutJson = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->handleException($e);
        }
        return $layoutJson;
    }

    public function updateLayout(string $resourceId, Layout $layout, string $resourceType = 'broadcast'): void
    {
        $request = new Request(
            'PUT',
            '/v2/project/' . $this->apiKey . '/' . $resourceType . '/' . $resourceId . '/layout'
        );
        try {
            $this->client->send($request, [
                'debug' => $this->isDebug(),
                'json' => $layout->toArray()
            ]);
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    public function setArchiveLayout(string $archiveId, Layout $layout): void
    {
        $request = new Request(
            'PUT',
            '/v2/project/' . $this->apiKey . '/archive/' . $archiveId . '/layout'
        );
        try {
            $this->client->send($request, [
                'debug' => $this->isDebug(),
                'json' => $layout->toArray()
            ]);
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    public function updateStream($sessionId, $streamId, $properties)
    {
        $request = new Request(
            'PUT',
            '/v2/project/' . $this->apiKey . '/session/' . $sessionId . '/stream/' . $streamId
        );
        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug(),
                'json' => $properties
            ]);
            if ($response->getStatusCode() != 204) {
                json_decode($response->getBody(), true);
            }
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    public function getStream($sessionId, $streamId)
    {
        $request = new Request(
            'GET',
            '/v2/project/' . $this->apiKey . '/session/' . $sessionId . '/stream/' . $streamId
        );

        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug()
            ]);
            $streamJson = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->handleException($e);
            return;
        }
        return $streamJson;
    }

    public function listStreams($sessionId)
    {
        $request = new Request(
            'GET',
            '/v2/project/' . $this->apiKey . '/session/' . $sessionId . '/stream/'
        );
        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug(),
            ]);
            $streamListJson = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->handleException($e);
            return;
        }
        return $streamListJson;
    }

    public function setStreamClassLists($sessionId, $payload)
    {
        $itemsPayload = array(
            'items' => $payload
        );
        $request = new Request(
            'PUT',
            'v2/project/' . $this->apiKey . '/session/' . $sessionId . '/stream'
        );

        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug(),
                'json' => $itemsPayload
            ]);
            if ($response->getStatusCode() != 200) {
                json_decode($response->getBody(), true);
            }
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * @param string $sessionId
     * @param string $token
     * @param string $sipUri
     * @param array{secure: bool, headers?: array<string, string>, auth?: array{username: string, password: string}, from?: string, video?: boolean, streams?: array} $options
     * @return array{id: string, streamId: string, connectId: string}
     * @throws AuthenticationException
     * @throws DomainException
     * @throws UnexpectedValueException
     * @throws GlobalException
     * @throws GuzzleException
     */
    public function dial($sessionId, $token, $sipUri, $options)
    {
        $body = array(
            'sessionId' => $sessionId,
            'token' => $token,
            'sip' => array(
                'uri' => $sipUri,
                'secure' => $options['secure'],
                'observeForceMute' => $options['observeForceMute']
            )
        );

        if (array_key_exists('headers', $options) && count($options['headers']) > 0) {
            $body['sip']['headers'] = $options['headers'];
        }

        if (array_key_exists('auth', $options)) {
            $body['sip']['auth'] = $options['auth'];
        }

        if (array_key_exists('from', $options)) {
            $body['sip']['from'] = $options['from'];
        }

        if (array_key_exists('video', $options)) {
            $body['sip']['video'] = (bool) $options['video'];
        }

        if (array_key_exists('streams', $options)) {
            $body['sip']['streams'] = $options['streams'];
        }

        // set up the request
        $request = new Request('POST', '/v2/project/' . $this->apiKey . '/call');

        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug(),
                'json' => $body
            ]);
            $sipJson = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->handleException($e);
        }

        return $sipJson;
    }

    public function playDTMF(string $sessionId, string $digits, string $connectionId = null): void
    {
        $route = sprintf('/v2/project/%s/session/%s/play-dtmf', $this->apiKey, $sessionId);
        if ($connectionId) {
            $route = sprintf(
                '/v2/project/%s/session/%s/connection/%s/play-dtmf',
                $this->apiKey,
                $sessionId,
                $connectionId
            );
        }

        $request = new Request('POST', $route);
        try {
            $this->client->send($request, [
                'debug' => $this->isDebug(),
                'json' => [
                    'digits' => $digits
                ]
            ]);
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Signal either an entire session or a specific connection in a session
     *
     * @param string $sessionId ID of the session to send the signal to
     * @param array{type: string, data: mixed} $payload Signal payload to send
     * @param string $connectionId ID of the connection to send the signal to
     *
     * @todo Mark $payload as required, as you cannot send an empty signal request body
     *
     * @throws SignalNetworkConnectionException
     * @throws \Exception
     */
    public function signal($sessionId, $payload = [], $connectionId = null)
    {
        // set up the request
        $requestRoot = '/v2/project/' . $this->apiKey . '/session/' . $sessionId;
        $request = is_null($connectionId) || empty($connectionId) ?
            new Request('POST', $requestRoot . '/signal')
            : new Request('POST', $requestRoot . '/connection/' . $connectionId . '/signal');

        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug(),
                'json' => array_merge(
                    $payload
                )
            ]);
            if ($response->getStatusCode() != 204) {
                json_decode($response->getBody(), true);
            }
        } catch (ClientException $e) {
            $this->handleSignalingException($e);
        } catch (RequestException $e) {
            throw new SignalNetworkConnectionException('Unable to communicate with host', -1, $e);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // Helpers

    private function postFieldsForOptions($options)
    {
        $options['p2p.preference'] = empty($options['mediaMode']) ? MediaMode::ROUTED : $options['mediaMode'];
        unset($options['mediaMode']);
        if (empty($options['location'])) {
            unset($options['location']);
        }
        $options['api_key'] = $this->apiKey;
        return $options;
    }

    public function forceMuteStream(string $sessionId, string $streamId)
    {
        $request = new Request(
            'POST',
            '/v2/project/' . $this->apiKey . '/session/' . $sessionId . '/stream/' . $streamId . '/mute'
        );

        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug(),
            ]);
            $jsonResponse = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->handleException($e);
            return false;
        }
        return $jsonResponse;
    }

    public function forceMuteAll(string $sessionId, array $options)
    {
        $request = new Request(
            'POST',
            '/v2/project/' . $this->apiKey . '/session/' . $sessionId . '/mute'
        );

        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug(),
                'json' => $options
            ]);
            $jsonResponse = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->handleException($e);
            return false;
        }
        return $jsonResponse;
    }

    public function connectAudio(string $sessionId, string $token, array $websocketOptions)
    {
        $request = new Request(
            'POST',
            '/v2/project/' . $this->apiKey . '/connect'
        );

        $body = [
            'sessionId' => $sessionId,
            'token' => $token,
            'websocket' => $websocketOptions
        ];

        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug(),
                'json' => $body
            ]);
            $jsonResponse = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->handleException($e);
            return false;
        }

        return $jsonResponse;
    }

    public function startCaptions(
        string $sessionId,
        string $token,
        ?string $languageCode,
        ?int $maxDuration,
        ?bool $partialCaptions,
        ?string $statusCallbackUrl
    )
    {
        $request = new Request(
            'POST',
            '/v2/project/' . $this->apiKey . '/captions'
        );

        $body = [
            'sessionId' => $sessionId,
            'token' => $token,
        ];

        if ($languageCode !== null) {
            $body['languageCode'] = $languageCode;
        }

        if ($maxDuration !== null) {
            $body['maxDuration'] = $maxDuration;
        }

        if ($partialCaptions !== null) {
            $body['partialCaptions'] = $partialCaptions;
        }

        if ($statusCallbackUrl !== null) {
            $body['statusCallbackUrl'] = $statusCallbackUrl;
        }

        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug(),
                'json' => $body
            ]);
            $jsonResponse = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->handleException($e);
        }

        return $jsonResponse;
    }

    public function stopCaptions(string $captionsId)
    {
        $request = new Request(
            'POST',
            '/v2/project/' . $this->apiKey . '/captions/' . $captionsId . '/stop'
        );

        try {
            $this->client->send($request, [
                'debug' => $this->isDebug(),
            ]);
            return true;
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    private function handleException($e)
    {
        // TODO: test coverage
        if ($e instanceof ClientException) {
            // will catch all 4xx errors
            if ($e->getResponse()->getStatusCode() == 403) {
                throw new AuthenticationException(
                    $this->apiKey,
                    $this->apiSecret,
                    null,
                    $e
                );
            } else {
                throw new DomainException(
                    'The OpenTok API request failed: ' . json_decode($e->getResponse()->getBody(true))->message,
                    null,
                    $e
                );
            }
        } elseif ($e instanceof ServerException) {
            // will catch all 5xx errors
            throw new UnexpectedValueException(
                'The OpenTok API server responded with an error: ' . json_decode($e->getResponse()->getBody(true))->message,
                null,
                $e
            );
        } else {
            // TODO: check if this works because Exception is an interface not a class
            throw new \Exception('An unexpected error occurred');
        }
    }

    private function handleArchiveException($e)
    {
        try {
            $this->handleException($e);
        } catch (AuthenticationException $ae) {
            throw new ArchiveAuthenticationException($this->apiKey, $this->apiSecret, null, $ae->getPrevious());
        } catch (DomainException $de) {
            throw new ArchiveDomainException($e->getMessage(), null, $de->getPrevious());
        } catch (UnexpectedValueException $uve) {
            throw new ArchiveUnexpectedValueException($e->getMessage(), null, $uve->getPrevious());
        } catch (Exception $oe) {
            // TODO: check if this works because ArchiveException is an interface not a class
            throw new ArchiveException($e->getMessage(), null, $oe->getPrevious());
        }
    }

    private function handleBroadcastException($e)
    {
        try {
            $this->handleException($e);
        } catch (AuthenticationException $ae) {
            throw new BroadcastAuthenticationException($this->apiKey, $this->apiSecret, null, $ae->getPrevious());
        } catch (DomainException $de) {
            throw new BroadcastDomainException($e->getMessage(), null, $de->getPrevious());
        } catch (UnexpectedValueException $uve) {
            throw new BroadcastUnexpectedValueException($e->getMessage(), null, $uve->getPrevious());
        } catch (Exception $oe) {
            // TODO: check if this works because BroadcastException is an interface not a class
            throw new BroadcastException($e->getMessage(), null, $oe->getPrevious());
        }
    }

    private function handleSignalingException(ClientException $e)
    {
        $responseCode = $e->getResponse()->getStatusCode();
        switch ($responseCode) {
            case 400:
                $message = 'One of the signal properties — data, type, sessionId or connectionId — is invalid.';
                throw new SignalUnexpectedValueException($message, $responseCode);
            case 403:
                throw new SignalAuthenticationException($this->apiKey, $this->apiSecret, null, $e);
            case 404:
                $message = 'The client specified by the connectionId property is not connected to the session.';
                throw new SignalConnectionException($message, $responseCode);
            case 413:
                $message = 'The type string exceeds the maximum length (128 bytes),'
                           . ' or the data string exceeds the maximum size (8 kB).';
                throw new SignalUnexpectedValueException($message, $responseCode);
            default:
                break;
        }
    }

    private function handleForceDisconnectException($e): void
    {
        $responseCode = $e->getResponse()->getStatusCode();
        switch ($responseCode) {
            case 400:
                $message = 'One of the arguments — sessionId or connectionId — is invalid.';
                throw new ForceDisconnectUnexpectedValueException($message, $responseCode);
            case 403:
                throw new ForceDisconnectAuthenticationException($this->apiKey, $this->apiSecret, null, $e);
            case 404:
                $message = 'The client specified by the connectionId property is not connected to the session.';
                throw new ForceDisconnectConnectionException($message, $responseCode);
            default:
                break;
        }
    }

    private function handleRenderException($e): void
    {
        $responseCode = $e->getResponse()->getStatusCode();
        switch ($responseCode) {
            case 400:
                throw new InvalidArgumentException('There was an error with the parameters supplied.');
            case 403:
                throw new AuthenticationException($this->apiKey, $this->apiSecret, null, $e);
            case 500:
                throw new \Exception('There is an error with the Video Platform');
            default:
                break;
        }
    }

    private function isDebug()
    {
        return defined('OPENTOK_DEBUG');
    }
}
