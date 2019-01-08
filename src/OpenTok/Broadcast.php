<?php

namespace OpenTok;

use OpenTok\Exception\BroadcastDomainException;
use OpenTok\Exception\BroadcastUnexpectedValueException;
use OpenTok\Exception\InvalidArgumentException;
use OpenTok\Util\Client;
use OpenTok\Util\Validators;

/**
* Represents a broadcast of an OpenTok session.
*
* @property int $createdAt
* The timestamp when the broadcast was created, expressed in seconds since the Unix epoch. 
*
* @property int $updatedAt
* The time the broadcast was started or stopped, expressed in seconds since the Unix epoch.
*
* @property string $id
* The unique ID for the broadcast.
*
* @property string $partnerId
* Your OpenTok API key.
*
* @property string $sessionId
* The OpenTok session ID.
*
* @property object $broadcastUrls
* Details on the HLS and RTMP broadcast streams. For an HLS stream, the URL is provided. See the
* <a href="https://tokbox.com/developer/guides/broadcast/live-streaming/">OpenTok live streaming developer guide</a>
* for more information on how to use this URL. For each RTMP stream, the RTMP server URL and stream
* name are provided, along with the RTMP stream's status.
*
* @property boolean $isStopped
* Whether the broadcast is stopped (true) or in progress (false).
*/
class Broadcast {
    // NOTE: after PHP 5.3.0 support is dropped, the class can implement JsonSerializable

    /** @ignore */
    private $data;
    /** @ignore */
    private $isStopped = false;
    /** @ignore */
    private $client;

    /** @ignore */
    public function __construct($broadcastData, $options = array())
    {
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

    /** @ignore */
    public function __get($name)
    {
        switch ($name) {
            case 'createdAt':
            case 'updatedAt':
            case 'id':
            case 'partnerId':
            case 'sessionId':
            case 'broadcastUrls':
            case 'status':
            case 'maxDuration':
            case 'resolution':
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
    /**
     * Stops the broadcast.
     */
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

    /**
     * Updates the layout of the broadcast.
     * <p>
     * See <a href="https://tokbox.com/developer/guides/broadcast/live-streaming/#configuring-video-layout-for-opentok-live-streaming-broadcasts">Configuring
     * video layout for OpenTok live streaming broadcasts</a>.
     *
     * @param Layout $layout An object defining the layout type for the broadcast.
     */
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
