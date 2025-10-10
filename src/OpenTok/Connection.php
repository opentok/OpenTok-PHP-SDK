<?php

namespace OpenTok;

/**
* Represents a connection in an OpenTok session.
* <p>
* See <a href="OpenTok.OpenTok.html#method_listConnections">OpenTok.listConnections()</a>.
*
* @property String $connectionId
* The connection ID.
*
* @property String $connectionState
* The state of the connection (either "Connecting" or "Connected").
*
* @property String $createdAt
* The timestamp for when the connection was created, expressed in milliseconds since the Unix epoch.
*/

class Connection
{

    private $data;

    public function __construct($connectionData)
    {

        $this->data = $connectionData;
    }

    /** @ignore */
    public function __get($name)
    {
        switch ($name) {
            case 'connectionId':
            case 'connectionState':
            case 'createdAt':
                return $this->data[$name];
            default:
                return null;
        }
    }

    public function jsonSerialize()
    {
        return $this->data;
    }
}