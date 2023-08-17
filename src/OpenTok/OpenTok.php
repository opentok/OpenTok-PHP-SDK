<?php

namespace OpenTok;

use OpenTok\Util\Client;
use OpenTok\Util\Validators;
use OpenTok\Exception\InvalidArgumentException;
use OpenTok\Exception\UnexpectedValueException;

/**
* Contains methods for creating OpenTok sessions, generating tokens, and working with archives.
* <p>
* To create a new OpenTok object, call the OpenTok() constructor with your OpenTok API key
* and the API secret for your <a href="https://tokbox.com/account">OpenTok Video API account</a>. Do not
* publicly share your API secret. You will use it with the OpenTok() constructor (only on your web
* server) to create OpenTok sessions.
* <p>
* Be sure to include the entire OpenTok server SDK on your web server.
*/
class OpenTok
{

    /** @internal */
    private $apiKey;
    /** @internal */
    private $apiSecret;
    /** @internal */
    private $client;
    /** @internal */
    public $options;

    /** @internal */
    public function __construct($apiKey, $apiSecret, $options = array())
    {
        // unpack optional arguments (merging with default values) into named variables
        $defaults = array(
            'apiUrl' => 'https://api.opentok.com',
            'client' => null,
            'timeout' => null // In the future we should set this to 2
        );

        $this->options = array_merge($defaults, array_intersect_key($options, $defaults));

        list($apiUrl, $client, $timeout) = array_values($this->options);

        // validate arguments
        Validators::validateApiKey($apiKey);
        Validators::validateApiSecret($apiSecret);
        Validators::validateApiUrl($apiUrl);
        Validators::validateClient($client);
        Validators::validateDefaultTimeout($timeout);

        $this->client = isset($client) ? $client : new Client();
        if (!$this->client->isConfigured()) {
            $this->client->configure(
                $apiKey,
                $apiSecret,
                $apiUrl,
                array_merge(['timeout' => $timeout], $this->options)
            );
        }
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    /**
     * Creates a token for connecting to an OpenTok session.
     *
     * In order to authenticate a user
     * connecting to an OpenTok session, the client passes a token when connecting to the session.
     * <p>
     * For testing, you generate tokens or by logging in to your
     * <a href="https://tokbox.com/account">OpenTok Video API account</a>.
     *
     * @param string $sessionId The session ID corresponding to the session to which the user
     * will connect.
     *
     * @param array $options This array defines options for the token. This array includes the
     * following keys, all of which are optional:
     *
     * <ul>
     *
     *    <li><code>'role'</code> (string) &mdash; One of the constants defined in the RoleConstants
     *    class. The default role is publisher</li>
     *
     *    <li><code>'expireTime'</code> (int) &mdash; The timestamp for when the token expires,
     *    in milliseconds since the Unix epoch. The default expiration time is 24 hours
     *    after the token creation time. The maximum expiration time is 30 days after the
     *    token creation time.</li>
     *
     *    <li><code>'data'</code> (string) &mdash; A string containing connection metadata
     *    describing the end-user. For example, you can pass the user ID, name, or other data
     *    describing the end-user. The length of the string is limited to 1000 characters.
     *    This data cannot be updated once it is set.</li>
     *
     *    <li><code>initialLayoutClassList</code> (array) &mdash; An array of class names (strings)
     *      to be used as the initial layout classes for streams published by the client. Layout
     *      classes are used in customizing the layout of videos in
     *      <a href="https://tokbox.com/developer/guides/broadcast/live-streaming/">live streaming
     *      broadcasts</a> and
     *      <a href="https://tokbox.com/developer/guides/archiving/layout-control.html">composed
     *      archives</a>.
     *    </li>
     *
     * </ul>
     *
     * @return string The token string.
     */
    public function generateToken($sessionId, $options = array())
    {
        // unpack optional arguments (merging with default values) into named variables
        $defaults = array(
            'role' => Role::PUBLISHER,
            'expireTime' => null,
            'data' => null,
            'initialLayoutClassList' => array(''),
        );
        $options = array_merge($defaults, array_intersect_key($options, $defaults));
        list($role, $expireTime, $data, $initialLayoutClassList) = array_values($options);

        // additional token data
        $createTime = time();
        $nonce = microtime(true) . mt_rand();

        // validate arguments
        Validators::validateSessionIdBelongsToKey($sessionId, $this->apiKey);
        Validators::validateRole($role);
        Validators::validateExpireTime($expireTime, $createTime);
        Validators::validateData($data);
        Validators::validateLayoutClassList($initialLayoutClassList, 'JSON');

        $dataString = "session_id=$sessionId&create_time=$createTime&role=$role&nonce=$nonce" .
            (($expireTime) ? "&expire_time=$expireTime" : '') .
            (($data) ? "&connection_data=" . urlencode($data) : '') .
            ((!empty($initialLayoutClassList)) ? "&initial_layout_class_list=" . urlencode(join(" ", $initialLayoutClassList)) : '');
        $sig = $this->signString($dataString, $this->apiSecret);

        return "T1==" . base64_encode("partner_id=$this->apiKey&sig=$sig:$dataString");
    }

    /**
    * Creates a new OpenTok session and returns the session ID, which uniquely identifies
    * the session.
    * <p>
    * For example, when using the OpenTok JavaScript library, use the session ID when calling the
    * <a href="http://tokbox.com/opentok/libraries/client/js/reference/OT.html#initSession">
    * OT.initSession()</a> method (to initialize an OpenTok session).
    * <p>
    * OpenTok sessions do not expire. However, authentication tokens do expire (see the
    * generateToken() method). Also note that sessions cannot explicitly be destroyed.
    * <p>
    * A session ID string can be up to 255 characters long.
    * <p>
    * Calling this method results in an OpenTokException in the event of an error.
    * Check the error message for details.
    * <p>
    * You can also create a session by logging in to your
    * <a href="https://tokbox.com/account">OpenTok Video API account</a>.
    *
    * @param array $options (Optional) This array defines options for the session. The array includes
    * the following keys (all of which are optional):
    *
    * <ul>
    *
    *    <li><code>'archiveMode'</code> (ArchiveMode) &mdash; Whether the session is automatically
    *    archived (<code>ArchiveMode::ALWAYS</code>) or not (<code>ArchiveMode::MANUAL</code>).
    *    By default, the setting is <code>ArchiveMode.MANUAL</code>, and you must call the
    *    <code>OpenTok->startArchive()</code> method to start archiving. To archive the session
    *    (either automatically or not), you must set the <code>mediaMode</code> key to
    *    <code>MediaMode::ROUTED</code>.</li>
    *
    *    <li><code>'e2ee'</code> (Boolean) &mdash; Whether to enable
    *    <a href="https://tokbox.com/developer/guides/end-to-end-encryption">end-to-end encryption</a>
    *    for a routed session.</li>
    *
    *    <li><code>archiveName</code> (String) &mdash; Name of the archives in auto archived sessions</li>
     *
     *   <li><code>archiveResolution</code> (Enum) &mdash; Resolution of the archives in
     *   auto archived sessions. Can be one of "480x640", "640x480", "720x1280", "1280x720", "1080x1920", "1920x1080"</li>
    *
    *    <li><code>'location'</code> (String) &mdash; An IP address that the OpenTok servers
    *    will use to situate the session in its global network. If you do not set a location hint,
    *    the OpenTok servers will be based on the first client connecting to the session.</li>
    *
    *    <li><code>'mediaMode'</code> (MediaMode) &mdash; Whether the session will transmit
    *    streams using the OpenTok Media Router (<code>MediaMode.ROUTED</code>) or not
    *    (<code>MediaMode.RELAYED</code>). By default, the <code>mediaMode</code> property
    *    is set to <code>MediaMode.RELAYED</code>.
    *
    *    <p>
    *    With the <code>mediaMode</code> parameter set to <code>MediaMode.RELAYED</code>, the
    *    session will attempt to transmit streams directly between clients. If clients cannot
    *    connect due to firewall restrictions, the session uses the OpenTok TURN server to relay
    *    audio-video streams.
    *
    *    <p>
    *    The
    *    <a href="https://tokbox.com/opentok/tutorials/create-session/#media-mode" target="_top">
    *    OpenTok Media Router</a> provides the following benefits:
    *
    *    <ul>
    *       <li>The OpenTok Media Router can decrease bandwidth usage in multiparty sessions.
    *           (When the <code>mediaMode</code> parameter is set to <code>MediaMode.ROUTED</code>,
    *           each client must send a separate audio-video stream to each client subscribing to
    *           it.)</li>
    *       <li>The OpenTok Media Router can improve the quality of the user experience through
    *         recovery</a>. With these features, if a client's connectivity degrades to a degree
    *         that it does not support video for a stream it's subscribing to, the video is dropped
    *         on that client (without affecting other clients), and the client receives audio only.
    *         If the client's connectivity improves, the video returns.</li>
    *       <li>The OpenTok Media Router supports the
    *         <a href="https://tokbox.com/opentok/tutorials/archiving" target="_top">archiving</a>
    *         feature, which lets you record, save, and retrieve OpenTok sessions.</li>
    *    </ul>
    *
    * </ul>
    *
    * @return \OpenTok\Session A Session object representing the new session. Call the
    * <code>getSessionId()</code> method of this object to get the session ID. For example,
    * when using the OpenTok.js library, use this session ID when calling the
    * <code>OT.initSession()</code> method.
    */
    public function createSession($options = array())
    {
        if (
            array_key_exists('archiveMode', $options) &&
            $options['archiveMode'] !== ArchiveMode::MANUAL
        ) {
            if (
                array_key_exists('mediaMode', $options) &&
                $options['mediaMode'] !== MediaMode::ROUTED
            ) {
                throw new InvalidArgumentException('A session must be routed to be archived.');
            } else {
                $options['mediaMode'] = MediaMode::ROUTED;
            }
        }

        if (array_key_exists('e2ee', $options) && $options['e2ee']) {

            if (array_key_exists('mediaMode', $options) && $options['mediaMode'] !== MediaMode::ROUTED) {
                throw new InvalidArgumentException('MediaMode must be routed in order to enable E2EE');
            }

            if (array_key_exists('archiveMode', $options) && $options['archiveMode'] === ArchiveMode::ALWAYS) {
                throw new InvalidArgumentException('ArchiveMode cannot be set to always when using E2EE');
            }

            $options['e2ee'] = 'true';
        }

        // unpack optional arguments (merging with default values) into named variables
        $defaults = array(
            'mediaMode' => MediaMode::RELAYED,
            'archiveMode' => ArchiveMode::MANUAL,
            'location' => null,
            'e2ee' => 'false',
            'archiveName' => null,
            'archiveResolution' => null
        );

        // Have to hack this because the default system in these classes needs total refactor
        $resolvedArchiveMode = array_merge($defaults, array_intersect_key($options, $defaults));

        if ($resolvedArchiveMode['archiveMode'] === ArchiveMode::ALWAYS) {
            $defaults['archiveResolution'] = '640x480';
        }

        $options = array_merge($defaults, array_intersect_key($options, $defaults));

        // Have to hack this because the default system in these classes needs total refactor
        if ($options['archiveName'] === null) {
            unset($options['archiveName']);
        }

        if ($options['archiveResolution'] === null) {
            unset($options['archiveResolution']);
        }

        list($mediaMode, $archiveMode, $location, $e2ee) = array_values($options);

        // validate arguments
        Validators::validateMediaMode($mediaMode);
        Validators::validateArchiveMode($archiveMode);
        Validators::validateAutoArchiveMode($archiveMode, $options);
        Validators::validateLocation($location);

        // make API call
        $sessionXml = $this->client->createSession($options);

        // check response
        $sessionId = $sessionXml->Session->session_id;
        if (!$sessionId) {
            $errorMessage = 'Failed to create a session. Server response: ' . $sessionXml;
            throw new UnexpectedValueException($errorMessage);
        }

        return new Session($this, (string)$sessionId, array(
            'location' => $location,
            'mediaMode' => $mediaMode,
            'archiveMode' => $archiveMode,
            'e2ee' => $e2ee
        ));
    }

    /**
     * Starts an Experience Composer renderer for an OpenTok session.
     * For more information, see the
     * <a href="https://tokbox.com/developer/guides/experience-composer">Experience Composer
     * developer guide</a>.
     *
     * @param $sessionId (string) The session ID of the OpenTok session that will include the Experience Composer stream.
     *
     * @param $token (string) A valid OpenTok token with a Publisher role and (optionally) connection data to be associated with the output stream.
     *
     * @param $url (string) A publicly reachable URL controlled by the customer and capable of generating the content to be rendered without user intervention.
     * The minimum length of the URL is 15 characters and the maximum length is 2048 characters.
     *
     * @param $maxDuration (int) (optional) The maximum time allowed for the Experience Composer, in seconds. After this time, it is stopped
     * automatically, if it is still running. The maximum value is 36000 (10 hours), the minimum value is 60 (1 minute), and the default value is 7200 (2 hours).
     * When the Experience Composer ends, its stream is unpublished and an event is posted to the callback URL, if configured in the Account Portal.
     *
     * @param $resolution (string) (optional) The resolution of the Experience Composer, either "640x480" (SD landscape), "480x640" (SD portrait), "1280x720" (HD landscape),
     * "720x1280" (HD portrait), "1920x1080" (FHD landscape), or "1080x1920" (FHD portrait). By default, this resolution is "1280x720" (HD landscape, the default).
     *
     * @param $properties (array) (optional) The initial configuration of Publisher properties for the composed output stream.
     * <ul>
     *   <li><code>name</code> (String) (optional) &mdash; Serves as the name of the composed output stream which is published to the session. The name must have a minimum length of 1 and
     *     a maximum length of 200.
     *   </li>
     * </ul>
     *
     * @return \OpenTok\Render The render object, which includes properties defining the render, including the render ID.
     */
    public function startRender(
        $sessionId,
        $token,
        $url,
        $maxDuration,
        $resolution,
        $properties
    ): Render {
        $arguments = [
            'sessionId' => $sessionId,
            'token' => $token,
            'url' => $url,
            'maxDuration' => $maxDuration,
            'resolution' => $resolution,
            'properties' => $properties
        ];

        $defaults = [
            'maxDuration' => 1800,
            'resolution' => '1280x720',
        ];

        $payload = array_merge($defaults, $arguments);
        Validators::validateSessionId($payload['sessionId']);

        $render = $this->client->startRender($payload);

        return new Render($render);
    }

    /**
     * Returns a list of Experience Composer renderers for an OpenTok project.
     *
     * @param int $offset
     * @param int $count
     *
     * @return mixed
     */
    public function listRenders(int $offset = 0, int $count = 50)
    {
        $queryPayload = [
            'offset' => $offset,
            'count' => $count
        ];
        return $this->client->listRenders($queryPayload);
    }

    /**
     * Stops an existing render.
     *
     * @param $renderId
     *
     * @return mixed
     */
    public function stopRender($renderId)
    {
        return $this->client->stopRender($renderId);
    }

    /**
     * Fetch an existing render to view status. Status can be one of:
     * <ul>
     *    <li><code>starting</code> &mdash; The Vonage Video API platform is in the process of connecting to the remote application at the URL provided. This is the initial state.</li>
     *    <li><code>started</code> &mdash; The Vonage Video API platform has succesfully connected to the remote application server, and is republishing that media into the Vonage Video API platform.</li>
     *    <li><code>stopped</code> &mdash; The Render has stopped.</li>
     *    <li><code>failed</code> &mdash; An error occurred and the Render could not proceed. It may occur at startup if the opentok server cannot connect to the remote application server or republish the stream. It may also occur at point during the rendering process due to some error in the Vonage Video API platform.</li>
     * </ul>
     *
     * @param $renderId
     *
     * @return Render
     */
    public function getRender($renderId): Render
    {
        $renderPayload = $this->client->getRender($renderId);

        return new Render($renderPayload);
    }

    /**
     * Starts archiving an OpenTok session.
     * <p>
     * Clients must be actively connected to the OpenTok session for you to successfully start
     * recording an archive.
     * <p>
     * You can only record one archive at a time for a given session. You can only record archives
     * of sessions that use the OpenTok Media Router (sessions with the
     * <a href="http://tokbox.com/opentok/tutorials/create-session/#media-mode">media mode</a>
     * set to routed); you cannot archive sessions with the media mode set to relayed.
     * <p>
     * For more information on archiving, see the
     * <a href="https://tokbox.com/opentok/tutorials/archiving/">OpenTok archiving</a> programming
     * guide.
     *
     * @param String $sessionId The session ID of the OpenTok session to archive.
     * @param array $options (Optional) This array defines options for the archive. The array
     * includes the following keys (all of which are optional):
     *
     * <ul>
     *
     *    <li><code>'name'</code> (String) &mdash; The name of the archive. You can use this name to
     *    identify the archive. It is a property of the Archive object, and it is a property of
     *    archive-related events in the OpenTok client SDKs.</li>
     *
     *    <li><code>'hasVideo'</code> (Boolean) &mdash; Whether the archive will record video
     *    (true, the default) or not (false). If you set both <code>hasAudio</code> and
     *    <code>hasVideo</code> to false, the call to the <code>startArchive()</code> method results
     *    in an error.</li>
     *
     *    <li><code>'streamMode'</code> (String) &mdash; Whether streams included in the archive are
     *    selected automatically (<code>StreamMode.AUTO</code>, the default) or manually
     *    (<code>StreamMode.MANUAL</code>). When streams are selected automatically
     *    (<code>StreamMode.AUTO</code>), all streams in the session can be included in the archive.
     *    When streams are selected manually (<code>StreamMode.MANUAL</code>), you specify streams
     *    to be included based on calls to the <code>Archive.addStreamToArchive()</code> and
     *    <code>Archive.removeStreamFromArchive()</code>. methods. With manual mode, you can specify
     *    whether a stream's audio, video, or both are included in the archive. In both automatic and
     *    manual modes, the archive composer includes streams based on
     *    <a href="https://tokbox.com/developer/guides/archive-broadcast-layout/#stream-prioritization-rules">stream
     *    prioritization rules</a>.</li>
     *
     *    <li><code>'hasAudio'</code> (Boolean) &mdash; Whether the archive will record audio
     *    (true, the default) or not (false). If you set both <code>hasAudio</code> and
     *    <code>hasVideo</code> to false, the call to the <code>startArchive()</code> method results
     *    in an error.</li>
     *
     *    <li><code>'multiArchiveTag'</code> (String) (Optional) &mdash; Set this to support recording multiple archives
     *    for the same session simultaneously. Set this to a unique string for each simultaneous archive of an ongoing
     *    session. You must also set this option when manually starting an archive
     *    that is {https://tokbox.com/developer/guides/archiving/#automatic automatically archived}.
     *    Note that the `multiArchiveTag` value is not included in the response for the methods to
     *    {https://tokbox.com/developer/rest/#listing_archives list archives} and
     *    {https://tokbox.com/developer/rest/#retrieve_archive_info retrieve archive information}.
     *    If you do not specify a unique `multiArchiveTag`, you can only record one archive at a time for a given session.
     *    {https://tokbox.com/developer/guides/archiving/#simultaneous-archives See Simultaneous archives}.</li>
     *
     *    <li><code>'outputMode'</code> (OutputMode) &mdash; Whether all streams in the
     *    archive are recorded to a single file (<code>OutputMode::COMPOSED</code>, the default)
     *    or to individual files (<code>OutputMode::INDIVIDUAL</code>).</li>
     *
     *    <li><code>'resolution'</code> (String) &mdash; The resolution of the archive, either "640x480" (SD landscape,
     *    the default), "1280x720" (HD landscape), "1920x1080" (FHD landscape), "480x640" (SD portrait), "720x1280"
     *    (HD portrait), or "1080x1920" (FHD portrait). This property only applies to composed archives. If you set
     *    this property and set the outputMode property to "individual", a call to the method
     *    results in an error.</li>
     * </ul>
     *
     * @return Archive The Archive object, which includes properties defining the archive, including
     * the archive ID.
     */
    public function startArchive(string $sessionId, $options = []): Archive
    {
        // support for deprecated method signature, remove in v3.0.0 (not before)
        if (!is_array($options)) {
            trigger_error(
                'Archive options passed as a string is deprecated, please pass an array with a name key',
                E_USER_DEPRECATED
            );
            $options = array('name' => $options);
        }

        // unpack optional arguments (merging with default values) into named variables
        $defaults = array(
            'name' => null,
            'hasVideo' => true,
            'hasAudio' => true,
            'outputMode' => OutputMode::COMPOSED,
            'resolution' => null,
            'streamMode' => StreamMode::AUTO,
        );
        $options = array_merge($defaults, array_intersect_key($options, $defaults));
        list($name, $hasVideo, $hasAudio, $outputMode, $resolution, $streamMode) = array_values($options);

        Validators::validateSessionId($sessionId);
        Validators::validateArchiveName($name);

        if ($resolution) {
            Validators::validateResolution($resolution);
        }

        Validators::validateArchiveHasVideo($hasVideo);
        Validators::validateArchiveHasAudio($hasAudio);
        Validators::validateArchiveOutputMode($outputMode);
        Validators::validateHasStreamMode($streamMode);

        if ((is_null($resolution) || empty($resolution)) && $outputMode === OutputMode::COMPOSED) {
            $options['resolution'] = "640x480";
        } elseif ((is_null($resolution) || empty($resolution)) && $outputMode === OutputMode::INDIVIDUAL) {
            unset($options['resolution']);
        } elseif (!empty($resolution) && $outputMode === OutputMode::INDIVIDUAL) {
            $errorMessage = "Resolution can't be specified for Individual Archives";
            throw new UnexpectedValueException($errorMessage);
        } elseif (!empty($resolution) && $outputMode === OutputMode::COMPOSED && !is_string($resolution)) {
            $errorMessage = "Resolution must be a valid string";
            throw new UnexpectedValueException($errorMessage);
        }

        $archiveData = $this->client->startArchive($sessionId, $options);

        return new Archive($archiveData, array( 'client' => $this->client ));
    }

    /**
     * Stops an OpenTok archive that is being recorded.
     * <p>
     * Archives automatically stop recording after 120 minutes or when all clients have disconnected
     * from the session being archived.
     *
     * @param String $archiveId The archive ID of the archive you want to stop recording.
     * @return Archive The Archive object corresponding to the archive being stopped.
     */
    public function stopArchive($archiveId)
    {
        Validators::validateArchiveId($archiveId);

        $archiveData = $this->client->stopArchive($archiveId);
        return new Archive($archiveData, array( 'client' => $this->client ));
    }

    /**
     * Gets an Archive object for the given archive ID.
     *
     * @param String $archiveId The archive ID.
     *
     * @throws ArchiveException There is no archive with the specified ID.
     * @throws InvalidArgumentException The archive ID provided is null or an empty string.
     *
     * @return Archive The Archive object.
     */
    public function getArchive($archiveId)
    {
        Validators::validateArchiveId($archiveId);

        $archiveData = $this->client->getArchive($archiveId);
        return new Archive($archiveData, array( 'client' => $this->client ));
    }

    /**
     * Deletes an OpenTok archive.
     * <p>
     * You can only delete an archive which has a status of "available", "uploaded", or "deleted".
     * Deleting an archive removes its record from the list of archives. For an "available" archive,
     * it also removes the archive file, making it unavailable for download. For a "deleted"
     * archive, the archive remains deleted.
     *
     * @param String $archiveId The archive ID of the archive you want to delete.
     *
     * @return Boolean Returns true on success.
     *
     * @throws ArchiveException There archive status is not "available", "updated",
     * or "deleted".
     */
    public function deleteArchive($archiveId)
    {
        Validators::validateArchiveId($archiveId);

        return $this->client->deleteArchive($archiveId);
    }

    /**
     * Returns a list of archives for a session.
     *
     * The <code>items()</code> method of this object returns a list of
     * archives that are completed and in-progress, for your API key.
     *
     * @param integer $offset Optional. The index offset of the first archive. 0 is offset of the
     * most recently started archive. 1 is the offset of the archive that started prior to the most
     * recent archive. If you do not specify an offset, 0 is used.
     * @param integer $count Optional. The number of archives to be returned. The maximum number of
     * archives returned is 1000.
     * @param string $sessionId Optional. The OpenTok session Id for which you want to retrieve Archives for. If no session Id
     * is specified, the method will return archives from all sessions created with the API key.
     *
     * @return ArchiveList An ArchiveList object. Call the items() method of the ArchiveList object
     * to return an array of Archive objects.
     */
    public function listArchives($offset = 0, $count = null, $sessionId = null)
    {
        // validate params
        Validators::validateOffsetAndCount($offset, $count);
        if (!is_null($sessionId)) {
            Validators::validateSessionIdBelongsToKey($sessionId, $this->apiKey);
        }

        $archiveListData = $this->client->listArchives($offset, $count, $sessionId);
        return new ArchiveList($archiveListData, array( 'client' => $this->client ));
    }


    /**
     * Updates the stream layout in an OpenTok Archive.
     */
    public function setArchiveLayout(string $archiveId, Layout $layoutType): void
    {
        Validators::validateArchiveId($archiveId);

        $this->client->setArchiveLayout($archiveId, $layoutType);
    }

    /**
     * Sets the layout class list for streams in a session.
     *
     * Layout classes are used in
     * the layout for composed archives and live streaming broadcasts. For more information, see
     * <a href="https://tokbox.com/developer/guides/archiving/layout-control.html">Customizing
     * the video layout for composed archives</a> and
     * <a href="https://tokbox.com/developer/guides/broadcast/live-streaming/#configuring-video-layout-for-opentok-live-streaming-broadcasts">Configuring
     * video layout for OpenTok live streaming broadcasts</a>.
     * @param string $sessionId The session ID of the session the streams belong to.
     *
     * @param array $classListArray The connectionId of the connection in a session.
     */

    public function setStreamClassLists($sessionId, $classListArray = array())
    {
        Validators::validateSessionIdBelongsToKey($sessionId, $this->apiKey);

        foreach ($classListArray as $item) {
            Validators::validateLayoutClassListItem($item);
        }

        $this->client->setStreamClassLists($sessionId, $classListArray);
    }


    /**
     * Disconnects a specific client from an OpenTok session.
     *
     * @param string $sessionId The OpenTok session ID that the client is connected to.
     *
     * @param string $connectionId The connection ID of the connection in the session.
     */

    public function forceDisconnect($sessionId, $connectionId)
    {
        Validators::validateSessionIdBelongsToKey($sessionId, $this->apiKey);
        Validators::validateConnectionId($connectionId);

        return $this->client->forceDisconnect($sessionId, $connectionId);
    }

    /**
     * Force the publisher of a specific stream to mute its published audio.
     *
     * <p>
     * Also see the
     * <a href="classes/OpenTok-OpenTok.html#method_forceMuteAll">OpenTok->forceMuteAll()</a>
     * method.
     *
     * @param string $sessionId The OpenTok session ID containing the stream.
     *
     * @param string $streamId The stream ID.
     *
     * @return bool Whether the call succeeded or failed.
     */
    public function forceMuteStream(string $sessionId, string $streamId): bool
    {
        Validators::validateSessionId($sessionId);
        Validators::validateStreamId($streamId);

        try {
            $this->client->forceMuteStream($sessionId, $streamId);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Force all streams (except for an optional list of streams) in an OpenTok session
     * to mute published audio.
     *
     * <p>
     * In addition to existing streams, any streams that are published after the call to
     * this method are published with audio muted. You can remove the mute state of a session
     * <a href="classes/OpenTok-OpenTok.html#method_disableForceMute">OpenTok->disableForceMute()</a>
     * method.
     * <p>
     * Also see the
     * <a href="classes/OpenTok-OpenTok.html#method_forceMuteStream">OpenTok->forceMuteStream()</a>
     * method.
     *
     * @param string $sessionId The OpenTok session ID.
     *
     * @param array<string> $options This array defines options and includes the following keys:
     *
     * <ul>
     *    <li><code>'excludedStreams'</code> (array, optional) &mdash; An array of stream IDs
     *    corresponding to streams that should not be muted. This is an optional property.
     *    If you omit this property, all streams in the session will be muted.</li>
     * </ul>
     *
     * @return bool Whether the call succeeded or failed.
     */
    public function forceMuteAll(string $sessionId, array $options): bool
    {
        // Active is always true when forcing mute all
        $options['active'] = true;

        Validators::validateSessionId($sessionId);
        Validators::validateForceMuteAllOptions($options);

        try {
            $this->client->forceMuteAll($sessionId, $options);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Disables the active mute state of the session. After you call this method, new streams
     * published to the session will no longer have audio muted.
     *
     * <p>
     * After you call the
     * <a href="classes/OpenTok-OpenTok.html#method_forceMuteAll">OpenTok->forceMuteAll()</a> method
     * any streams published after the call are published with audio muted. Call the
     * <c>disableForceMute()</c> method to remove the mute state of a session, so that
     * new published streams are not automatically muted.
     *
     * @param string $sessionId The OpenTok session ID.
     *
     * @param array<string> $options This array defines options and includes the following keys:
     *
     * <ul>
     *    <li><code>'excludedStreams'</code> (array, optional) &mdash; An array of stream IDs
     *    corresponding to streams that should not be muted. This is an optional property.
     *    If you omit this property, all streams in the session will be muted.</li>
     * </ul>
     *
     * @return bool Whether the call succeeded or failed.
     */
    public function disableForceMute(string $sessionId, array $options): bool
    {
        // Active is always false when disabling force mute
        $options['active'] = false;

        Validators::validateSessionId($sessionId);
        Validators::validateForceMuteAllOptions($options);

        try {
            $this->client->forceMuteAll($sessionId, $options);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Starts a live streaming broadcast of an OpenTok session.
     *
     * @param String $sessionId The session ID of the session to be broadcast.
     *
     * @param array $options (Optional) An array with options for the broadcast. This array has
     * the following properties:
     *
     * <ul>
     *   <li><code>layout</code> (Layout) &mdash; (Optional) An object defining the initial
     *     layout type of the broadcast. If you do not specify an initial layout type,
     *     the broadcast stream uses the Best Fit layout type. For more information, see
     *     <a href="https://tokbox.com/developer/guides/broadcast/live-streaming/#configuring-live-streaming-video-layout">Configuring
     *     Video Layout for the OpenTok live streaming feature</a>.
     *   </li>
     *
     *    <li><code>streamMode</code> (String) &mdash; Whether streams included in the broadcast
     *    are selected automatically (<code>StreamMode.AUTO</code>, the default) or manually
     *    (<code>StreamMode.MANUAL</code>). When streams are selected automatically
     *    (<code>StreamMode.AUTO</code>), all streams in the session can be included in the broadcast.
     *    When streams are selected manually (<code>StreamMode.MANUAL</code>), you specify streams
     *    to be included based on calls to the <code>Broadcast.addStreamToBroadcast()</code> and
     *    <code>Broadcast.removeStreamFromBroadcast()</code> methods. With manual mode, you can specify
     *    whether a stream's audio, video, or both are included in the broadcast. In both automatic and
     *    manual modes, the broadcast composer includes streams based on
     *    <a href="https://tokbox.com/developer/guides/archive-broadcast-layout/#stream-prioritization-rules">stream
     *    prioritization rules</a>.</li>
     *
     *    <li><code>multiBroadcastTag</code> (String) (Optional) &mdash; Set this to support multiple broadcasts for
     *    the same session simultaneously. Set this to a unique string for each simultaneous broadcast of an ongoing session.
     *    Note that the `multiBroadcastTag` value is *not* included in the response for the methods to
     *    {https://tokbox.com/developer/rest/#list_broadcasts list live streaming broadcasts} and
     *    {https://tokbox.com/developer/rest/#get_info_broadcast get information about a live streaming broadcast}.
     *    {https://tokbox.com/developer/guides/broadcast/live-streaming#simultaneous-broadcasts See Simultaneous broadcasts}.</li>
     *
     *    <li><code>resolution</code> &mdash; The resolution of the broadcast: either "640x480" (SD landscape, the default), "1280x720" (HD landscape),
     *    "1920x1080" (FHD landscape), "480x640" (SD portrait), "720x1280" (HD portrait), or "1080x1920"
     *    (FHD portrait).</li>
     *
     *    <li><code>outputs</code> (Array) &mdash;
     *      Defines the HLS broadcast and RTMP streams. You can provide the following keys:
     *      <ul>
     *        <li><code>hls</code> (Array) &mdash; available with the following options:
     *          <p>
     *            <ul>
     *              <li><code>'dvr'</code> (Bool) &mdash; Whether to enable
     *                <a href="https://tokbox.com/developer/guides/broadcast/live-streaming/#dvr">DVR functionality</a>
     *                — rewinding, pausing, and resuming — in players that support it (<code>true</code>),
     *                or not (<code>false</code>, the default). With DVR enabled, the HLS URL will include
     *                a ?DVR query string appended to the end.
     *              </li>
     *              <li><code>'lowLatency'</code> (Bool) &mdash; Whether to enable
     *                <a href="https://tokbox.com/developer/guides/broadcast/live-streaming/#low-latency">low-latency mode</a>
     *                for the HLS stream. Some HLS players do not support low-latency mode. This feature
     *                is incompatible with DVR mode HLS broadcasts.
     *              </li>
     *            </ul>
     *           </p>
     *        </li>
     *        <li><code>rtmp</code> (Array) &mdash; An array of arrays defining RTMP streams to broadcast. You
     *          can specify up to five target RTMP streams. Each RTMP stream array has the following keys:
     *          <ul>
     *            <li><code>id</code></code> (String) &mdash; The stream ID (optional)</li>
     *            <li><code>serverUrl</code> (String) &mdash; The RTMP server URL</li>
     *            <li><code>streamName</code> (String) &mdash; The stream name, such as
     *                the YouTube Live stream name or the Facebook stream key</li>
     *          </ul>
     *        </li>
     *      </ul>
     * </ul>
     *
     * @return Broadcast An object with properties defining the broadcast.
     */
    public function startBroadcast(string $sessionId, array $options = []): Broadcast
    {
        // unpack optional arguments (merging with default values) into named variables
        // NOTE: although the server can be authoritative about the default value of layout, its
        // not preferred to depend on that in the SDK because its then harder to garauntee backwards
        // compatibility

        if (isset($options['maxBitRate'])) {
            Validators::validateBroadcastBitrate($options['maxBitRate']);
        }

        if (isset($options['resolution'])) {
            Validators::validateResolution($options['resolution']);
        }

	    if (isset($options['outputs']['hls'])) {
		    Validators::validateBroadcastOutputOptions($options['outputs']['hls']);
	    }

		if (isset($options['outputs']['rtmp'])) {
			Validators::validateRtmpStreams($options['outputs']['rtmp']);
		}

        $defaults = [
            'layout' => Layout::getBestFit(),
            'hasAudio' => true,
            'hasVideo' => true,
            'streamMode' => 'auto',
            'resolution' => '640x480',
            'maxBitRate' => 2000000,
	        'outputs' => [
				'hls' => [
	                'dvr' => false,
					'lowLatency' => false
				]
            ]
        ];

        $options = array_merge($defaults, $options);

        Validators::validateSessionId($sessionId);
        Validators::validateLayout($options['layout']);
        Validators::validateHasStreamMode($options['streamMode']);

        $broadcastData = $this->client->startBroadcast($sessionId, $options);

        return new Broadcast($broadcastData, ['client' => $this->client]);
    }

    /**
     * Stops a broadcast.
     *
     * @param String $broadcastId The ID of the broadcast.
     */
    public function stopBroadcast($broadcastId): Broadcast
    {
        // validate arguments
        Validators::validateBroadcastId($broadcastId);

        // make API call
        $broadcastData = $this->client->stopBroadcast($broadcastId);
        return new Broadcast($broadcastData, array(
            'client' => $this->client,
            'isStopped' => true
        ));
    }

    /**
     * Gets information about an OpenTok broadcast.
     *
     * @param String $broadcastId The ID of the broadcast.
     *
     * @return Broadcast An object with properties defining the broadcast.
     */
    public function getBroadcast($broadcastId): Broadcast
    {
        Validators::validateBroadcastId($broadcastId);

        $broadcastData = $this->client->getBroadcast($broadcastId);
        return new Broadcast($broadcastData, array( 'client' => $this->client ));
    }

    // TODO: not yet implemented by the platform
    // public function getBroadcastLayout($broadcastId)
    // {
    //     Validators::validateBroadcastId($broadcastId);
    //
    //     $layoutData = $this->client->getLayout($broadcastId, 'broadcast');
    //     return Layout::fromData($layoutData);
    // }

    /**
     * Updates the layout of the broadcast.
     * <p>
     * See <a href="https://tokbox.com/developer/guides/broadcast/live-streaming/#configuring-video-layout-for-opentok-live-streaming-broadcasts">Configuring
     * video layout for OpenTok live streaming broadcasts</a>.
     *
     * @param String $broadcastId The ID of the broadcast.
     *
     * @param Layout $layout An object defining the layout type for the broadcast.
     */
    public function updateBroadcastLayout(string $broadcastId, Layout $layout): void
    {
        Validators::validateBroadcastId($broadcastId);

        $this->client->updateLayout($broadcastId, $layout, 'broadcast');
    }

    /**
    * Sets the layout class list for a stream.
    *
    * Layout classes are used in
    * the layout for composed archives and live streaming broadcasts.
    * <p>
    * For more information, see
    * <a href="https://tokbox.com/developer/guides/archiving/layout-control.html">Customizing
    * the video layout for composed archives</a> and
    * <a href="https://tokbox.com/developer/guides/broadcast/live-streaming/#configuring-video-layout-for-opentok-live-streaming-broadcasts">Configuring
    * video layout for OpenTok live streaming broadcasts</a>.
    *
    * <p>
    * You can set the initial layout class list for streams published by a client when you generate
    * the token used by the client to connect to the session. See the
    * <a href="classes/OpenTok-OpenTok.html#method_generateToken">OpenTok::generateToken()</a>
    * method.
    *
    * @param string $sessionId The session ID of the session the stream belongs to.
    *
    * @param string $streamId The stream ID.
    *
    * @param array $properties An array containing one property: <code>$layoutClassList</code>.
    * This property is an array of class names (strings) to apply to the
    * stream. Set <code>$layoutClassList</code> to an empty array to clear the layout class list for
    * a stream. For example, this code sets the stream to use two classes:
    * <p>
    * <pre>
    * $streamProperties = array(
    *   '$layoutClassList' => array('bottom', 'right')
    * );
    * $opentok->updateStream($sessionId, $streamId, $streamProperties);
    * </pre>
    */
    public function updateStream($sessionId, $streamId, $properties = array())
    {
        // unpack optional arguments (merging with default values) into named variables
        $defaults = array(
            'layoutClassList' => array()
        );
        $properties = array_merge($defaults, array_intersect_key($properties, $defaults));
        list($layoutClassList) = array_values($properties);

        // validate arguments
        Validators::validateSessionId($sessionId);
        Validators::validateStreamId($streamId);
        Validators::validateLayoutClassList($layoutClassList, 'JSON');

        // make API call
        $this->client->updateStream($sessionId, $streamId, $properties);
    }

    /**
     * Gets an Stream object, providing information on a given stream.
     *
     * @param String $sessionId The session ID for the OpenTok session containing the stream.
     *
     * @param String $streamId The stream ID.
     *
     * @return Stream The Stream object.
     */

    public function getStream($sessionId, $streamId)
    {
        Validators::validateSessionId($sessionId);
        Validators::validateStreamId($streamId);

        // make API call
        $streamData = $this->client->getStream($sessionId, $streamId);
        return new Stream($streamData);
    }

    /**
     *  Returns a StreamList Object for the given session ID.
     *
     * @param String $sessionId The session ID.
     *
     * @return StreamList A StreamList object. Call the items() method of the StreamList object
     * to return an array of Stream objects.
     */

    public function listStreams($sessionId)
    {
        Validators::validateSessionIdBelongsToKey($sessionId, $this->apiKey);

        // make API call
        $streamListData = $this->client->listStreams($sessionId);
        return new StreamList($streamListData);
    }

    /**
     * Initiates an outgoing SIP call.
     * <p>
     * For more information, see the
     * <a href="https://tokbox.com/developer/guides/sip">OpenTok SIP Interconnect
     * developer guide</a>.
     *
     * @param string $sessionId The ID of the OpenTok session that the participant being called
     * will join.
     *
     * @param string $token The OpenTok token to be used for the participant being called.
     * You can add token data to identify that the participant is on a SIP endpoint or for
     * other identifying data, such as phone numbers. Generate a token using the
     * <a href="classes/OpenTok-OpenTok.html#method_generateToken">OpenTok::generateToken()</a> or
     * <a href="classes/OpenTok-Session.html#method_generateToken">Session::generateToken()</a>
     * method.
     *
     * @param string $sipUri The SIP URI to be used as destination of the SIP Call initiated from
     * OpenTok to the Third Party SIP Platform.
     * <p>
     * If the URI contains a transport=tlsheader, the negotiation between TokBox and
     * the SIP endpoint will be done securely. Note that this will only apply to the negotiation
     * itself, and not to the transmission of audio. To have audio transmission be encrypted,
     * see the "secure" property of the <code>options</code> parameter.
     * <p>
     * This is an example of secure call negotiation: "sip:access@thirparty.com;transport=tls".
     * <p>
     * This is an example of insecure call negotiation: "sip:access@thirparty.com".
     *
     * @param array $options This array defines options for the SIP call. It includes the
     * following keys, all of which are optional:
     *
     * <ul>
     *    <li><code>'headers'</code> (array) &mdash; Headers​: Custom Headers to be added to the
     *    SIP INVITE request initiated from OpenTok to the Third Party SIP Platform.</li>
     *
     *    <li><code>'auth'</code> (array) &mdash; Auth​: Username and Password to be used in the SIP
     *    INVITE request for HTTP Digest authentication in case this is required by the Third Party
     *    SIP Platform.
     *
     *    <ul>
     *
     *    <li><code>'username'</code> (string) &mdash; The username to be used
     *    in the the SIP INVITE​ request for HTTP digest authentication (if one
     *    is required).</li>
     *
     *    <li><code>'password'</code> (string) &mdash; The password to be used
     *    in the the SIP INVITE​ request for HTTP digest authentication.</li>
     *
     *    </ul>
     *
     *    <li><code>'secure'</code> (Boolean) &mdash; Indicates whether the media
     *    must be transmitted encrypted (true, the default) or not (false).</li>
     *
     *    <li><code>'observeForceMute'</code> (Boolean) &mdash; Whether the SIP endpoint should honor
     *    <a href="https://tokbox.com/developer/guides/moderation/#force_mute">force mute moderation</a>
     *    (True) or not (False, the default).</li>
     *
     *    <li><code>'from'</code> (string) &mdash; The number or string that will be sent to
     *    the final SIP number as the caller. It must be a string in the form of
     *    "from@example.com", where from can be a string or a number. If from is set to a number
     *    (for example, "14155550101@example.com"), it will show up as the incoming number
     *    on PSTN phones. If from is undefined or set to a string (for example, "joe@example.com"),
     *    +00000000 will show up as the incoming number on PSTN phones.</li>
     *
     * </ul>
     *
     * @return SipCall An object contains the OpenTok connection ID and stream ID
     * for the SIP call's connection in the OpenTok session. You can use the connection ID
     * to terminate the SIP call, using the
     * <a href="classes/OpenTok-OpenTok.html#method_forceDisconnect">OpenTok::method_forceDisconnect()</a>
     * method.
     */
    public function dial($sessionId, $token, $sipUri, $options = [])
    {
        // unpack optional arguments (merging with default values) into named variables
        $defaults = array(
            'auth' => null,
            'headers' => [],
            'secure' => true,
            'from' => null,
            'video' => false,
            'observeForceMute' => false
        );

        $options = array_merge($defaults, array_intersect_key($options, $defaults));

        // validate arguments
        Validators::validateSessionIdBelongsToKey($sessionId, $this->apiKey);

        // make API call
        $sipJson = $this->client->dial($sessionId, $token, $sipUri, $options);

        // check response
        $id = $sipJson['id'];
        if (!$id) {
            $errorMessage = 'Failed to initiate a SIP call. Server response: ' . $sipJson;
            throw new UnexpectedValueException($errorMessage);
        }

        return new SipCall($sipJson);
    }

    /**
     * Plays a DTMF string into a session or to a specific connection
     *
     * @param string $sessionId The ID of the OpenTok session that the participant being called
     * will join.
     *
     * @param string $digits DTMF digits to play
     * Valid DTMF digits are 0-9, p, #, and * digits. 'p' represents a 500ms pause if a delay is
     * needed during the input process.
     *
     * @param string $connectionId An optional parameter used to send the DTMF tones to a specific connection in a session.
     *
     * @return void
     */
    public function playDTMF(string $sessionId, string $digits, string $connectionId = null): void
    {
        Validators::validateSessionIdBelongsToKey($sessionId, $this->apiKey);
        Validators::validateDTMFDigits($digits);

        $this->client->playDTMF($sessionId, $digits, $connectionId);
    }

    /**
     * Sends a signal to clients (or a specific client) connected to an OpenTok session.
     *
     * @param string $sessionId The OpenTok session ID where the signal will be sent.
     *
     *
     * @param array $payload This array defines the payload for the signal. This array includes the
     * following keys, of which type is optional:
     *
     * <ul>
     *
     *    <li><code>'data'</code> (string) &mdash; The data string for the signal. You can send a maximum of 8kB.</li>
     *    <li><code>'type'</code> (string) &mdash; (Optional) The type string for the signal. You can send a maximum of 128 characters, and only the following characters are allowed: A-Z, a-z, numbers (0-9), '-', '_', and '~'. </li>
     *
     * </ul>
     *
     *
     * @param string $connectionId An optional parameter used to send the signal to a specific connection in a session.
     */
    public function signal($sessionId, $payload, $connectionId = null)
    {

        // unpack optional arguments (merging with default values) into named variables
        $defaults = array(
            'type' => '',
            'data' => '',
        );

        $payload = array_merge($defaults, array_intersect_key($payload, $defaults));
        list($type, $data) = array_values($payload);

        // validate arguments
        Validators::validateSessionIdBelongsToKey($sessionId, $this->apiKey);
        Validators::validateSignalPayload($payload);

        if (is_null($connectionId) || empty($connectionId)) {
            // make API call without connectionId
            $this->client->signal($sessionId, $payload);
        } else {
            Validators::validateConnectionId($connectionId);
            // make API call with connectionId
            $this->client->signal($sessionId, $payload, $connectionId);
        }
    }

    /**
     * Starts an <a href="https://tokbox.com/developer/guides/audio-connector/">Audio Connector</a>
     * WebSocket connection. to send audio from a Vonage Video API session to a WebSocket.
     *
     * @param string $sessionId The session ID.
     *
     * @param string $token The OpenTok token to be used for the Audio Connector to the
     * OpenTok session. You can add token data to identify that the connection
     * is the Audio Connector endpoint or for other identifying data.
     *
     * @param array $websocketOptions Configuration for the Websocket. Contains the following keys:
     * <ul>
     *    <li><code>'uri'</code> (string) &mdash; A publically reachable WebSocket URI controlled by the customer for the destination of the connect call. (f.e. wss://service.com/wsendpoint)</li>
     *    <li><code>'streams'</code> (array) &mdash; (Optional) The stream IDs of the participants' whose audio is going to be connected. If not provided, all streams in session will be selected.</li>
     *    <li><code>'headers'</code> (array) &mdash; (Optional) An object of key/val pairs with additional properties to send to your Websocket server, with a maximum length of 512 bytes.</li>
     * </ul>
     *
     * @return array $response Response from the API, structured as follows:
     * <ul>
     *    <li><code>'id'</code> (string) &mdash; A unique ID identifying the Audio Connector
     *    WebSocket.</li>
     *    <li><code>'connectionId'</code> (string) &mdash; Opentok client connectionId that has been created. This connection will subscribe and forward the streams defined in the payload to the WebSocket, as any other participant, will produce a connectionCreated event on the session.</li>
     * </ul>
     *
     *
     */
    public function connectAudio(string $sessionId, string $token, array $websocketOptions)
    {
        Validators::validateSessionId($sessionId);
        Validators::validateWebsocketOptions($websocketOptions);

        return $this->client->connectAudio($sessionId, $token, $websocketOptions);
    }

    /** @internal */
    private function signString($string, $secret)
    {
        return hash_hmac("sha1", $string, $secret);
    }
}
