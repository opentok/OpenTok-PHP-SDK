<?php

namespace OpenTok;

use Guzzle\Http\Client;
use Guzzle\Http\ClientInterface;

class ArchiveList {

    private $json;

    public function __construct($archiveListJson, $apiKey, $apiSecret, $options = array())
    {
        // TODO: validate JSON

        $this->json = $archiveListJson;
    }
}

/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
