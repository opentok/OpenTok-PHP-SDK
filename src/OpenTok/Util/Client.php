<?php

namespace OpenTok\Util;

use \Guzzle\Http\Exception\ClientErrorResponseException;
use \Guzzle\Http\Exception\ServerErrorResponseException;

// TODO: build this dynamically
define('OPENTOK_SDK_VERSION', '2.0.0-beta');
define('OPENTOK_SDK_USER_AGENT', 'OpenTok-PHP-SDK/' . OPENTOK_SDK_VERSION);

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
        $partnerAuthPlugin = new Plugin\PartnerAuth($apiKey, $apiSecret);
        $this->addSubscriber($partnerAuthPlugin);

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
        } catch (RuntimeException $e) {
            // The $response->xml() method uses the following code to throw a parse exception:
            // throw new RuntimeException('Unable to parse response body into XML: ' . $errorMessage);
            // TODO: test if we have a parse exception and handle it, otherwise throw again
            throw $e;
        } catch (Exception $e) {
            $this->handleException($e);
            return;
        }
        return $sessionXml;
    }

    // Archiving API Requests

    public function startArchive($params)
    {
        $request = $this->post('/v2/partner/'.$this->apiKey.'/archive');
        $request->setBody(json_encode($params));
        $request->setHeader('Content-Type', 'application/json');
        try {
            $archiveJson = $request->send()->json();
        } catch (Exception $e) {
            $this->handleException($e);
            return;
        }
        return $archiveJson;
    }

    public function stopArchive($archiveId)
    {
        $request = $this->post('/v2/partner/'.$this->apiKey.'/archive/'.$archiveId);
        $params = array( 'action' => 'stop' );
        $request->setBody(json_encode($params));
        $request->setHeader('Content-Type', 'application/json');
        try {
            $archiveJson = $request->send()->json();
        } catch (Exception $e) {
            $this->handleException($e);
            return;
        }
        return $archiveJson;
    }

    public function getArchive($archiveId)
    {
        $request = $this->get('/v2/partner/'.$this->apiKey.'/archive/'.$archiveId);
        try {
            $archiveJson = $request->send()->json();
        } catch (Exception $e) {
            $this->handleException($e);
            return;
        }
        return $archiveJson;
    }

    public function deleteArchive($archiveId)
    {
        $request = $this->delete('/v2/partner/'.$this->apiKey.'/archive/'.$archiveId);
        $request->setHeader('Content-Type', 'application/json');
        try {
            $archiveJson = $request->send()->json();
        } catch (Exception $e) {
            $this->handleException($e);
            return;
        }
        return $archiveJson;
    }

    public function listArchives($offset, $count)
    {
        $request = $this->get('/v2/partner/'.$this->apiKey.'/archive');
        if ($offset != 0) $request->getQuery()->set('offset', $offset);
        if (!empty($count)) $request->getQuery()->set('count', $count);
        try {
            $archiveListJson = $request->send()->json();
        } catch (Exception $e) {
            $this->handleException($e);
            return;
        }
        return $archiveListJson;
    }

    // Helpers

    private function postFieldsForOptions($options)
    {
        if (!isset($options['p2p'])) {
            unset($options['p2p']);
        } else {
            $options['p2p.preference'] = $options['p2p'] ? 'enabled' : 'disabled';
        }
        if (empty($options['location'])) {
            unset($options['location']);
        }
        $options['api_key'] = $this->apiKey;
        return $options;
    }

    private function handleException($e)
    {
        // TODO: test coverage
        // TODO: logging
        // TODO: exception handling
        if ($e instanceof ClientErrorResponseException) {
            // will catch all 4xx errors
            echo 'Uh oh! ' . $e->getMessage();
            echo 'HTTP request URL: ' . $e->getRequest()->getUrl() . "\n";
            echo 'HTTP request: ' . $e->getRequest() . "\n";
            echo 'HTTP response status: ' . $e->getResponse()->getStatusCode() . "\n";
            echo 'HTTP response: ' . $e->getResponse() . "\n";
            return;
        } else if ($e instanceof ServerErrorResponseException) {
            // will catch all 5xx errors
            echo 'Uh oh! ' . $e->getMessage();
            echo 'HTTP request URL: ' . $e->getRequest()->getUrl() . "\n";
            echo 'HTTP request: ' . $e->getRequest() . "\n";
            echo 'HTTP response status: ' . $e->getResponse()->getStatusCode() . "\n";
            echo 'HTTP response: ' . $e->getResponse() . "\n";
            return;
        } else {
            echo 'An error unrelated to the Request cannot be handled by the OpenTok\Util\Client'."\n";
            echo $e->getMessage();
            return;
        }
    }

}
