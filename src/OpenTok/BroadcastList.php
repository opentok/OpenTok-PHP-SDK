<?php

namespace OpenTok;

use OpenTok\Util\Client;
use OpenTok\Util\Validators;

/**
* A class for accessing an array of Archive objects.
*/
class BroadcastList
{
    /**
    * @internal
    */
    private $data;
    /**
    * @internal
    */
    private $client;
    /**
    * @internal
    */
    private $items;

    /**
    * @internal
    */
    public function __construct($broadcastListData, $options = array())
    {
        // unpack optional arguments (merging with default values) into named variables
        $defaults = array(
            'apiKey' => null,
            'apiSecret' => null,
            'apiUrl' => 'https://api.opentok.com',
            'client' => null
        );
        $options = array_merge($defaults, array_intersect_key($options, $defaults));
        list($apiKey, $apiSecret, $apiUrl, $client) = array_values($options);

        // validate params
        Validators::validateBroadcastListData($broadcastListData);
        Validators::validateClient($client);

        $this->data = $broadcastListData;

        $this->client = isset($client) ? $client : new Client();
        if (!$this->client->isConfigured()) {
            Validators::validateApiKey($apiKey);
            Validators::validateApiSecret($apiSecret);
            Validators::validateApiUrl($apiUrl);

            $this->client->configure($apiKey, $apiSecret, $apiUrl);
        }
    }

    /**
     * Returns the number of total archives for the API key.
     */
    public function totalCount()
    {
        return $this->data['count'];
    }

    /**
     * Returns an array of Archive objects.
     */
    public function getItems()
    {
        if (!$this->items) {
            $items = array();
            foreach ($this->data['items'] as $broadcastData) {
                $items[] = new Broadcast($broadcastData, array( 'client' => $this->client ));
            }
            $this->items = $items;
        }
        return $this->items;
    }
}
