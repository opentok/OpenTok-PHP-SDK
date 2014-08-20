<?php

namespace OpenTok;

use OpenTok\Util\Client;
use OpenTok\Util\Validators;

// TODO: may want to implement the ArrayAccess interface in the future
// TODO: what does implementing JsonSerializable gain for us?
/**
* A class for accessing an array of Archive objects.
*/
class ArchiveList {

    /**
    * @internal
    */
    private $data;
    /**
    * @internal
    */
    private $apiKey;
    /**
    * @internal
    */
    private $apiSecret;
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
    public function __construct($archiveListData, $options = array())
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
        Validators::validateArchiveListData($archiveListData);
        Validators::validateClient($client);

        $this->data = $archiveListData;

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
            foreach($this->data['items'] as $archiveData) {
                $items[] = new Archive($archiveData, array( 'client' => $this->client ));
            }
            $this->items = $items;
        }
        return $this->items;
    }
}

/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
