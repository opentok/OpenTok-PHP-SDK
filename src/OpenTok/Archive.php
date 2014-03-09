<?php

namespace OpenTok;

use Guzzle\Http\Client;
use Guzzle\Http\ClientInterface;

class Archive {

    private $json;

    public function __construct($archiveJson, $apiKey, $apiSecret, $options = array())
    {
        // TODO: validate JSON

        $this->json = $archiveJson;
    }
}

