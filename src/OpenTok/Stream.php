<?php

namespace OpenTok;

/**
* Represents a stream in an OpenTok session.
* <p>
* See <a href="OpenTok.html#method_getStream">OpenTok.getStream()</a> and
* <a href="OpenTok.html#method_listStreams">OpenTok.listStreams()</a>.
*
* @property String $id
* The stream ID.
*
* @property Array $layoutClassList
* An array of the layout classes for the stream.
*
* @property String $name
* The stream name (if one was set when the client published the stream).
*
* @property String $videoType
* The type of video in the stream, which is set to either "camera" or "screen".
*/

class Stream {

    private $data;

    public function __construct($streamData)
    {

        $this->data = $streamData;
    }

    /** @ignore */
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
