<?php

namespace OpenTok;

/**
 * Represents an  Experience Composer render of an OpenTok session.
 */
class Render
{
    /** @internal */
    private $data;

    /** @internal */
    public function __construct($data)
    {
        $this->data = json_decode($data, true);
    }

    /** @internal */
    public function __get($name)
    {
        switch ($name) {
            case 'id':
            case 'sessionId':
            case 'projectId':
            case 'createdAt':
            case 'updatedAt':
            case 'url':
            case 'resolution':
            case 'status':
            case 'streamId':
                return $this->data[$name];
            default:
                return null;
        }
    }
}
