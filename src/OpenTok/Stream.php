<?php

namespace OpenTok;

/**
* Represents a stream in an OpenTok session.
* <p>
* See <a href="OpenTok.OpenTok.html#method_getStream">OpenTok.getStream()</a> and
* <a href="OpenTok.OpenTok.html#method_listStreams">OpenTok.listStreams()</a>.
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

class Stream
{
    public function __construct(private $data)
    {
    }

    /** @ignore */
    public function __get($name)
    {
        return match ($name) {
            'id', 'videoType', 'name', 'layoutClassList' => $this->data[$name],
            default => null,
        };
    }

    public function jsonSerialize()
    {
        return $this->data;
    }
}
