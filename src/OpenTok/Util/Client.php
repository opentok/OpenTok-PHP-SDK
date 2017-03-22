<?php

namespace OpenTok\Util;

use \Guzzle\Http\Exception\ClientErrorResponseException;
use \Guzzle\Http\Exception\ServerErrorResponseException;

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

// TODO: build this dynamically
/** @internal */
define('OPENTOK_SDK_VERSION', '2.4.1-alpha.1');
/** @internal */
define('OPENTOK_SDK_USER_AGENT', 'OpenTok-PHP-SDK/' . OPENTOK_SDK_VERSION);

/**
* @internal
*/
class Client extends \Guzzle\Http\Client
{
    protected $apiKey;
    protected $apiSecret;
    protected $configured = false;

    public function configure($apiKey, $apiSecret, $apiUrl)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->setBaseUrl($apiUrl);
        $this->setUserAgent(OPENTOK_SDK_USER_AGENT, true);

        // TODO: attach plugins
        $opentokAuthPlugin = new Plugin\OpentokAuth($apiKey, $apiSecret);
        $this->addSubscriber($opentokAuthPlugin);

        $this->configured = true;
    }

    public function isConfigured() {
        return $this->configured;
    }

    // General API Requests

    public function createSession($options)
    {
        $request = $this->post('/session/create');
        $request->addPostFields($this->postFieldsForOptions($options));
        try {
            $sessionXml = $request->send()->xml();
        } catch (\RuntimeException $e) {
            // The $response->xml() method uses the following code to throw a parse exception:
            // throw new RuntimeException('Unable to parse response body into XML: ' . $errorMessage);
            // TODO: test if we have a parse exception and handle it, otherwise throw again
            throw $e;
        } catch (\Exception $e) {
            $this->handleException($e);
            return;
        }
        return $sessionXml;
    }

    // Archiving API Requests

    public function startArchive($sessionId, $options)
    {
        // set up the request
        $request = $this->post('/v2/project/'.$this->apiKey.'/archive');
        $request->setBody(json_encode(array_merge(array( 'sessionId' => $sessionId ), $options)));
        $request->setHeader('Content-Type', 'application/json');

        try {
            $archiveJson = $request->send()->json();
        } catch (\Exception $e) {
            $this->handleArchiveException($e);
        }
        return $archiveJson;
    }

    public function stopArchive($archiveId)
    {
        // set up the request
        $request = $this->post('/v2/project/'.$this->apiKey.'/archive/'.$archiveId.'/stop');
        $request->setHeader('Content-Type', 'application/json');

        try {
            $archiveJson = $request->send()->json();
        } catch (\Exception $e) {
            // TODO: what happens with JSON parse errors?
            $this->handleArchiveException($e);
        }
        return $archiveJson;
    }

    public function getArchive($archiveId)
    {
        $request = $this->get('/v2/project/'.$this->apiKey.'/archive/'.$archiveId);
        try {
            $archiveJson = $request->send()->json();
        } catch (\Exception $e) {
            $this->handleException($e);
            return;
        }
        return $archiveJson;
    }

    public function deleteArchive($archiveId)
    {
        $request = $this->delete('/v2/project/'.$this->apiKey.'/archive/'.$archiveId);
        $request->setHeader('Content-Type', 'application/json');
        try {
            $request->send()->json();
        } catch (\Exception $e) {
            $this->handleException($e);
            return false;
        }
        return true;
    }

    public function listArchives($offset, $count)
    {
        $request = $this->get('/v2/project/'.$this->apiKey.'/archive');
        if ($offset != 0) $request->getQuery()->set('offset', $offset);
        if (!empty($count)) $request->getQuery()->set('count', $count);
        try {
            $archiveListJson = $request->send()->json();
        } catch (\Exception $e) {
            $this->handleException($e);
            return;
        }
        return $archiveListJson;
    }

    public function startBroadcast($sessionId, $options)
    {
        $request = $this->post('/v2/project/'.$this->apiKey.'/broadcast');
        $request->setBody(json_encode(array(
            'sessionId' => $sessionId,
            'layout' => $options['layout']->jsonSerialize()
        )));
        $request->setHeader('Content-Type', 'application/json');

        try {
            $broadcastJson = $request->send()->json();
        } catch (\Exception $e) {
            $this->handleBroadcastException($e);
        }
        return $broadcastJson;
    }

    public function stopBroadcast($broadcastId)
    {
        $request = $this->post('/v2/project/'.$this->apiKey.'/broadcast/'.$broadcastId.'/stop');
        $request->setHeader('Content-Type', 'application/json');

        try {
            $broadcastJson = $request->send()->json();
        } catch (\Exception $e) {
            $this->handleBroadcastException($e);
        }
        return $broadcastJson;
    }

    public function getBroadcast($broadcastId)
    {
        $request = $this->get('/v2/project/'.$this->apiKey.'/broadcast/'.$broadcastId);
        try {
            $broadcastJson = $request->send()->json();
        } catch (\Exception $e) {
            $this->handleBroadcastException($e);
        }
        return $broadcastJson;
    }

    public function getLayout($resourceId, $resourceType = 'broadcast')
    {
        $request = $this->get('/v2/project/'.$this->apiKey.'/'.$resourceType.'/'.$resourceId.'/layout');
        try {
            $layoutJson = $request->send()->json();
        } catch (\Exception $e) {
            $this->handleException($e);
        }
        return $layoutJson;
    }

    public function updateLayout($resourceId, $layout, $resourceType = 'broadcast')
    {
        $request = $this->put('/v2/project/'.$this->apiKey.'/'.$resourceType.'/'.$resourceId.'/layout');
        $request->setBody(json_encode($layout->jsonSerialize()));
        $request->setHeader('Content-Type', 'application/json');
        try {
            $layoutJson = $request->send()->json();
        } catch (\Exception $e) {
            $this->handleException($e);
        }
        return $layoutJson;
    }

    public function updateStream($sessionId, $streamId, $properties)
    {
        $request = $this->put('/v2/project/'.$this->apiKey.'/session/'.$sessionId.'/stream/'.$streamId);
        $request->setBody(json_encode($properties));
        $request->setHeader('Content-Type', 'application/json');
        try {
            $request->send()->json();
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

        // set up the request
        $request = $this->post('/v2/project/'.$this->apiKey.'/call');
        $request->setBody(json_encode($body));
        $request->setHeader('Content-Type', 'application/json');

        try {
            $sipJson = $request->send()->json();
        } catch (\Exception $e) {
            $this->handleException($e);
        }
        return $sipJson;
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
        if ($e instanceof ClientErrorResponseException) {
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
        } else if ($e instanceof ServerErrorResponseException) {
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

}
