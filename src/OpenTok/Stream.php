<?php

namespace OpenTok;

class Stream {

    private $data;

    public function __construct($streamData)
    {

        $this->data = $streamData;
    }

    public function __get($name)
    {
        switch ($name) {
            case 'id':
            case 'videoType':
            case 'name':
            case 'layoutClassList':
                return $this->data[$name];
                break;
            default:
                return null;
        }
    }

    public function jsonSerialize()
    {
        return $this->data;
    }
}
