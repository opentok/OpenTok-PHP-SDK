<?php

namespace OpenTok;

use OpenTok\Session;
use OpenTok\Stream;
use OpenTok\StreamList;
use OpenTok\Archive;
use OpenTok\Broadcast;
use OpenTok\Layout;
use OpenTok\Role;
use OpenTok\MediaMode;
use OpenTok\ArchiveMode;
use OpenTok\OutputMode;
use OpenTok\Util\Client;
use OpenTok\Util\Validators;

use OpenTok\Exception\UnexpectedValueException;
use OpenTok\Exception\InvalidArgumentException;

/**
* Contains methods for creating OpenTok sessions, generating tokens, and working with archives.
* <p>
* To create a new OpenTok object, call the OpenTok() constructor with your OpenTok API key
* and the API secret for your <a href="https://tokbox.com/account">TokBox account</a>. Do not
* publicly share your API secret. You will use it with the OpenTok() constructor (only on your web
* server) to create OpenTok sessions.
* <p>
* Be sure to include the entire OpenTok server SDK on your web server.
*/
class OpenTok {

    /** @internal */
    private $apiKey;
    /** @internal */
    private $apiSecret;
    /** @internal */
    private $client;

    /** @internal */
    public function __construct($apiKey, $apiSecret, $options = array())
    {
        // unpack optional arguments (merging with default values) into named variables
        $defaults = array('apiUrl' => 'https://api.opentok.com', 'client' => null);
        $options = array_merge($defaults, array_intersect_key($options, $defaults));
        list($apiUrl, $client) = array_values($options);

        // validate arguments
        Validators::validateApiKey($apiKey);
        Validators::validateApiSecret($apiSecret);
        Validators::validateApiUrl($apiUrl);
        Validators::validateClient($client);

        $this->client = isset($client) ? $client : new Client();
        if (!$this->client->isConfigured()) {
            $this->client->configure($apiKey, $apiSecret, $apiUrl);
        }
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    /**
     * Creates a token for connecting to an OpenTok session. In order to authenticate a user
     * connecting to an OpenTok session, the client passes a token when connecting to the session.
     * <p>
     * For testing, you generate tokens or by logging in to your
     * <a href="https://tokbox.com/account">TokBox account</a>.
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
            ((!empty($initialLayoutClassList)) ? "&initial_layout_class_list=" . urlencode(join(" ",$initialLayoutClassList)) : '');
        $sig = $this->_sign_string($dataString, $this->apiSecret);

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
    * <a href="https://tokbox.com/account">TokBox account</a>.
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
    *    <li><code>'location'</code> (String) &mdash; An IP address that the OpenTok servers
    *    will use to situate the session in its global network. If you do not set a location hint,
    *    the OpenTok servers will be based on the first client connecting to the session.</li>
    *
    *     <li><code>'mediaMode'</code> (MediaMode) &mdash; Whether the session will transmit
    *     streams using the OpenTok Media Router (<code>MediaMode.ROUTED</code>) or not
    *     (<code>MediaMode.RELAYED</code>). By default, the <code>mediaMode</code> property
    *     is set to <code>MediaMode.RELAYED</code>.
    *
    *     <p>
    *     With the <code>mediaMode</code> parameter set to <code>MediaMode.RELAYED</code>, the
    *     session will attempt to transmit streams directly between clients. If clients cannot
    *     connect due to firewall restrictions, the session uses the OpenTok TURN server to relay
    *     audio-video streams.
    *
    *     <p>
    *     The
    *     <a href="https://tokbox.com/opentok/tutorials/create-session/#media-mode" target="_top">
    *     OpenTok Media Router</a> provides the following benefits:
    *
    *     <ul>
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
    *     </ul>
    *
    * </ul>
    *
    * @return \OpenTok\Session A Session object representing the new session. Call the
    * <code>getSessionId()</code> method of this object to get the session ID. For example,
    * when using the OpenTok.js library, use this session ID when calling the
    * <code>OT.initSession()</code> method.
    */
    public function createSession($options=array())
    {
        if (array_key_exists('archiveMode', $options) &&
            $options['archiveMode'] != ArchiveMode::MANUAL) {

            if (array_key_exists('mediaMode', $options) &&
                $options['mediaMode'] != MediaMode::ROUTED) {

                throw new InvalidArgumentException('A session must be routed to be archived.');
            } else {
              $options['mediaMode'] = MediaMode::ROUTED;
            }
        }

        // unpack optional arguments (merging with default values) into named variables
        $defaults = array(
            'mediaMode' => MediaMode::RELAYED,
            'archiveMode' => ArchiveMode::MANUAL,
            'location' => null
        );
        $options = array_merge($defaults, array_intersect_key($options, $defaults));
        list($mediaMode, $archiveMode, $location) = array_values($options);

        // validate arguments
        Validators::validateMediaMode($mediaMode);
        Validators::validateArchiveMode($archiveMode);
        Validators::validateLocation($location);

        // make API call
        $sessionXml = $this->client->createSession($options);

        // check response
        $sessionId = $sessionXml->Session->session_id;
        if (!$sessionId) {
            $errorMessage = 'Failed to create a session. Server response: '. (string)$sessionXml;
            throw new UnexpectedValueException($errorMessage);
        }

        return new Session($this, (string)$sessionId, array(
            'location' => $location,
            'mediaMode' => $mediaMode,
            'archiveMode' => $archiveMode
        ));
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
     *    <li><code>'hasAudio'</code> (Boolean) &mdash; Whether the archive will record audio
     *    (true, the default) or not (false). If you set both <code>hasAudio</code> and
     *    <code>hasVideo</code> to false, the call to the <code>startArchive()</code> method results
     *    in an error.</li>
     *
     *    <li><code>'outputMode'</code> (OutputMode) &mdash; Whether all streams in the
     *    archive are recorded to a single file (<code>OutputMode::COMPOSED</code>, the default)
     *    or to individual files (<code>OutputMode::INDIVIDUAL</code>).</li>
     *
     * <ul>
     *
     * @return Archive The Archive object, which includes properties defining the archive, including
     * the archive ID.
     */
    public function startArchive($sessionId, $options=array())
    {
        // support for deprecated method signature, remove in v3.0.0 (not before)
        if (!is_array($options)) {
            $options = array('name' => $options);
        }
        
        // unpack optional arguments (merging with default values) into named variables
        $defaults = array(
            'name' => null,
            'hasVideo' => true,
            'hasAudio' => true,
            'outputMode' => OutputMode::COMPOSED,
            'resolution' => null,
        );
        $options = array_merge($defaults, array_intersect_key($options, $defaults));
        list($name, $hasVideo, $hasAudio, $outputMode, $resolution) = array_values($options);
        // validate arguments
        Validators::validateSessionId($sessionId);
        Validators::validateArchiveName($name);
        Validators::validateArchiveHasVideo($hasVideo);
        Validators::validateArchiveHasAudio($hasAudio);
        Validators::validateArchiveOutputMode($outputMode);

        if ((is_null($resolution) || empty($resolution)) && $outputMode === OutputMode::COMPOSED) {
            $options['resolution'] = "640x480";
        } else if((is_null($resolution) || empty($resolution)) && $outputMode === OutputMode::INDIVIDUAL) {
            unset($options['resolution']);
        } else if(!empty($resolution) && $outputMode === OutputMode::INDIVIDUAL) {
            $errorMessage = "Resolution can't be specified for Individual Archives";
            throw new UnexpectedValueException($errorMessage);
        } else if(!empty($resolution) && $outputMode === OutputMode::COMPOSED && !is_string($resolution)) {
            $errorMessage = "Resolution must be a valid string";
            throw new UnexpectedValueException($errorMessage);
        }
        // we don't validate the actual resolution so if we add resolutions, we don't artificially block functionality
        // make API call
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
     * Returns an ArchiveList. The <code>items()</code> method of this object returns a list of
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
    public function listArchives($offset=0, $count=null, $sessionId=null)
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
     *
     * @param string $archiveId The OpenTok archive ID.
     *
     * @param string $layout The connectionId of the connection in a session.
     */

    public function setArchiveLayout($archiveId, $layoutType)
    {
        Validators::validateArchiveId($archiveId);
        Validators::validateLayout($layoutType);
        
        $this->client->setArchiveLayout($archiveId, $layoutType);
    }

    /**
     * Sets the layout class list for streams in a session. Layout classes are used in
     * the layout for composed archives and live streaming broadcasts. For more information, see
     * <a href="https://tokbox.com/developer/guides/archiving/layout-control.html">Customizing
     * the video layout for composed archives</a> and
     * <a href="https://tokbox.com/developer/guides/broadcast/live-streaming/#configuring-video-layout-for-opentok-live-streaming-broadcasts">Configuring
     * video layout for OpenTok live streaming broadcasts</a>.
     * @param string $sessionId The session ID of the session the streams belong to.
     *
     * @param array $classListArray The connectionId of the connection in a session.
     */

    public function setStreamClassLists($sessionId, $classListArray=array())
    {
        Validators::validateSessionIdBelongsToKey($sessionId, $this->apiKey);
        
        foreach ($classListArray as $item ){
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
     * Starts a live streaming broadcast of an OpenTok session.
     *
     * @param String $sessionId The session ID of the session to be broadcast.
     *
     * @param Array $options (Optional) An array with options for the broadcast. This array has
     * the following properties:
     * <ul>
     *   <li><code>layout</code> (Layout) &mdash; (Optional) An object defining the initial
     *     layout type of the broadcast. If you do not specify an initial layout type,
     *     the broadcast stream uses the Best Fit layout type. For more information, see
     *     <a href="https://tokbox.com/developer/guides/broadcast/live-streaming/#configuring-live-streaming-video-layout">Configuring
     *     Video Layout for the OpenTok live streaming feature</a>.
     *   </li>
     * </ul>
     *
     * @return Broadcast An object with properties defining the broadcast.
     */
    public function startBroadcast($sessionId, $options=array())
    {
        // unpack optional arguments (merging with default values) into named variables
        // NOTE: although the server can be authoritative about the default value of layout, its
        // not preferred to depend on that in the SDK because its then harder to garauntee backwards
        // compatibility
        $defaults = array(
            'layout' => Layout::getBestFit()
        );
        $options = array_merge($defaults, $options);
        list($layout) = array_values($options);

        // validate arguments
        Validators::validateSessionId($sessionId);
        Validators::validateLayout($layout);

        // make API call
        $broadcastData = $this->client->startBroadcast($sessionId, $options);

        return new Broadcast($broadcastData, array( 'client' => $this->client ));
    }

    /**
     * Stops a broadcast.
     *
     * @param String $broadcastId The ID of the broadcast.
     */
    public function stopBroadcast($broadcastId)
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
    public function getBroadcast($broadcastId)
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
    public function updateBroadcastLayout($broadcastId, $layout)
    {
        Validators::validateBroadcastId($broadcastId);
        Validators::validateLayout($layout);

        // TODO: platform implementation does not meet API Review spec
        // $layoutData = $this->client->updateLayout($broadcastId, $layout, 'broadcast');
        // return Layout::fromData($layoutData);

        $this->client->updateLayout($broadcastId, $layout, 'broadcast');
    }

    /**
    * Sets the layout class list for a stream. Layout classes are used in
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
    * <a href="#method_generateToken">OpenTok->generateToken()</a> method.
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
     * Returns a StreamList Object for the given session ID.
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
     * <a href="#method_generateToken">OpenTok->generateToken()</a> or
     * <a href="OpenTok.Session.html#method_generateToken">Session->generateToken()</a>
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
     * @param array $options This array defines options for the token. It includes the
     * following keys, all of which are optional:
     *
     * <ul>
     *
     *    <li><code>'headers'</code> (array) &mdash; Headers​: Custom Headers to be added to the
     *    SIP INVITE request initiated from OpenTok to the Third Party SIP Platform. All of this
     *    custom headers must start with the "X-" prefix, or a Bad Request (400) will be
     *    thrown.</li>
     *
     *    <li><code>'auth'</code> (array) &mdash; Auth​: Username and Password to be used in the SIP
     *    INVITE request for HTTP Digest authentication in case this is required by the Third Party
     *    SIP Platform.
     *
     *     <ul>
     *
     *       <li><code>'username'</code> (string) &mdash; The username to be used
     *       in the the SIP INVITE​ request for HTTP digest authentication (if one
     *       is required).</li>
     *
     *       <li><code>'password'</code> (string) &mdash; The password to be used
     *       in the the SIP INVITE​ request for HTTP digest authentication.</li>
     *
     *     </ul>
     *
     *    <li><code>'secure'</code> (Boolean) &mdash; Indicates whether the media
     *    must be transmitted encrypted (true, the default) or not (false).</li>
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
     * <a href="#method_forceDisconnect">OpenTok->forceDisconnect()</a> method.
     */
    public function dial($sessionId, $token, $sipUri, $options=array())
    {
        // unpack optional arguments (merging with default values) into named variables
        $defaults = array(
            'auth' => null,
            'headers' => null,
            'secure' => true,
            'from' => null,
        );
        $options = array_merge($defaults, array_intersect_key($options, $defaults));
        list($headers, $secure, $from) = array_values($options);

        // validate arguments
        Validators::validateSessionIdBelongsToKey($sessionId, $this->apiKey);

        // make API call
        $sipJson = $this->client->dial($sessionId, $token, $sipUri, $options);

        // check response
        $id = $sipJson['id'];
        if (!$id) {
            $errorMessage = 'Failed to initiate a SIP call. Server response: '. (string)$sipJson;
            throw new UnexpectedValueException($errorMessage);
        }

        return new SipCall($sipJson);
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
    public function signal($sessionId, $payload, $connectionId=null)
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

    /** @internal */
    private function _sign_string($string, $secret)
    {
        return hash_hmac("sha1", $string, $secret);
    }
}

/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
