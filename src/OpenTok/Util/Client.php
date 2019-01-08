<?php

namespace OpenTok\Util;

use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Firebase\JWT\JWT;

use OpenTok\Exception\Exception;
use OpenTok\Exception\DomainException;
use OpenTok\Exception\UnexpectedValueException;
use OpenTok\Exception\AuthenticationException;

use OpenTok\Exception\ArchiveException;
use OpenTok\Exception\ArchiveDomainException;
use OpenTok\Exception\ArchiveUnexpectedValueException;
use OpenTok\Exception\ArchiveAuthenticationException;

use OpenTok\Exception\BroadcastException;
use OpenTok\Exception\BroadcastDomainException;
use OpenTok\Exception\BroadcastUnexpectedValueException;
use OpenTok\Exception\BroadcastAuthenticationException;

use OpenTok\Exception\SignalException;
use OpenTok\Exception\SignalConnectionException;
use OpenTok\Exception\SignalUnexpectedValueException;
use OpenTok\Exception\SignalAuthenticationException;

use OpenTok\Exception\ForceDisconnectConnectionException;
use OpenTok\Exception\ForceDisconnectUnexpectedValueException;
use OpenTok\Exception\ForceDisconnectAuthenticationException;

use OpenTok\MediaMode;

// TODO: build this dynamically
/** @internal */
define('OPENTOK_SDK_VERSION', '4.4.0');
/** @internal */
define('OPENTOK_SDK_USER_AGENT', 'OpenTok-PHP-SDK/' . OPENTOK_SDK_VERSION);

/**
* @internal
*/
class Client
{
    protected $apiKey;
    protected $apiSecret;
    protected $configured = false;
    protected $client;

    public function configure($apiKey, $apiSecret, $apiUrl, $options = array())
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;

        if (empty($options['handler'])) {
            $handlerStack = HandlerStack::create();
        } else {
            $handlerStack = $options['handler'];
        }

        $handler = Middleware::mapRequest(function (RequestInterface $request) {
            $authHeader = $this->createAuthHeader();
            return $request->withHeader('X-OPENTOK-AUTH', $authHeader);
        });
        $handlerStack->push($handler);

