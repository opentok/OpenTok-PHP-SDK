<?php

namespace OpenTok;

use OpenTok\Exception\BroadcastDomainException;
use OpenTok\Exception\BroadcastUnexpectedValueException;
use OpenTok\Util\Validators;

class Broadcast {
    // NOTE: after PHP 5.3.0 support is dropped, the class can implement JsonSerializable

    private $data;
    private $isStopped = false;
    private $client;

    public function __construct($broadcastData, $options = array()) {
        // unpack optional arguments (merging with default values) into named variables
        $defaults = array(
            'apiKey' => null,
            'apiSecret' => null,
            'apiUrl' => 'https://api.opentok.com',
            'client' => null,
            'isStopped' => false
        );
        $options = array_merge($defaults, array_intersect_key($options, $defaults));
        list($apiKey, $apiSecret, $apiUrl, $client, $isStopped) = array_values($options);

        // validate params
        Validators::validateBroadcastData($broadcastData);
        Validators::validateClient($client);

        $this->data = $broadcastData;

        $this->isStopped = $isStopped;

        $this->client = isset($client) ? $client : new Client();
        if (!$this->client->isConfigured()) {
            Validators::validateApiKey($apiKey);
            Validators::validateApiSecret($apiSecret);
            Validators::validateApiUrl($apiUrl);

            $this->client->configure($apiKey, $apiSecret, $apiUrl);
        }
    }

    public function __get($name)
    {
        switch ($name) {
            case 'createdAt':
            case 'updatedAt':
            case 'id':
            case 'partnerId':
            case 'sessionId':
            case 'broadcastUrls':
                return $this->data[$name];
                break;
            case 'hlsUrl':
                return $this->data['broadcastUrls']['hls'];
                break;
            case 'isStopped':
                return $this->isStopped;
                break;
            default:
                return null;
        }
    }

    public function stop()
    {
        if ($this->isStopped) {
            throw new BroadcastDomainException(
                'You cannot stop a broadcast which is already stopped.'
            );
        }

        $broadcastData = $this->client->stopBroadcast($this->data['id']);

        try {
            Validators::validateBroadcastData($broadcastData);
        } catch (InvalidArgumentException $e) {
            throw new BroadcastUnexpectedValueException('The broadcast JSON returned after stopping was not valid', null, $e);
        }

        $this->data = $broadcastData;
        return $this;
    }

    // TODO: not yet implemented by the platform
    // public function getLayout()
    // {
    //     $layoutData = $this->client->getLayout($this->id, 'broadcast');
    //     return Layout::fromData($layoutData);
    // }

    public function updateLayout($layout)
    {
        Validators::validateLayout($layout);

        // TODO: platform implementation did not meet API review spec
        // $layoutData = $this->client->updateLayout($this->id, $layout, 'broadcast');
        // return Layout::fromData($layoutData);

        $this->client->updateLayout($this->id, $layout, 'broadcast');
    }

    public function jsonSerialize()
    {
        return $this->data;
    }
}
