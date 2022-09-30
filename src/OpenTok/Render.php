<?php

namespace OpenTok;

/**
 * Represents an  Experience Composer render of an OpenTok session.
 *
 * @property string $id
 * The unique ID for the Experience Composer.
 *
 * @property string $sessionId
 * The session ID of the OpenTok session associated with this render.
 *
 * @property string $projectId
 * The API key associated with the render.
 *
 * @property int $createdAt
 * The time the Experience Composer started, expressed in milliseconds since the Unix epoch.
 *
 * @property int $updatedAt
 * The UNIX timestamp when the Experience Composer status was last updated.
 *
 * @property string $url
 * A publicly reachable URL controlled by the customer and capable of generating the content to be rendered without user intervention.
 *
 * @property string $resolution
 * The resolution of the Experience Composer (either "640x480", "480x640", "1280x720", "720x1280", "1920x1080", or "1080x1920").
 *
 * @property string $status
 * The status of the Experience Composer. Poll frequently to check status updates. This property set to one of the following:
 *   - "starting" — The Vonage Video API platform is in the process of connecting to the remote application at the URL provided. This is the initial state.
 *   - "started" — The Vonage Video API platform has successfully connected to the remote application server, and is publishing the web view to an OpenTok stream.
 *   - "stopped" — The Experience Composer has stopped.
 *   - "failed" — An error occurred and the Experience Composer could not proceed. It may occur at startup if the OpenTok server cannot connect to the remote
 * application server or republish the stream. It may also occur at any point during the process due to an error in the Vonage Video API platform.
 *
 * @property string $streamId
 * The ID of the composed stream being published. The streamId is not available when the status is "starting" and
 * may not be available when the status is "failed".
 *
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