        $ua = OPENTOK_SDK_USER_AGENT . ' ' . \GuzzleHttp\default_user_agent();
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => $apiUrl,
            'handler' => $handlerStack,
            'headers' => [
                'User-Agent' => $ua
            ]
        ]);

        $this->configured = true;
    }

    public function isConfigured()
    {
        return $this->configured;
    }

    private function createAuthHeader()
    {
        $token = array(
            'ist' => 'project',
            'iss' => $this->apiKey,
            'iat' => time(), // this is in seconds
            'exp' => time()+(5 * 60),
            'jti' => uniqid(),
        );
        return JWT::encode($token, $this->apiSecret);
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
        $disableEntities = libxml_disable_entity_loader(true);
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
        libxml_disable_entity_loader($disableEntities);
        if ($errorMessage) {
            throw new \RuntimeException('Unable to parse response body into XML: ' . $errorMessage);
        }
        return $xml;
    }

    // Archiving API Requests

    public function startArchive($sessionId, $options)
    {
        // set up the request
        $request = new Request('POST', '/v2/project/'.$this->apiKey.'/archive');

        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug(),
                'json' => array_merge(
                    array( 'sessionId' => $sessionId ),
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
            '/v2/project/'.$this->apiKey.'/archive/'.$archiveId.'/stop',
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
            '/v2/project/'.$this->apiKey.'/archive/'.$archiveId
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

    public function deleteArchive($archiveId)
    {
        $request = new Request(
            'DELETE',
            '/v2/project/'.$this->apiKey.'/archive/'.$archiveId,
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

    public function forceDisconnect($sessionId,$connectionId)
    {
        $request = new Request(
            'DELETE',
            '/v2/project/'.$this->apiKey.'/session/'.$sessionId.'/connection/'.$connectionId,
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
        $request = new Request('GET', '/v2/project/'.$this->apiKey.'/archive');
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

    public function startBroadcast($sessionId, $options)
    {
        $request = new Request(
            'POST',
            '/v2/project/'.$this->apiKey.'/broadcast'
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
            '/v2/project/'.$this->apiKey.'/broadcast/'.$broadcastId.'/stop',
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
            '/v2/project/'.$this->apiKey.'/broadcast/'.$broadcastId
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

    public function getLayout($resourceId, $resourceType = 'broadcast')
    {
        $request = new Request(
            'GET',
            '/v2/project/'.$this->apiKey.'/'.$resourceType.'/'.$resourceId.'/layout'
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

    public function updateLayout($resourceId, $layout, $resourceType = 'broadcast')
    {
        $request = new Request(
            'PUT',
            '/v2/project/'.$this->apiKey.'/'.$resourceType.'/'.$resourceId.'/layout'
        );
        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug(),
                'json' => $layout->jsonSerialize()
            ]);
            $layoutJson = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->handleException($e);
        }
        return $layoutJson;
    }

    public function setArchiveLayout($archiveId, $layout)
    {
        $request = new Request(
            'PUT',
            '/v2/project/'.$this->apiKey.'/archive/'.$archiveId.'/layout'
        );
        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug(),
                'json' => $layout->jsonSerialize()
            ]);
            $layoutJson = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->handleException($e);
        }
        return $layoutJson;
    }

    public function updateStream($sessionId, $streamId, $properties)
    {
        $request = new Request(
            'PUT',
            '/v2/project/'.$this->apiKey.'/session/'.$sessionId.'/stream/'.$streamId
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

    public function getStream($sessionId, $streamId) {
        $request = new Request(
            'GET',
            '/v2/project/'.$this->apiKey.'/session/'.$sessionId.'/stream/'.$streamId
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
            '/v2/project/'.$this->apiKey.'/session/'.$sessionId.'/stream/'
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
            'v2/project/'.$this->apiKey.'/session/'.$sessionId.'/stream'
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


    public function dial($sessionId, $token, $sipUri, $options)
    {
        $body = array(
            'sessionId' => $sessionId,
            'token' => $token,
            'sip' => array(
                'uri' => $sipUri,
                'secure' => $options['secure']
            )
        );

        if (isset($options) && array_key_exists('headers', $options) && sizeof($options['headers']) > 0) {
            $body['sip']['headers'] = $options['headers'];
        }

        if (isset($options) && array_key_exists('auth', $options)) {
            $body['sip']['auth'] = $options['auth'];
        }
        if (isset($options) && array_key_exists('from', $options)) {
            $body['sip']['from'] = $options['from'];
        }

        // set up the request
        $request = new Request('POST', '/v2/project/'.$this->apiKey.'/call');

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

    public function signal($sessionId, $options=array(), $connectionId=null)
    {
        // set up the request

        
        $request = is_null($connectionId) || empty($connectionId) ? 
                new Request('POST', '/v2/project/'.$this->apiKey.'/session/'.$sessionId.'/signal')
                : new Request('POST', '/v2/project/'.$this->apiKey.'/session/'.$sessionId.'/connection/'.$connectionId.'/signal');

        try {
            $response = $this->client->send($request, [
                'debug' => $this->isDebug(),
                'json' => array_merge(
                    $options
                )
            ]);
            if ($response->getStatusCode() != 204) {
                json_decode($response->getBody(), true);
            }
        } catch (\Exception $e) {
            $this->handleSignalingException($e);
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

    //echo 'Uh oh! ' . $e->getMessage();
    //echo 'HTTP request URL: ' . $e->getRequest()->getUrl() . "\n";
    //echo 'HTTP request: ' . $e->getRequest() . "\n";
    //echo 'HTTP response status: ' . $e->getResponse()->getStatusCode() . "\n";
    //echo 'HTTP response: ' . $e->getResponse() . "\n";

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
                    'The OpenTok API request failed: '. json_decode($e->getResponse()->getBody(true))->message,
                    null,
                    $e
                );
            }
        } else if ($e instanceof ServerException) {
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

    private function handleSignalingException($e)
    {
        $responseCode = $e->getResponse()->getStatusCode();
        switch($responseCode) {
            case 400:
                $message = 'One of the signal properties — data, type, sessionId or connectionId — is invalid.';
                throw new SignalUnexpectedValueException($message, $responseCode);
                break;
            case 403:
                throw new SignalAuthenticationException($this->apiKey, $this->apiSecret, null, $e);
                break;
            case 404:
                $message = 'The client specified by the connectionId property is not connected to the session.';
                throw new SignalConnectionException($message, $responseCode);
                break;
            case 413:
                $message = 'The type string exceeds the maximum length (128 bytes), or the data string exceeds the maximum size (8 kB).';
                throw new SignalUnexpectedValueException($message, $responseCode);
                break;
            default:
                break;
        }
    }

    private function handleForceDisconnectException($e)
    {
        $responseCode = $e->getResponse()->getStatusCode();
        switch($responseCode) {
            case 400:
                $message = 'One of the arguments — sessionId or connectionId — is invalid.';
                throw new ForceDisconnectUnexpectedValueException($message, $responseCode);
                break;
            case 403:
                throw new ForceDisconnectAuthenticationException($this->apiKey, $this->apiSecret, null, $e);
                break;
            case 404:
                $message = 'The client specified by the connectionId property is not connected to the session.';
                throw new ForceDisconnectConnectionException($message, $responseCode);
                break;
            default:
                break;
        }
    }

    private function isDebug()
    {
      return defined('OPENTOK_DEBUG');
    }
}
