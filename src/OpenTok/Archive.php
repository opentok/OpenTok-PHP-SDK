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
* The duration of the archive, in milliseconds.
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
* The resolution of the archive.
*
* @property string $sessionId
* The session ID of the OpenTok session associated with this archive.
*
* @property string $size
* The size of the MP4 file. For archives that have not been generated, this value is set to 0.
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
class Archive {
    // NOTE: after PHP 5.3.0 support is dropped, the class can implement JsonSerializable

    /** @internal */
    private $data;
    /** @internal */
    private $isDeleted;
    /** @internal */
    private $client;

    /** @internal */
    public function __construct($archiveData, $options = array())
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
        Validators::validateArchiveData($archiveData);
        Validators::validateClient($client);

        $this->data = $archiveData;

        $this->client = isset($client) ? $client : new Client();
        if (!$this->client->isConfigured()) {
            Validators::validateApiKey($apiKey);
            Validators::validateApiSecret($apiSecret);
            Validators::validateApiUrl($apiUrl);

            $this->client->configure($apiKey, $apiSecret, $apiUrl);
        }
    }

    /** @internal */
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
            case 'hasVideo':
            case 'hasAudio':
            case 'outputMode':
            case 'resolution':
                return $this->data[$name];
                break;
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

/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
