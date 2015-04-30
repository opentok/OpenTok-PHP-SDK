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

// TODO: build this dynamically
/** @internal */
define('OPENTOK_SDK_VERSION', '2.2.3');
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

    public function startArchive($params)
    {
        // set up the request
        $request = $this->post('/v2/partner/'.$this->apiKey.'/archive');
        $request->setBody(json_encode($params));
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
        $request = $this->post('/v2/partner/'.$this->apiKey.'/archive/'.$archiveId.'/stop');
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
        $request = $this->get('/v2/partner/'.$this->apiKey.'/archive/'.$archiveId);
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
        $request = $this->delete('/v2/partner/'.$this->apiKey.'/archive/'.$archiveId);
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
        $request = $this->get('/v2/partner/'.$this->apiKey.'/archive');
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
                'The OpenTok API server responded with an error: ' . json_decode($e->getResponse()-getBody(true))->message,
                null,
                $e
            );
        } else {
            // TODO: check if this works because Exception is an interface not a class
            throw new Exception('An unexpected error occurred');
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

}
