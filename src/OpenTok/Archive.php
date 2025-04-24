<?php

namespace OpenTok;

use OpenTok\Util\Client;
use OpenTok\Util\Validators;
use OpenTok\Exception\InvalidArgumentException;
use OpenTok\Exception\ArchiveUnexpectedValueException;

/**
* Represents an archive of an OpenTok session.
*
* @property int $createdAt
* The time at which the archive was created, in milliseconds since the UNIX epoch.
*
* @property string $duration
* The duration of the archive, in seconds.
*
* @property bool $hasVideo
* Whether the archive has a video track (<code>true</code>) or not (<code>false</code>).
*
* @property bool $hasAudio
* Whether the archive has an audio track (<code>true</code>) or not (<code>false</code>).
*
* @property string $id
* The archive ID.
*
* @property string $name
* The name of the archive. If no name was provided when the archive was created, this is set
* to null.
*
* @property string $outputMode
* The name of the archive. If no name was provided when the archive was created, this is set
* to null.
*
* @property string $partnerId
* The API key associated with the archive.
*
* @property string $reason
* For archives with the status "stopped" or "failed", this string describes the reason
* the archive stopped (such as "maximum duration exceeded") or failed.
*
* @property string $resolution
* The resolution of the archive, either "640x480" (SD landscape, the default), "1280x720" (HD landscape),
* "1920x1080" (FHD landscape), "480x640" (SD portrait), "720x1280" (HD portrait), or "1080x1920" (FHD portrait).
* You may want to use a portrait aspect ratio for archives that include video streams from mobile devices (which often use the portrait aspect ratio).
*
* @property string $sessionId
* The session ID of the OpenTok session associated with this archive.
*
* @property string $multiArchiveTag
* Whether Multiple Archive is switched on, which will be a unique string for each simultaneous archive of an ongoing session.
* See https://tokbox.com/developer/guides/archiving/#simultaneous-archives for more information.
*
* @property string $size
* The size of the MP4 file. For archives that have not been generated, this value is set to 0.
*
* @property string $streamMode
* Whether streams included in the archive are selected automatically (<code>StreamMode.AUTO</code>) or
* manually (<code>StreamMode.MANUAL</code>). When streams are selected automatically (<code>StreamMode.AUTO</code>),
* all streams in the session can be included in the archive. When streams are selected manually
* (<code>StreamMode.MANUAL</code>), you specify streams to be included based on calls to the
* <code>Archive.addStreamToArchive()</code> and <code>Archive.removeStreamFromArchive()</code> methods.
* With manual mode, you can specify whether a stream's audio, video, or both are included in the
* archive. In both automatic and manual modes, the archive composer includes streams based on
* <a href="https://tokbox.com/developer/guides/archive-broadcast-layout/#stream-prioritization-rules">stream
* prioritization rules</a>.
*
* @property string $status
* The status of the archive, which can be one of the following:
*
* <ul>
*   <li> "available" -- The archive is available for download from the OpenTok cloud.</li>
*   <li> "expired" -- The archive is no longer available for download from the OpenTok
*         cloud.</li>
*   <li> "failed" -- The archive recording failed.</li>
*   <li> "paused" -- The archive is in progress and no clients are publishing streams to
*        the session. When an archive is in progress and any client publishes a stream,
*        the status is "started". When an archive is "paused", nothing is recorded. When
*        a client starts publishing a stream, the recording starts (or resumes). If all clients
*        disconnect from a session that is being archived, the status changes to "paused", and
*        after 60 seconds the archive recording stops (and the status changes to "stopped").</li>
*   <li> "started" -- The archive started and is in the process of being recorded.</li>
*   <li> "stopped" -- The archive stopped recording.</li>
*   <li> "uploaded" -- The archive is available for download from the the upload target
*        Amazon S3 bucket or Windows Azure container you set up for your
*        <a href="https://tokbox.com/account">OpenTok project</a>.</li>
* </ul>
*
* @property string $url
* The download URL of the available MP4 file. This is only set for an archive with the status set to
* "available"; for other archives, (including archives with the status "uploaded") this property is
* set to null. The download URL is obfuscated, and the file is only available from the URL for
* 10 minutes. To generate a new URL, call the Archive.listArchives() or OpenTok.getArchive() method.
*/
class Archive
{
    // NOTE: after PHP 5.3.0 support is dropped, the class can implement JsonSerializable

    /** @internal */
    private $data;
    /** @internal */
    private $isDeleted;
    /** @internal */
    private $client;
    /** @internal */
    private $multiArchiveTag;
    /**
     * @var mixed|null
     */
    private const PERMITTED_AUTO_RESOLUTIONS = [
        '480x640',
        "640x480",
        "720x1280",
        "1280x720",
        "1080x1920",
        "1920x1080"
    ];

