<?php

namespace OpenTok;

use OpenTok\Util\Client;
use OpenTok\Util\Validators;

use OpenTok\Exception\InvalidArgumentException;
use OpenTok\Exception\ArchiveUnexpectedValueException;

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
        Validators::validateArchiveJson($archiveJson);
        Validators::validateClient($client);

        $this->json = $archiveJson;

        $this->client = isset($client) ? $client : new Client();
        if (!$this->client->isConfigured()) {
            Validators::validateApiKey($apiKey);
            Validators::validateApiSecret($apiSecret);
            Validators::validateApiUrl($apiUrl);

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

        try {
            Validators::validateArchiveJson($archiveJson);
        } catch (InvalidArgumentException $e) {
            throw new ArchiveUnexpectedValueException('The archive JSON returned after stopping was not valid', null, $e);
        }

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
