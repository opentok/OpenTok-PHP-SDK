<?php

namespace OpenTok;

use OpenTok\Util\Client;
use OpenTok\Exception\InvalidArgumentException;

class Archive {

    private $json;
    private $isDeleted;
    private $client;

    public function __construct($archiveJson, $options = array())
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
        // TODO: validate archiveJson
        if ($client && !($client instanceof Client)) {
            throw InvalidArgumentException(
                'The optional client was not an instance of \OpenTok\Util\Client'
            );
        }

        $this->json = $archiveJson;

        $this->client = isset($client) ? $client : new Client();
        if (!$this->client->isConfigured()) {
            // TODO: validate apiKey, apiSecret, apiUrl
            $this->client->configure($apiKey, $apiSecret, $apiUrl);
        }
    }

    // TODO: using the __get magic method is a challenge for PHPDoc, right?
    public function __get($name)
    {
        if ($this->isDeleted) {
            // TODO: throw an logic error about not being able to stop an archive thats deleted
        }
        switch($name) {
            case 'createdAt':
            case 'duration':
            case 'id':
            case 'name':
            case 'partnerId':
            case 'reason':
            case 'sessionId':
            case 'size':
            case 'status':
            case 'url':
                return $this->json[$name];
                break;
            default:
                return null;
        }
    }

    public function stop()
    {
        if ($this->isDeleted) {
            // TODO: throw an logic error about not being able to stop an archive thats deleted
        }

        $archiveJson = $this->client->stopArchive($this->json['id']);

        // TODO: validate json?
        $this->json = $archiveJson;
        return $this;
    }

    public function delete()
    {
        if ($this->isDeleted) {
            // TODO: throw an logic error about not being able to stop an archive thats deleted
        }

        if ($this->client->deleteArchive($this->json['id'])) {
            $this->json = array();
            $this->isDeleted = true;
            return true;
        }
        return false;
    }

}

/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