    /** @internal */
    public function __construct($archiveData, $options = array())
    {
        // unpack optional arguments (merging with default values) into named variables
        $defaults = array(
            'apiKey' => null,
            'apiSecret' => null,
            'apiUrl' => 'https://api.opentok.com',
            'client' => null,
            'streamMode' => StreamMode::AUTO
        );
        $options = array_merge($defaults, array_intersect_key($options, $defaults));
        list($apiKey, $apiSecret, $apiUrl, $client, $streamMode) = array_values($options);

        // validate params
        Validators::validateArchiveData($archiveData);
        Validators::validateClient($client);
        Validators::validateHasStreamMode($streamMode);

        if (isset($archiveData['maxBitrate']) && isset($archiveData['quantizationParameter'])) {
            throw new \DomainException('Max Bitrate cannot be set with QuantizationParameter ');
        }

        $this->data = $archiveData;

        if (isset($this->data['multiArchiveTag'])) {
            $this->multiArchiveTag = $this->data['multiArchiveTag'];
        }

        $this->client = isset($client) ? $client : new Client();
        if (!$this->client->isConfigured()) {
            Validators::validateApiUrl($apiUrl);

            $this->client->configure($apiKey, $apiSecret, $apiUrl);
        }
    }

    public static function getPermittedResolutions()
    {
        return self::PERMITTED_AUTO_RESOLUTIONS;
    }

    /** @internal */
    public function __get($name)
    {
        if ($this->isDeleted) {
            // TODO: throw an logic error about not being able to stop an archive thats deleted
        }

        switch ($name) {
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
            case 'hasVideo':
            case 'hasAudio':
            case 'outputMode':
            case 'resolution':
            case 'streamMode':
            case 'maxBitrate':
            case 'quantizationParameter':
                return $this->data[$name];
            case 'multiArchiveTag':
                return $this->multiArchiveTag;
            default:
                return null;
        }
    }

    /**
     * Stops the OpenTok archive, if it is being recorded.
     * <p>
     * Archives automatically stop recording after 120 minutes or when all clients have
     * disconnected from the session being archived.
     *
     * @throws Exception\ArchiveException The archive is not being recorded.
     */
    public function stop()
    {
        if ($this->isDeleted) {
            // TODO: throw an logic error about not being able to stop an archive thats deleted
        }

        $archiveData = $this->client->stopArchive($this->data['id']);

        try {
            Validators::validateArchiveData($archiveData);
        } catch (InvalidArgumentException $e) {
            throw new ArchiveUnexpectedValueException('The archive JSON returned after stopping was not valid', null, $e);
        }

        $this->data = $archiveData;
        return $this;
    }

    /**
     * Deletes an OpenTok archive.
     * <p>
     * You can only delete an archive which has a status of "available", "uploaded", or "deleted".
     * Deleting an archive removes its record from the list of archives. For an "available" archive,
     * it also removes the archive file, making it unavailable for download. For a "deleted"
     * archive, the archive remains deleted.
     *
     * @throws Exception\ArchiveException There archive status is not "available", "updated",
     * or "deleted".
     */
    public function delete()
    {
        if ($this->isDeleted) {
            // TODO: throw an logic error about not being able to stop an archive thats deleted
        }

        if ($this->client->deleteArchive($this->data['id'])) {
            $this->data = array();
            $this->isDeleted = true;
            return true;
        }
        return false;
    }

    /**
     * Returns a JSON representation of this Archive object.
     */
    public function toJson()
    {
        return json_encode($this->jsonSerialize());
    }

    /**
     * Adds a stream to a currently running archive that was started with the
     * the streamMode set to StreamMode.Manual. You can call the method
     * repeatedly with the same stream ID, to toggle the stream's audio or video in the archive.
     * 
     * @param String $streamId The stream ID.
     * @param Boolean $hasAudio Whether the archive should include the stream's audio (true, the default)
     * or not (false).
     * @param Boolean $hasVideo Whether the archive should include the stream's video (true, the default)
     * or not (false).
     *
     * @return Boolean Returns true on success.
     */
    public function addStreamToArchive(string $streamId, bool $hasAudio, bool $hasVideo): bool
    {
        if ($this->streamMode === StreamMode::AUTO) {
            throw new InvalidArgumentException('Cannot add stream to an Archive in auto stream mode');
        }

        if ($hasAudio === false && $hasVideo === false) {
            throw new InvalidArgumentException('Both hasAudio and hasVideo cannot be false');
        }

        if ($this->client->addStreamToArchive(
            $this->data['id'],
            $streamId,
            $hasVideo,
            $hasVideo
        )) {
            return true;
        }

        return false;
    }

    /**
     * Removes a stream from a currently running archive that was started with the
     * the streamMode set to StreamMode.Manual.
     * 
     * @param String $streamId The stream ID.
     *
     * @return Boolean Returns true on success.
     */
    public function removeStreamFromArchive(string $streamId): bool
    {
        if ($this->streamMode === StreamMode::AUTO) {
            throw new InvalidArgumentException('Cannot remove stream to an Archive in auto stream mode');
        }

        if ($this->client->removeStreamFromArchive(
            $this->data['id'],
            $streamId
        )) {
            return true;
        }

        return false;
    }

    /**
     * Returns an associative array representation of this Archive object.
     * @deprecated 3.0.0 A more standard name for this method is supplied by JsonSerializable
     * @see Archive::jsonSerialize() for a method with the same behavior
     */
    public function toArray()
    {
        return $this->data;
    }

    public function jsonSerialize()
    {
        return $this->data;
    }
}
